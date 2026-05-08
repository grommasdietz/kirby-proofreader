<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader\Tests\Integration;

use GrommasDietz\Proofreader\Tests\TestCase;
use Kirby\Http\Response;

final class PlaygroundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootKirby()->impersonate('kirby');
    }

    public function testPluginRegistersWithKirby(): void
    {
        $this->assertNotNull($this->kirby->plugin('grommasdietz/proofreader'));
    }

    public function testHomePageCanBeLoaded(): void
    {
        $page = $this->kirby->page('home');

        $this->assertSame('home', $page?->id());
        $this->assertSame('Home', $page?->title()->value());
    }

    public function testProofreaderRouteAppliesPageTitleViaTitleAction(): void
    {
        $page = $this->kirby->page('editorial-review');

        $this->assertNotNull($page);
        $this->assertSame('Editorial Review...', $page->title()->value());

        try {
            $response = $this->callProofreaderRoute(
                'kirby-proofreader/pages/editorial-review/optimize',
                [
                    'preview' => false,
                    'rules'   => ['ellipsis'],
                    'fields'  => ['title'],
                ]
            );
            $data = $this->jsonResponse($response);
            $page = $this->kirby->page('editorial-review');
            $language = $this->kirby->language('en') ?? 'default';

            $this->assertSame('ok', $data['status']);
            $this->assertSame(['title'], $data['changedFields']);
            $this->assertSame('Editorial Review…', $data['diffs']['title']['to']);
            $this->assertSame('Editorial Review…', $page?->title()->value());
            $this->assertFalse($page?->version('changes')->exists($language));
        } finally {
            $page = $this->kirby->page('editorial-review');
            $language = $this->kirby->language('en') ?? 'default';

            if ($page !== null && $page->title()->value() !== 'Editorial Review...') {
                $page = $page->changeTitle('Editorial Review...', 'en');
            }

            if ($page?->version('changes')->exists($language) === true) {
                $page->version('changes')->delete($language);
            }
        }
    }

    public function testProofreaderRouteSupportsSiteContent(): void
    {
        $response = $this->callProofreaderRoute(
            'kirby-proofreader/site/optimize',
            [
                'preview' => true,
                'rules'   => ['dashes'],
                'fields'  => ['description'],
            ]
        );
        $data = $this->jsonResponse($response);

        $this->assertSame('ok', $data['status']);
        $this->assertSame(['description'], array_unique(array_column($data['suggestions'], 'field')));
        $this->assertSame('Description', $data['suggestions'][0]['fieldLabel']);
    }

    public function testOptionalDimensionRuleCanReviewPlaygroundContent(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'rules' => [
                        'unicode',
                        'ellipsis',
                        'quotes',
                        'dashes',
                        'spaces',
                        'dimensions',
                    ],
                ],
            ],
        ])->impersonate('kirby');

        $response = $this->callProofreaderRoute(
            'kirby-proofreader/pages/editorial-review/optimize',
            [
                'preview' => true,
                'rules'   => ['dimensions'],
                'fields'  => ['summary'],
            ]
        );
        $data = $this->jsonResponse($response);

        $this->assertSame('ok', $data['status']);
        $this->assertSame(['dimensions'], array_unique(array_column($data['suggestions'], 'rule')));
        $this->assertSame(['summary'], array_unique(array_column($data['suggestions'], 'field')));
    }

    public function testListFieldCanReviewPlaygroundContent(): void
    {
        $response = $this->callProofreaderRoute(
            'kirby-proofreader/pages/editorial-review/optimize',
            [
                'preview' => true,
                'rules'   => ['ellipsis', 'dashes'],
                'fields'  => ['checklist'],
            ]
        );
        $data = $this->jsonResponse($response);

        $this->assertSame('ok', $data['status']);
        $this->assertSame(['checklist'], array_unique(array_column($data['suggestions'], 'field')));
        $this->assertContains('ellipsis', array_column($data['suggestions'], 'rule'));
        $this->assertContains('dashes', array_column($data['suggestions'], 'rule'));
    }

    public function testUsersCanBeCreatedAndDeleted(): void
    {
        // Clean up any leftover test users from a prior run
        // (prepareAccounts is non-destructive to support seeded users)
        foreach (['primary-admin@kirby-proofreader.test', 'secondary-admin@kirby-proofreader.test'] as $email) {
            $existing = $this->kirby->user($email);
            $existing?->delete();
        }

        // seedUsers() creates a default admin during boot
        $seededCount = $this->kirby->users()->count();
        $this->assertGreaterThanOrEqual(1, $seededCount);

        $primaryAdmin = $this->kirby->users()->create([
            'email' => 'primary-admin@kirby-proofreader.test',
            'name' => 'Primary Admin',
            'role' => 'admin',
            'password' => 'test-password',
        ]);

        $secondaryAdmin = $this->kirby->users()->create([
            'email' => 'secondary-admin@kirby-proofreader.test',
            'name' => 'Secondary Admin',
            'role' => 'admin',
            'password' => 'test-password',
        ]);

        $this->assertSame('admin', $primaryAdmin->role()->name());
        $this->assertSame('admin', $secondaryAdmin->role()->name());
        $this->assertCount($seededCount + 2, $this->kirby->users());

        $secondaryAdmin->delete();

        $this->assertCount($seededCount + 1, $this->kirby->users());
        $this->assertNotNull(
            $this->kirby->user('primary-admin@kirby-proofreader.test')
        );

        // Clean up test-created user so it doesn't persist on disk
        $primaryAdmin->delete();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function callProofreaderRoute(string $path, array $body): Response
    {
        $this->kirby = $this->kirby->clone([
            'request' => [
                'method'  => 'POST',
                'body'    => $body,
                'headers' => [
                    'x-language' => 'en',
                ],
            ],
        ]);
        $this->kirby->impersonate('kirby');

        $response = $this->kirby->call($path, 'POST');

        $this->assertInstanceOf(Response::class, $response);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonResponse(Response $response): array
    {
        $data = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        if (is_array($data) === false) {
            throw new \RuntimeException('Proofreader route did not return a JSON object');
        }

        return $data;
    }
}
