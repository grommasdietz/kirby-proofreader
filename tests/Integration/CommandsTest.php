<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader\Tests\Integration;

use GrommasDietz\Proofreader\Proofreader;
use GrommasDietz\Proofreader\Tests\TestCase;
use Kirby\Cms\Language;
use Kirby\Filesystem\Dir;

/**
 * Integration tests for the logic used by the Kirby CLI commands.
 *
 * These tests exercise the same model-processing flow that
 * proofreader:fix and proofreader:review use internally —
 * reading from the content version, calling reviewFields,
 * computing diffs, and writing back to the appropriate version.
 * A few smoke tests also shell out to the playground's real Kirby CLI binary
 * so documented flags stay wired to the parser.
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
        self::assertNotNull($page);

        $language = $this->kirby->language('en');
        $content  = $page->version('latest')->content($language)->toArray();

        $review = Proofreader::reviewFields(
            $content,
            $page->blueprint()->fields(),
            ['ellipsis'],
            null
        );

        self::assertArrayHasKey('suggestions', $review);
        self::assertArrayHasKey('fixed', $review);
        self::assertNotEmpty($review['suggestions'], 'Editorial review page should have at least one ellipsis suggestion');
    }

    public function testReviewLogicReturnsSuggestionWithExpectedStructure(): void
    {
        $page = $this->kirby->page('editorial-review');
        self::assertNotNull($page);

        $language = $this->kirby->language('en');
        $content  = $page->version('latest')->content($language)->toArray();

        $review = Proofreader::reviewFields(
            $content,
            $page->blueprint()->fields(),
            ['ellipsis'],
            null
        );

        $suggestion = $review['suggestions'][0] ?? null;
        self::assertNotNull($suggestion);
        self::assertArrayHasKey('field', $suggestion);
        self::assertArrayHasKey('rule', $suggestion);
        self::assertArrayHasKey('previewBefore', $suggestion);
        self::assertArrayHasKey('previewAfter', $suggestion);
        self::assertSame('ellipsis', $suggestion['rule']);
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

        self::assertArrayHasKey('suggestions', $review);
        self::assertArrayHasKey('fixed', $review);
    }

    // -------------------------------------------------------------------------
    // Fix logic (write path — mirrors proofreader:fix)
    // -------------------------------------------------------------------------

    public function testFixLogicComputesDiffsFromPageContent(): void
    {
        $page = $this->kirby->page('editorial-review');
        self::assertNotNull($page);

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

        self::assertNotEmpty($diffs, 'At least one field should have a diff after applying the ellipsis rule');
    }

    public function testFixLogicSavesToChangesVersionAndCanBeCleanedUp(): void
    {
        $page = $this->kirby->page('editorial-review');
        self::assertNotNull($page);

        $language       = $this->kirby->language('en');
        $changesVersion = $page->version('changes');
        $latestVersion  = $page->version('latest');

        self::assertFalse(
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

            self::assertTrue(
                $changesVersion->exists($language),
                'Changes version should exist after saving fixes'
            );

            $saved = $changesVersion->content($language)->toArray();
            self::assertStringContainsString('…', $saved['title'] ?? '');
        } finally {
            if ($changesVersion->exists($language)) {
                $changesVersion->delete($language);
            }
        }
    }

    public function testDryRunLogicDoesNotWriteToAnyVersion(): void
    {
        $page = $this->kirby->page('editorial-review');
        self::assertNotNull($page);

        $language       = $this->kirby->language('en');
        $changesVersion = $page->version('changes');

        self::assertFalse($changesVersion->exists($language));

        $content = $page->version('latest')->content($language)->toArray();

        // Compute review but intentionally skip the save step (dry-run behaviour)
        $review = Proofreader::reviewFields(
            $content,
            $page->blueprint()->fields(),
            ['ellipsis'],
            null
        );

        self::assertNotEmpty($review['suggestions']);

        // No save — changes version must remain absent
        self::assertFalse(
            $changesVersion->exists($language),
            'Dry-run must not write to the changes version'
        );
    }

    public function testFixLogicWritesToLatestVersionWhenPublishFlagIsSet(): void
    {
        $page = $this->kirby->page('editorial-review');
        self::assertNotNull($page);

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
            self::assertStringContainsString('…', $saved['title'] ?? '');
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
        self::assertNotNull($page);

        $language = $this->kirby->language('en');
        self::assertInstanceOf(Language::class, $language);

        $content = $page->version('latest')->content($language)->toArray();

        $review = Proofreader::reviewFields(
            $content,
            $page->blueprint()->fields(),
            ['ellipsis'],
            $language->code()
        );

        self::assertNotEmpty($review['suggestions']);
    }

    public function testReviewLogicUsesDefaultLanguageWhenCliLanguageIsOmitted(): void
    {
        $language = $this->kirby->defaultLanguage();
        self::assertInstanceOf(Language::class, $language);

        $content = $this->kirby->site()->version('latest')->content($language)->toArray();

        $review = Proofreader::reviewFields(
            $content,
            $this->kirby->site()->blueprint()->fields(),
            ['dashes'],
            $language->code()
        );

        self::assertNotEmpty($review['suggestions']);
    }

    // -------------------------------------------------------------------------
    // Real CLI parser smoke tests
    // -------------------------------------------------------------------------

    public function testKirbyCliHelpListsRegisteredFixFlags(): void
    {
        $result = $this->runKirbyCli(['proofreader:fix', '--help']);

        self::assertSame(0, $result['exitCode'], $result['output']);

        foreach (['--all', '--children', '--recursive', '--publish', '--dry-run', '--language', '--rules'] as $flag) {
            self::assertStringContainsString($flag, $result['output']);
        }
    }

    public function testKirbyCliHelpListsRegisteredReviewFlags(): void
    {
        $result = $this->runKirbyCli(['proofreader:review', '--help']);

        self::assertSame(0, $result['exitCode'], $result['output']);

        foreach (['--all', '--children', '--recursive', '--language', '--rules'] as $flag) {
            self::assertStringContainsString($flag, $result['output']);
        }
    }

    public function testKirbyCliReviewParsesRulesFlag(): void
    {
        $result = $this->runKirbyCli(['proofreader:review', 'editorial-review', '--rules=ellipsis']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('[ellipsis]', $result['output']);
        self::assertStringNotContainsString('[dashes]', $result['output']);
        self::assertStringNotContainsString('[spaces]', $result['output']);
    }

    public function testKirbyCliReviewUsesDefaultLanguageForSiteModel(): void
    {
        $result = $this->runKirbyCli(['proofreader:review']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('[dashes] Description', $result['output']);
        self::assertStringContainsString('suggestion(s) found.', $result['output']);
    }

    public function testKirbyCliReviewAllAggregatesSuggestionsAcrossModels(): void
    {
        $result = $this->runKirbyCli(['proofreader:review', '--all']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertMatchesRegularExpression('/suggestion\(s\) across \d+ model\(s\)\.|No suggestions/', $result['output']);
    }

    public function testKirbyCliReviewAllSkipsVirtualPages(): void
    {
        $modelsDir = dirname(__DIR__, 2) . '/playground/site/models';
        $modelFile = $modelsDir . '/home.php';
        $hadModelsDir = is_dir($modelsDir);

        Dir::make($modelsDir, true);

        $model = <<<'PHP'
<?php

use Kirby\Cms\Page;
use Kirby\Cms\Pages;

class HomePage extends Page
{
    public function children(): Pages
    {
        return parent::children()->add(Page::factory([
            'slug'     => 'virtual-in-progress',
            'parent'   => $this,
            'template' => $this->intendedTemplate()->name(),
            'model'    => 'default',
            'content'  => ['title' => 'In Bearbeitung'],
        ]));
    }
}
PHP;

        if (file_put_contents($modelFile, $model) === false) {
            self::fail('Unable to write virtual page model fixture.');
        }

        try {
            $result = $this->runKirbyCli(['proofreader:review', '--all', '--rules=spaces']);

            self::assertSame(0, $result['exitCode'], $result['output']);
            self::assertStringNotContainsString('[In Bearbeitung]', $result['output']);
        } finally {
            if (is_file($modelFile)) {
                unlink($modelFile);
            }

            if ($hadModelsDir === false && is_dir($modelsDir)) {
                Dir::remove($modelsDir);
            }
        }
    }

    public function testKirbyCliReviewChildrenReturnsNoModelsWhenPageHasNoChildren(): void
    {
        $result = $this->runKirbyCli(['proofreader:review', 'editorial-review', '--children']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('No models to review.', $result['output']);
    }

    public function testKirbyCliDryRunUsesDefaultLanguageForSiteModel(): void
    {
        $result = $this->runKirbyCli(['proofreader:fix', '--dry-run']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Dry run — no changes will be saved.', $result['output']);
        self::assertStringContainsString('[site] would change: 1 field(s)', $result['output']);
        self::assertStringContainsString('1 field(s) would change across 1 model(s).', $result['output']);
    }

    public function testKirbyCliFixCanWriteThroughRealBinary(): void
    {
        $pageDir = $this->createCliTestPage();

        try {
            $result = $this->runKirbyCli([
                'proofreader:fix',
                'cli-proofreader-test',
                '--language=en',
                '--rules=ellipsis',
            ]);

            self::assertSame(0, $result['exitCode'], $result['output']);
            self::assertStringContainsString('fixed (changes): 2 field(s)', $result['output']);
            self::assertStringContainsString('2 field(s) fixed across 1 model(s).', $result['output']);

            $latest = file_get_contents($pageDir . '/about.en.txt');
            $changes = file_get_contents($pageDir . '/_changes/about.en.txt');

            self::assertIsString($latest);
            self::assertIsString($changes);
            self::assertStringContainsString('Title: CLI Proofreader Test…', $latest);
            self::assertStringContainsString('Summary: CLI summary…', $changes);
        } finally {
            Dir::remove($pageDir);
        }
    }

    /**
     * @param list<string> $arguments
     * @return array{exitCode:int, output:string}
     */
    private function runKirbyCli(array $arguments): array
    {
        $root = dirname(__DIR__, 2);
        $playground = $root . '/playground';
        $binary = $playground . '/vendor/bin/kirby';

        if (is_file($binary) === false) {
            self::fail('Kirby CLI binary missing. Run composer install -d playground.');
        }

        $process = proc_open(
            [PHP_BINARY, $binary, ...$arguments],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $playground
        );

        if (is_resource($process) === false) {
            self::fail('Unable to start Kirby CLI process.');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $output = (is_string($stdout) ? $stdout : '') . (is_string($stderr) ? $stderr : '');

        return [
            'exitCode' => $exitCode,
            'output'   => preg_replace('/\e\[[0-9;]*m/u', '', $output) ?? $output,
        ];
    }

    private function createCliTestPage(): string
    {
        $pageDir = dirname(__DIR__, 2) . '/playground/content/cli-proofreader-test';

        Dir::remove($pageDir);
        Dir::make($pageDir, true);

        $content = <<<TXT
Title: CLI Proofreader Test...

----

Summary: CLI summary...

----

Uuid: cli-proofreader-test
TXT;

        if (file_put_contents($pageDir . '/about.en.txt', $content) === false) {
            throw new \RuntimeException('Unable to write CLI test page fixture.');
        }

        return $pageDir;
    }
}
