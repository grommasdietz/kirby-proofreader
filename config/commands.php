<?php

declare(strict_types=1);

use GrommasDietz\Proofreader\Proofreader;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Site;

/**
 * Resolves the language/ruleLanguage pair from a CLI language flag value.
 *
 * @return array{string|object, string|null, string|null}
 */
$resolveCliLanguage = static function (App $kirby, ?string $langCode): array {
    if ($kirby->multilang() === true) {
        $language = $langCode !== null && $langCode !== ''
            ? $kirby->language($langCode) ?? $kirby->defaultLanguage()
            : $kirby->defaultLanguage();
        $ruleLanguage = is_object($language) ? $language->code() : null;
    } else {
        $language     = 'default';
        $ruleLanguage = null;
    }

    $languageCode = is_object($language) ? $language->code() : null;
    $languageCode = is_string($languageCode) && $languageCode !== '' ? $languageCode : null;

    return [$language, $languageCode, $ruleLanguage];
};

/**
 * Applies typography fixes or dry-runs them for a single model.
 *
 * @param  Page|Site             $model
 * @param  array{string|object, string|null, string|null} $languageTuple
 * @param  list<string>|null     $rules
 * @param  bool                  $dryRun
 * @param  bool                  $publish   Write to 'latest' instead of 'changes'
 * @return array{changed:int, suggestions:int, title:string}
 */
$processModel = static function (
    Page|Site $model,
    array $languageTuple,
    ?array $rules,
    bool $dryRun,
    bool $publish
): array {
    [$language, $languageCode, $ruleLanguage] = $languageTuple;

    $changesVersion = $model->version('changes');
    $latestVersion  = $model->version('latest');
    $sourceVersion  = $changesVersion->exists($language) ? $changesVersion : $latestVersion;
    $content        = $sourceVersion->content($language)->toArray();

    /** @var list<string>|null $rules */
    $review = Proofreader::reviewFields(
        $content,
        $model->blueprint()->fields(),
        $rules,
        $ruleLanguage
    );

    $fixed = $review['fixed'];
    $diffs = [];

    foreach ($fixed as $key => $value) {
        $origKey = (string) $key;
        if (isset($content[$origKey]) && $value !== $content[$origKey]) {
            $diffs[$origKey] = ['from' => $content[$origKey], 'to' => $value];
        }
    }

    if (!$dryRun && $diffs !== []) {
        $titleKey = null;

        foreach (array_keys($fixed) as $key) {
            if (strtolower((string) $key) === 'title') {
                $titleKey = (string) $key;
                break;
            }
        }

        $hasTitleChange = $titleKey !== null && array_key_exists($titleKey, $diffs);
        $contentDiffs   = array_filter(
            $diffs,
            static fn (string $key): bool => strtolower($key) !== 'title',
            ARRAY_FILTER_USE_KEY
        );

        if ($hasTitleChange === true && $titleKey !== null) {
            $model          = $model->changeTitle((string) $fixed[$titleKey], $languageCode);
            $changesVersion = $model->version('changes');
        }

        if ($contentDiffs !== []) {
            $targetVersion = $publish ? $latestVersion : $changesVersion;
            $targetVersion->save($fixed, $language);
        }
    }

    $modelTitle = $model instanceof Site
        ? 'site'
        : (string) ($model->title());

    return [
        'changed'     => count($diffs),
        'suggestions' => count($review['suggestions']),
        'title'       => $modelTitle,
    ];
};

/**
 * Collects all pages from a Kirby installation (flat list, all descendants).
 *
 * @return list<Page>
 */
$collectAllPages = static function (App $kirby): array {
    return $kirby->site()->index()->values();
};

/**
 * Collects direct children of a page.
 *
 * @return list<Page>
 */
$collectChildren = static function (Page $page): array {
    return $page->children()->values();
};

/**
 * Collects a page plus all its descendants.
 *
 * @return list<Page>
 */
$collectRecursive = static function (Page $page): array {
    return array_values(
        array_merge([$page], $page->index()->values())
    );
};

$parseCliRules = static function (?string $rulesArg): ?array {
    return $rulesArg !== null
        ? array_values(array_filter(array_map('trim', explode(',', $rulesArg))))
        : null;
};

$optionalCliString = static function (mixed $value): ?string {
    return is_string($value) && $value !== '' ? $value : null;
};

$pageArg = [
    'description' => 'Page ID or path. Omit to target the site model.',
    'required'    => false,
    'defaultValue' => '',
];

$languageArg = [
    'description' => 'Language code for multi-language installs',
    'longPrefix'  => 'language',
];

$rulesArg = [
    'description' => 'Comma-separated rule names to apply',
    'longPrefix'  => 'rules',
];

return [
    // -------------------------------------------------------------------------
    // proofreader:fix
    // -------------------------------------------------------------------------
    'proofreader:fix' => [
        'description' => 'Apply typography fixes to one or more pages (or the site model)',
        'args'        => [
            'page' => $pageArg,
            'all' => [
                'description' => 'Process all pages on the site',
                'longPrefix'  => 'all',
                'noValue'     => true,
            ],
            'children' => [
                'description' => 'Process direct children of the given page',
                'longPrefix'  => 'children',
                'noValue'     => true,
            ],
            'recursive' => [
                'description' => 'Process the given page and all its descendants',
                'longPrefix'  => 'recursive',
                'noValue'     => true,
            ],
            'publish' => [
                'description' => 'Write to the published version instead of changes',
                'longPrefix'  => 'publish',
                'noValue'     => true,
            ],
            'dry-run' => [
                'description' => 'Preview changes without saving',
                'longPrefix'  => 'dry-run',
                'noValue'     => true,
            ],
            'language' => $languageArg,
            'rules' => $rulesArg,
        ],
        /** @param \Kirby\CLI\CLI $cli */
        'command' => static function ($cli) use ($resolveCliLanguage, $processModel, $collectAllPages, $collectChildren, $collectRecursive, $parseCliRules, $optionalCliString): void {
            $kirby = App::instance();

            // --- flags -------------------------------------------------------
            $pageId    = (string) $cli->arg('page');
            $all       = (bool)   ($cli->arg('all')       ?? false);
            $children  = (bool)   ($cli->arg('children')  ?? false);
            $recursive = (bool)   ($cli->arg('recursive') ?? false);
            $publish   = (bool)   ($cli->arg('publish')   ?? false);
            $dryRun    = (bool)   ($cli->arg('dry-run')   ?? false);
            $langCode  = $optionalCliString($cli->arg('language'));
            $rulesArg  = $optionalCliString($cli->arg('rules'));
            $rules     = $parseCliRules($rulesArg);

            if ($dryRun === false) {
                $kirby->impersonate('kirby');
            }

            $languageTuple = $resolveCliLanguage($kirby, $langCode);

            // --- resolve target models ---------------------------------------
            /** @var list<Page|Site> $models */
            $models = [];

            if ($all) {
                $models = $collectAllPages($kirby);
            } elseif ($pageId !== '') {
                $page = $kirby->page($pageId);

                if ($page === null) {
                    $cli->error("Page not found: {$pageId}");
                    return;
                }

                if ($children) {
                    $models = $collectChildren($page);
                } elseif ($recursive) {
                    $models = $collectRecursive($page);
                } else {
                    $models = [$page];
                }
            } else {
                $models = [$kirby->site()];
            }

            if ($models === []) {
                $cli->out('No models to process.');
                return;
            }

            // --- process -----------------------------------------------------
            if ($dryRun) {
                $cli->out('Dry run — no changes will be saved.');
            }

            $totalChanged = 0;
            $totalModels  = 0;

            foreach ($models as $model) {
                $result = $processModel($model, $languageTuple, $rules, $dryRun, $publish);
                $totalModels++;
                $totalChanged += $result['changed'];

                if ($result['changed'] > 0) {
                    $action = $dryRun ? 'would change' : ($publish ? 'fixed (published)' : 'fixed (changes)');
                    $cli->out("  [{$result['title']}] {$action}: {$result['changed']} field(s)");
                }
            }

            $summary = $dryRun
                ? "{$totalChanged} field(s) would change across {$totalModels} model(s)."
                : "{$totalChanged} field(s) fixed across {$totalModels} model(s).";

            $cli->success($summary);
        },
    ],

    // -------------------------------------------------------------------------
    // proofreader:review
    // -------------------------------------------------------------------------
    'proofreader:review' => [
        'description' => 'Preview typography suggestions for one or more pages (or the site model) — read-only',
        'args'        => [
            'page' => $pageArg,
            'all' => [
                'description' => 'Review all pages on the site',
                'longPrefix'  => 'all',
                'noValue'     => true,
            ],
            'children' => [
                'description' => 'Review direct children of the given page',
                'longPrefix'  => 'children',
                'noValue'     => true,
            ],
            'recursive' => [
                'description' => 'Review the given page and all its descendants',
                'longPrefix'  => 'recursive',
                'noValue'     => true,
            ],
            'language' => $languageArg,
            'rules' => $rulesArg,
        ],
        /** @param \Kirby\CLI\CLI $cli */
        'command' => static function ($cli) use ($resolveCliLanguage, $parseCliRules, $optionalCliString, $collectAllPages, $collectChildren, $collectRecursive): void {
            $kirby = App::instance();

            $pageId    = (string) $cli->arg('page');
            $all       = (bool)   ($cli->arg('all')       ?? false);
            $children  = (bool)   ($cli->arg('children')  ?? false);
            $recursive = (bool)   ($cli->arg('recursive') ?? false);
            $langCode  = $optionalCliString($cli->arg('language'));
            $rulesArg  = $optionalCliString($cli->arg('rules'));
            $rules     = $parseCliRules($rulesArg);

            [$language,, $ruleLanguage] = $resolveCliLanguage($kirby, $langCode);

            // --- resolve target models ---------------------------------------
            /** @var list<Page|Site> $models */
            $models  = [];
            $isBatch = $all || $children || $recursive;

            if ($all) {
                $models = $collectAllPages($kirby);
            } elseif ($pageId !== '') {
                $page = $kirby->page($pageId);

                if ($page === null) {
                    $cli->error("Page not found: {$pageId}");
                    return;
                }

                if ($children) {
                    $models = $collectChildren($page);
                } elseif ($recursive) {
                    $models = $collectRecursive($page);
                } else {
                    $models = [$page];
                }
            } else {
                $models = [$kirby->site()];
            }

            if ($models === []) {
                $cli->out('No models to review.');
                return;
            }

            // --- batch output -----------------------------------------------
            if ($isBatch) {
                $totalSuggestions = 0;

                foreach ($models as $model) {
                    $changesVersion = $model->version('changes');
                    $latestVersion  = $model->version('latest');
                    $sourceVersion  = $changesVersion->exists($language) ? $changesVersion : $latestVersion;
                    $content        = $sourceVersion->content($language)->toArray();

                    $review = Proofreader::reviewFields(
                        $content,
                        $model->blueprint()->fields(),
                        $rules,
                        $ruleLanguage
                    );

                    $count = count($review['suggestions']);
                    $totalSuggestions += $count;

                    if ($count === 0) {
                        continue;
                    }

                    $title = $model instanceof Site
                        ? 'site'
                        : (string) $model->title();

                    $cli->out("  [{$title}] {$count} suggestion(s)");

                    foreach ($review['suggestions'] as $suggestion) {
                        $cli->bold("    [{$suggestion['rule']}] {$suggestion['pathLabel']}");
                        $cli->out("      Before: {$suggestion['previewBefore']}");
                        $cli->out("      After:  {$suggestion['previewAfter']}");
                        $cli->br();
                    }
                }

                if ($totalSuggestions === 0) {
                    $cli->success('No suggestions — content looks good.');
                    return;
                }

                $cli->out("{$totalSuggestions} suggestion(s) across " . count($models) . " model(s).");
                return;
            }

            // --- single-model output ----------------------------------------
            $model = $models[0];

            $changesVersion = $model->version('changes');
            $latestVersion  = $model->version('latest');
            $sourceVersion  = $changesVersion->exists($language) ? $changesVersion : $latestVersion;
            $content        = $sourceVersion->content($language)->toArray();

            $review = Proofreader::reviewFields(
                $content,
                $model->blueprint()->fields(),
                $rules,
                $ruleLanguage
            );

            if ($review['suggestions'] === []) {
                $cli->success('No suggestions — content looks good.');
                return;
            }

            $cli->out('Suggestions:');
            $cli->br();

            foreach ($review['suggestions'] as $suggestion) {
                $cli->bold("  [{$suggestion['rule']}] {$suggestion['pathLabel']}");
                $cli->out("    Before: {$suggestion['previewBefore']}");
                $cli->out("    After:  {$suggestion['previewAfter']}");
                $cli->br();
            }

            $count = count($review['suggestions']);
            $cli->out("{$count} suggestion(s) found.");
        },
    ],
];
