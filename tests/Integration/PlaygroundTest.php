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

    public function testProofreaderRouteSupportsPageFileContent(): void
    {
        $root = dirname(__DIR__, 2);
        $parentDir = $root . '/playground/content/kontext';
        $pageDir = $parentDir . '/heizen-mit-einem-eisspeicher';
        $blueprintDir = $root . '/playground/site/blueprints/files';
        $blueprintFile = $blueprintDir . '/image.yml';
        $parentContent = $parentDir . '/about.en.txt';
        $pageContent = $pageDir . '/about.en.txt';
        $filename = 'mlzd_msb_maison-del-la-sante_schnitt.png';
        $file = $pageDir . '/' . $filename;
        $content = $pageDir . '/' . $filename . '.en.txt';
        $changes = $pageDir . '/_changes/' . $filename . '.en.txt';
        $imageBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lCwL8AAAAABJRU5ErkJggg==',
            true
        );

        if ($imageBytes === false) {
            throw new \RuntimeException('Unable to decode test image');
        }

        if (is_dir($blueprintDir) === false) {
            mkdir($blueprintDir, 0777, true);
        }

        if (is_dir($pageDir) === false) {
            mkdir($pageDir, 0777, true);
        }

        file_put_contents($blueprintFile, <<<YAML
title: Image

buttons:
  proofreader: true

fields:
  alt:
    label: Alt text
    type: text
  caption:
    label: Caption
    type: textarea
YAML);
        file_put_contents($parentContent, <<<TXT
Title: Kontext
TXT);
        file_put_contents($pageContent, <<<TXT
Title: Heizen mit einem Eisspeicher
TXT);
        file_put_contents($file, $imageBytes);
        file_put_contents($content, <<<TXT
Template: image

----

Alt: Image...

----

Caption: Range 2020 - 2024
TXT);

        try {
            $this->bootKirby()->impersonate('kirby');

            $response = $this->callProofreaderRoute(
                'kirby-proofreader/files/pages+kontext+heizen-mit-einem-eisspeicher+files+' . $filename . '/optimize',
                [
                    'preview' => true,
                    'rules'   => ['ellipsis'],
                    'fields'  => ['alt'],
                ]
            );
            $data = $this->jsonResponse($response);

            self::assertSame('ok', $data['status']);
            self::assertSame(['alt'], array_unique(array_column($data['suggestions'], 'field')));
            self::assertSame('Alt text', $data['suggestions'][0]['fieldLabel']);

            $response = $this->callProofreaderRoute(
                'kirby-proofreader/files/pages+kontext+heizen-mit-einem-eisspeicher+files+' . $filename . '/optimize',
                [
                    'preview' => false,
                    'rules'   => ['ellipsis'],
                    'fields'  => ['alt'],
                ]
            );
            $data = $this->jsonResponse($response);
            $fileModel = $this->kirby
                ->page('kontext/heizen-mit-einem-eisspeicher')
                ?->file($filename);
            $language = $this->kirby->language('en') ?? 'default';

            self::assertSame('ok', $data['status']);
            self::assertSame(['alt'], $data['changedFields']);
            self::assertSame('Image…', $data['diffs']['alt']['to']);
            self::assertSame('Image…', $fileModel?->version('changes')->content($language)->get('alt')->value());
        } finally {
            foreach ([$changes, $content, $file, $pageContent, $parentContent, $blueprintFile] as $fixture) {
                if (is_file($fixture)) {
                    unlink($fixture);
                }
            }

            $changesDir = dirname($changes);
            if (is_dir($changesDir) && count(scandir($changesDir) ?: []) === 2) {
                rmdir($changesDir);
            }

            if (is_dir($blueprintDir) && count(scandir($blueprintDir) ?: []) === 2) {
                rmdir($blueprintDir);
            }

            if (is_dir($pageDir) && count(scandir($pageDir) ?: []) === 2) {
                rmdir($pageDir);
            }

            if (is_dir($parentDir) && count(scandir($parentDir) ?: []) === 2) {
                rmdir($parentDir);
            }
        }
    }

    public function testProofreaderRouteSupportsUserContent(): void
    {
        $root = dirname(__DIR__, 2);
        $blueprintDir = $root . '/playground/site/blueprints/users';
        $blueprintFile = $blueprintDir . '/admin.yml';
        $originalBlueprint = is_file($blueprintFile) ? file_get_contents($blueprintFile) : null;
        $user = null;

        if ($originalBlueprint === false) {
            $originalBlueprint = null;
        }

        if (is_dir($blueprintDir) === false) {
            mkdir($blueprintDir, 0777, true);
        }

        file_put_contents($blueprintFile, <<<YAML
title: Admin

buttons:
  proofreader: true

fields:
  bio:
    label: Bio
    type: textarea
YAML);

        try {
            $user = $this->kirby->users()->create([
                'email'    => 'profile-user-' . uniqid() . '@kirby-proofreader.test',
                'name'     => 'Profile User',
                'role'     => 'admin',
                'password' => 'test-password',
                'content'  => [
                    'bio' => 'Profile...',
                ],
            ]);

            $response = $this->callProofreaderRoute(
                'kirby-proofreader/users/' . rawurlencode($user->id()) . '/optimize',
                [
                    'preview' => true,
                    'rules'   => ['ellipsis'],
                    'fields'  => ['bio'],
                ]
            );
            $data = $this->jsonResponse($response);

            self::assertSame('ok', $data['status']);
            self::assertSame(['bio'], array_unique(array_column($data['suggestions'], 'field')));
            self::assertSame('Bio', $data['suggestions'][0]['fieldLabel']);

            $response = $this->callProofreaderRoute(
                'kirby-proofreader/users/' . rawurlencode($user->id()) . '/optimize',
                [
                    'preview' => false,
                    'rules'   => ['ellipsis'],
                    'fields'  => ['bio'],
                ]
            );
            $data = $this->jsonResponse($response);
            $language = $this->kirby->language('en') ?? 'default';
            $updatedUser = $this->kirby->user($user->id());

            self::assertSame('ok', $data['status']);
            self::assertSame(['bio'], $data['changedFields']);
            self::assertSame('Profile…', $data['diffs']['bio']['to']);
            self::assertSame('Profile…', $updatedUser?->version('changes')->content($language)->get('bio')->value());
        } finally {
            $user?->delete();

            if ($originalBlueprint !== null) {
                file_put_contents($blueprintFile, $originalBlueprint);
            } elseif (is_file($blueprintFile)) {
                unlink($blueprintFile);
            }

            if (is_dir($blueprintDir) && count(scandir($blueprintDir) ?: []) === 2) {
                rmdir($blueprintDir);
            }
        }
    }

    public function testProofreaderRouteSupportsBlueprintAreaContent(): void
    {
        $root = dirname(__DIR__, 2);
        $siblingBlueprintAreas = dirname($root) . '/kirby-blueprint-areas';

        if (is_dir($siblingBlueprintAreas) === false) {
            $this->markTestSkipped('Sibling kirby-blueprint-areas checkout is not available.');
        }

        $pluginSymlink = $root . '/tests/.plugins/kirby-blueprint-areas';
        if (is_link($pluginSymlink) === true || is_file($pluginSymlink) === true) {
            unlink($pluginSymlink);
        }

        symlink($siblingBlueprintAreas, $pluginSymlink);

        $this->bootKirby()->impersonate('kirby');

        $areaDir = $root . '/playground/site/blueprints/areas';
        $areaBlueprint = $areaDir . '/proofreader-area.yml';
        $pageDir = $root . '/playground/content/proofreader-area-test';
        $pageContent = $pageDir . '/about.en.txt';
        $pageChanges = $pageDir . '/_changes/about.en.txt';

        if (is_dir($areaDir) === false) {
            mkdir($areaDir, 0777, true);
        }

        if (is_dir($pageDir) === false) {
            mkdir($pageDir, 0777, true);
        }

        file_put_contents($areaBlueprint, <<<YAML
title: Proofreader Area
query: site.find("proofreader-area-test")

buttons:
  proofreader: true

fields:
  area_text:
    label: Area text
    type: text
YAML);
        file_put_contents($pageContent, <<<TXT
Title: Proofreader Area Test

----

Area_text: Area...
TXT);

        try {
            $response = $this->callProofreaderRoute(
                'kirby-proofreader/areas/proofreader-area/optimize',
                [
                    'preview' => true,
                    'rules'   => ['ellipsis'],
                    'fields'  => ['area_text'],
                ]
            );
            $data = $this->jsonResponse($response);

            self::assertSame('ok', $data['status']);
            self::assertSame(['area_text'], array_unique(array_column($data['suggestions'], 'field')));
            self::assertSame('Area text', $data['suggestions'][0]['fieldLabel']);

            $response = $this->callProofreaderRoute(
                'kirby-proofreader/areas/proofreader-area/optimize',
                [
                    'preview' => false,
                    'rules'   => ['ellipsis'],
                    'fields'  => ['area_text'],
                ]
            );
            $data = $this->jsonResponse($response);
            $page = $this->kirby->page('proofreader-area-test');
            $language = $this->kirby->language('en') ?? 'default';

            self::assertSame('ok', $data['status']);
            self::assertSame(['area_text'], $data['changedFields']);
            self::assertSame('Area…', $data['diffs']['area_text']['to']);
            self::assertSame('Area…', $page?->version('changes')->content($language)->get('area_text')->value());
        } finally {
            foreach ([$pageChanges, $pageContent, $areaBlueprint] as $fixture) {
                if (is_file($fixture)) {
                    unlink($fixture);
                }
            }

            $changesDir = dirname($pageChanges);
            if (is_dir($changesDir) && count(scandir($changesDir) ?: []) === 2) {
                rmdir($changesDir);
            }

            if (is_dir($pageDir) && count(scandir($pageDir) ?: []) === 2) {
                rmdir($pageDir);
            }

            if (is_link($pluginSymlink)) {
                unlink($pluginSymlink);
            }
        }
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

    public function testEntriesFieldCanReviewPlaygroundContent(): void
    {
        $response = $this->callProofreaderRoute(
            'kirby-proofreader/pages/editorial-review/optimize',
            [
                'preview' => true,
                'rules'   => ['ellipsis', 'dashes'],
                'fields'  => ['milestones'],
            ]
        );
        $data = $this->jsonResponse($response);

        self::assertSame('ok', $data['status']);
        self::assertSame(['milestones'], array_unique(array_column($data['suggestions'], 'field')));
        self::assertContains('ellipsis', array_column($data['suggestions'], 'rule'));
        self::assertContains('dashes', array_column($data['suggestions'], 'rule'));
        self::assertContains('Milestones -> Entry 1', array_column($data['suggestions'], 'pathLabel'));
        self::assertContains('Milestones -> Entry 2', array_column($data['suggestions'], 'pathLabel'));
        self::assertSame(['milestones'], $data['changedFields']);
        self::assertStringContainsString('- Review kickoff…', $data['diffs']['milestones']['to']);
        self::assertStringContainsString('- Range 2020 – 2021', $data['diffs']['milestones']['to']);
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
