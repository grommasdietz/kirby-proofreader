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
        self::assertNotNull($this->kirby->plugin('grommasdietz/proofreader'));
    }

    public function testHomePageCanBeLoaded(): void
    {
        $page = $this->kirby->page('home');

        self::assertSame('home', $page?->id());
        self::assertSame('Home', $page?->title()->value());
    }

    public function testProofreaderRouteAppliesPageTitleViaTitleAction(): void
    {
        $page = $this->kirby->page('editorial-review');

        self::assertNotNull($page);
        self::assertSame('Editorial Review...', $page->title()->value());

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

            self::assertSame('ok', $data['status']);
            self::assertSame(['title'], $data['changedFields']);
            self::assertSame('Editorial Review…', $data['diffs']['title']['to']);
            self::assertSame('Editorial Review…', $page?->title()->value());
            self::assertFalse($page?->version('changes')->exists($language));
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

        self::assertSame('ok', $data['status']);
        self::assertSame(['description'], array_unique(array_column($data['suggestions'], 'field')));
        self::assertSame('Description', $data['suggestions'][0]['fieldLabel']);
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

        self::assertSame('ok', $data['status']);
        self::assertSame(['dimensions'], array_unique(array_column($data['suggestions'], 'rule')));
        self::assertSame(['summary'], array_unique(array_column($data['suggestions'], 'field')));
    }

    public function testProofreaderRouteCanIncludeConfiguredFieldNames(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'fields' => [
                        'include' => [
                            'names' => [
                                'date' => 'plain',
                            ],
                        ],
                    ],
                ],
            ],
        ])->impersonate('kirby');

        $response = $this->callProofreaderRoute(
            'kirby-proofreader/pages/editorial-review/optimize',
            [
                'preview' => true,
                'rules'   => ['dashes'],
                'fields'  => ['date'],
            ]
        );
        $data = $this->jsonResponse($response);

        self::assertSame('ok', $data['status']);
        self::assertSame(['date'], array_unique(array_column($data['suggestions'], 'field')));
        self::assertSame(['date'], $data['changedFields']);
    }

    public function testProofreaderRouteHonorsConfiguredFieldExcludes(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'fields' => [
                        'exclude' => [
                            'names' => ['summary'],
                        ],
                    ],
                ],
            ],
        ])->impersonate('kirby');

        $response = $this->callProofreaderRoute(
            'kirby-proofreader/pages/editorial-review/optimize',
            [
                'preview' => true,
                'rules'   => ['dashes', 'spaces', 'dimensions'],
                'fields'  => ['summary'],
            ]
        );
        $data = $this->jsonResponse($response);

        self::assertSame('ok', $data['status']);
        self::assertSame([], $data['suggestions']);
        self::assertSame([], $data['changedFields']);
        self::assertSame([], $data['diffs']);
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

        self::assertSame('ok', $data['status']);
        self::assertSame(['checklist'], array_unique(array_column($data['suggestions'], 'field')));
        self::assertContains('ellipsis', array_column($data['suggestions'], 'rule'));
        self::assertContains('dashes', array_column($data['suggestions'], 'rule'));
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
        self::assertGreaterThanOrEqual(1, $seededCount);

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

        self::assertSame('admin', $primaryAdmin->role()->name());
        self::assertSame('admin', $secondaryAdmin->role()->name());
        self::assertCount($seededCount + 2, $this->kirby->users());

        $secondaryAdmin->delete();

        self::assertCount($seededCount + 1, $this->kirby->users());
        self::assertNotNull(
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

        self::assertInstanceOf(Response::class, $response);

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
