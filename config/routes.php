<?php

declare(strict_types=1);

use GrommasDietz\Proofreader\Proofreader;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Find;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Http\Response;

$resolveLanguage = static function (App $kirby): array {
    $langCode = $kirby->request()->header('x-language');

    if ($kirby->multilang() === true && $langCode !== null && $langCode !== '') {
        $language = $kirby->language($langCode) ?? $kirby->defaultLanguage();
        $ruleLanguage = is_string($language) ? null : $language->code();
    } else {
        $language = 'default';
        $ruleLanguage = null;
    }

    $languageCode = is_string($language) ? null : $language->code();
    $languageCode = is_string($languageCode) && $languageCode !== '' ? $languageCode : null;

    return [$language, $languageCode, $ruleLanguage];
};

/**
 * @param Page|Site|File $model
 */
$optimizeModel = static function (Page|Site|File $model) use ($resolveLanguage): Response {
    $kirby = App::instance();
    $body  = $kirby->request()->body()->toArray();

    if ($kirby->user() === null) {
        return Response::json(
            ['status' => 'error', 'message' => 'Unauthorized'],
            401
        );
    }

    [$language, $languageCode, $ruleLanguage] = $resolveLanguage($kirby);

    $changesVersion = $model->version('changes');
    $latestVersion  = $model->version('latest');
    $sourceVersion  = $changesVersion->exists($language) ? $changesVersion : $latestVersion;
    $content        = $sourceVersion->content($language)->toArray();

    $rules = is_array($body['rules'] ?? null)
        ? array_values(array_filter($body['rules'], 'is_string'))
        : null;
    $fields = is_array($body['fields'] ?? null)
        ? array_values(array_filter($body['fields'], 'is_string'))
        : null;

    $review = Proofreader::reviewFields(
        $content,
        $model->blueprint()->fields(),
        $rules,
        $ruleLanguage,
        $fields
    );
    $fixed = $review['fixed'];

    $diffs = [];
    foreach ($fixed as $key => $value) {
        $origKey = (string) $key;
        if (isset($content[$origKey]) && $value !== $content[$origKey]) {
            $diffs[$origKey] = ['from' => $content[$origKey], 'to' => $value];
        }
    }

    $preview = ($body['preview'] ?? false) === true;

    if ($preview === false) {
        $titleKey = null;

        foreach (array_keys($fixed) as $key) {
            if (strtolower((string) $key) === 'title') {
                $titleKey = (string) $key;
                break;
            }
        }

        $hasTitleChange = $model instanceof File === false &&
            $titleKey !== null &&
            array_key_exists($titleKey, $diffs);
        $contentDiffs   = $model instanceof File
            ? $diffs
            : array_filter(
                $diffs,
                static fn (string $key): bool => strtolower($key) !== 'title',
                ARRAY_FILTER_USE_KEY
            );

        // Titles are stored through Kirby's native action so permissions,
        // hooks and existing changes-version syncing still apply.
        if ($hasTitleChange === true && $titleKey !== null) {
            $model = $model->changeTitle((string) $fixed[$titleKey], $languageCode);
            $changesVersion = $model->version('changes');
        }

        if ($contentDiffs !== []) {
            $changesVersion->save($fixed, $language);
        }
    }

    return Response::json([
        'status'        => 'ok',
        'preview'       => $preview,
        'rules'         => $review['rules'],
        'availableRules' => Proofreader::rulesForPanel(),
        'suggestions'   => $review['suggestions'],
        'changedFields' => array_keys($diffs),
        'diffs'         => $diffs,
    ]);
};

$resolveFile = static function (string $encodedPath): ?File {
    $panelPath = str_replace('+', '/', $encodedPath);

    try {
        if (str_starts_with($panelPath, 'files/')) {
            return Find::file('', substr($panelPath, 6));
        }

        if (preg_match('#^(.+)/files/(.+)$#', $panelPath, $m) === 1) {
            return Find::file($m[1], $m[2]);
        }
    } catch (\Throwable) {
        return null;
    }

    return null;
};

return [
    [
        'pattern' => 'kirby-proofreader/site/optimize',
        'method'  => 'POST',
        'action'  => function () use ($optimizeModel): Response {
            return $optimizeModel(App::instance()->site());
        },
    ],
    [
        'pattern' => 'kirby-proofreader/pages/(:any)/optimize',
        'method'  => 'POST',
        'action'  => function (string $encodedId) use ($optimizeModel): Response {
            $kirby = App::instance();

            // Panel encodes path separators as '+' in IDs
            $pageId = str_replace('+', '/', $encodedId);
            $page   = $kirby->page($pageId);

            if ($page === null) {
                return Response::json(
                    ['status' => 'error', 'message' => 'Page not found'],
                    404
                );
            }

            return $optimizeModel($page);
        },
    ],
    [
        'pattern' => 'kirby-proofreader/files/(:any)/optimize',
        'method'  => 'POST',
        'action'  => function (string $encodedPath) use ($optimizeModel, $resolveFile): Response {
            $file = $resolveFile($encodedPath);

            if ($file === null) {
                return Response::json(
                    ['status' => 'error', 'message' => 'File not found'],
                    404
                );
            }

            return $optimizeModel($file);
        },
    ],
];
