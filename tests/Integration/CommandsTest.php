<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader\Tests\Integration;

use GrommasDietz\Proofreader\Proofreader;
use GrommasDietz\Proofreader\Tests\TestCase;
use Kirby\Cms\Language;

/**
 * Integration tests for the logic used by the Kirby CLI commands.
 *
 * These tests exercise the same model-processing flow that
 * proofreader:fix and proofreader:review use internally —
 * reading from the content version, calling reviewFields,
 * computing diffs, and writing back to the appropriate version —
 * without requiring the Kirby CLI binary or a mock \Kirby\CLI\CLI object.
 */
final class CommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootKirby()->impersonate('kirby');
    }

    // -------------------------------------------------------------------------
    // Review logic (read-only — mirrors proofreader:review)
    // -------------------------------------------------------------------------

    public function testReviewLogicFindsSuggestionsForPageWithFixableContent(): void
    {
        $page = $this->kirby->page('editorial-review');
        $this->assertNotNull($page);

        $language = $this->kirby->language('en');
        $content  = $page->version('latest')->content($language)->toArray();

        $review = Proofreader::reviewFields(
            $content,
            $page->blueprint()->fields(),
            ['ellipsis'],
            null
        );

        $this->assertArrayHasKey('suggestions', $review);
        $this->assertArrayHasKey('fixed', $review);
        $this->assertNotEmpty($review['suggestions'], 'Editorial review page should have at least one ellipsis suggestion');
    }

    public function testReviewLogicReturnsSuggestionWithExpectedStructure(): void
    {
        $page = $this->kirby->page('editorial-review');
        $this->assertNotNull($page);

        $language = $this->kirby->language('en');
        $content  = $page->version('latest')->content($language)->toArray();

        $review = Proofreader::reviewFields(
            $content,
            $page->blueprint()->fields(),
            ['ellipsis'],
            null
        );

        $suggestion = $review['suggestions'][0] ?? null;
        $this->assertNotNull($suggestion);
        $this->assertArrayHasKey('field', $suggestion);
        $this->assertArrayHasKey('rule', $suggestion);
        $this->assertArrayHasKey('previewBefore', $suggestion);
        $this->assertArrayHasKey('previewAfter', $suggestion);
        $this->assertSame('ellipsis', $suggestion['rule']);
    }

    public function testReviewLogicForSiteModelReturnsSuggestions(): void
    {
        $site     = $this->kirby->site();
        $language = $this->kirby->language('en');
        $content  = $site->version('latest')->content($language)->toArray();

        $review = Proofreader::reviewFields(
            $content,
            $site->blueprint()->fields(),
            ['dashes'],
            null
        );

        $this->assertArrayHasKey('suggestions', $review);
        $this->assertArrayHasKey('fixed', $review);
    }

    // -------------------------------------------------------------------------
    // Fix logic (write path — mirrors proofreader:fix)
    // -------------------------------------------------------------------------

    public function testFixLogicComputesDiffsFromPageContent(): void
    {
        $page = $this->kirby->page('editorial-review');
        $this->assertNotNull($page);

        $language = $this->kirby->language('en');
        $content  = $page->version('latest')->content($language)->toArray();

        $review = Proofreader::reviewFields(
            $content,
            $page->blueprint()->fields(),
            ['ellipsis'],
            null
        );

        $fixed = $review['fixed'];
        $diffs = [];

        foreach ($fixed as $key => $value) {
            $origKey = (string) $key;
            if (isset($content[$origKey]) && $value !== $content[$origKey]) {
                $diffs[$origKey] = ['from' => $content[$origKey], 'to' => $value];
            }
        }

        $this->assertNotEmpty($diffs, 'At least one field should have a diff after applying the ellipsis rule');
    }

    public function testFixLogicSavesToChangesVersionAndCanBeCleanedUp(): void
    {
        $page = $this->kirby->page('editorial-review');
        $this->assertNotNull($page);

        $language       = $this->kirby->language('en');
        $changesVersion = $page->version('changes');
        $latestVersion  = $page->version('latest');

        $this->assertFalse(
            $changesVersion->exists($language),
            'Precondition: changes version must not exist before the test'
        );

        try {
            $content = $latestVersion->content($language)->toArray();

            $review = Proofreader::reviewFields(
                $content,
                $page->blueprint()->fields(),
                ['ellipsis'],
                null
            );

            $changesVersion->save($review['fixed'], $language);

            $this->assertTrue(
                $changesVersion->exists($language),
                'Changes version should exist after saving fixes'
            );

            $saved = $changesVersion->content($language)->toArray();
            $this->assertStringContainsString('…', $saved['title'] ?? '');
        } finally {
            if ($changesVersion->exists($language)) {
                $changesVersion->delete($language);
            }
        }
    }

    public function testDryRunLogicDoesNotWriteToAnyVersion(): void
    {
        $page = $this->kirby->page('editorial-review');
        $this->assertNotNull($page);

        $language       = $this->kirby->language('en');
        $changesVersion = $page->version('changes');

        $this->assertFalse($changesVersion->exists($language));

        $content = $page->version('latest')->content($language)->toArray();

        // Compute review but intentionally skip the save step (dry-run behaviour)
        $review = Proofreader::reviewFields(
            $content,
            $page->blueprint()->fields(),
            ['ellipsis'],
            null
        );

        $this->assertNotEmpty($review['suggestions']);

        // No save — changes version must remain absent
        $this->assertFalse(
            $changesVersion->exists($language),
            'Dry-run must not write to the changes version'
        );
    }

    public function testFixLogicWritesToLatestVersionWhenPublishFlagIsSet(): void
    {
        $page = $this->kirby->page('editorial-review');
        $this->assertNotNull($page);

        $language      = $this->kirby->language('en');
        $latestVersion = $page->version('latest');

        $originalContent = $latestVersion->content($language)->toArray();

        try {
            $review = Proofreader::reviewFields(
                $originalContent,
                $page->blueprint()->fields(),
                ['ellipsis'],
                null
            );

            // Simulate --publish: write fixed content directly to latest
            $latestVersion->save($review['fixed'], $language);

            $saved = $latestVersion->content($language)->toArray();
            $this->assertStringContainsString('…', $saved['title'] ?? '');
        } finally {
            // Restore original content to keep the playground in a known state
            $latestVersion->save($originalContent, $language);
        }
    }

    // -------------------------------------------------------------------------
    // Language-resolution logic (mirrors $resolveCliLanguage)
    // -------------------------------------------------------------------------

    public function testReviewLogicWithExplicitLanguageCodeProducesResults(): void
    {
        $page = $this->kirby->page('editorial-review');
        $this->assertNotNull($page);

        $language = $this->kirby->language('en');
        $this->assertInstanceOf(Language::class, $language);

        $content = $page->version('latest')->content($language)->toArray();

        $review = Proofreader::reviewFields(
            $content,
            $page->blueprint()->fields(),
            ['ellipsis'],
            $language->code()
        );

        $this->assertNotEmpty($review['suggestions']);
    }
}
