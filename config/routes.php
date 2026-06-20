<?php

declare(strict_types=1);

use GrommasDietz\Proofreader\Proofreader;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Find;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Cms\User;
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
 * @return array{rules:list<string>, suggestions:list<array<string, string>>, fixed:array<string, mixed>}
 */
$reviewContent = static function (
    array $content,
    array $blueprintFields,
    array $body,
    ?string $ruleLanguage
): array {
    $rules = is_array($body['rules'] ?? null)
        ? array_values(array_filter($body['rules'], 'is_string'))
        : null;
    $fields = is_array($body['fields'] ?? null)
        ? array_values(array_filter($body['fields'], 'is_string'))
        : null;

    return Proofreader::reviewFields(
        $content,
        $blueprintFields,
        $rules,
        $ruleLanguage,
        $fields
    );
};

/**
 * @param array<string, mixed> $content
 * @param array<string, mixed> $fixed
 * @return array<string, array{from:mixed,to:mixed}>
 */
$diffFields = static function (array $content, array $fixed): array {
    $diffs = [];

    foreach ($fixed as $key => $value) {
        $origKey = (string) $key;
        if (isset($content[$origKey]) && $value !== $content[$origKey]) {
            $diffs[$origKey] = ['from' => $content[$origKey], 'to' => $value];
        }
    }

    return $diffs;
};

/**
 * @param Page|Site|File|User $model
 */
$optimizeModel = static function (Page|Site|File|User $model) use (
    $resolveLanguage,
    $reviewContent,
    $diffFields
): Response {
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

    $review = $reviewContent($content, $model->blueprint()->fields(), $body, $ruleLanguage);
    $fixed = $review['fixed'];
    $diffs = $diffFields($content, $fixed);
    $preview = ($body['preview'] ?? false) === true;

    if ($preview === false) {
        $titleKey = null;

        foreach (array_keys($fixed) as $key) {
            if (strtolower((string) $key) === 'title') {
                $titleKey = (string) $key;
                break;
            }
        }

        $hasTitleChange = ($model instanceof Page || $model instanceof Site) &&
            $titleKey !== null &&
            array_key_exists($titleKey, $diffs);
        $contentDiffs   = $hasTitleChange === true
            ? array_filter(
                $diffs,
                static fn (string $key): bool => strtolower($key) !== 'title',
                ARRAY_FILTER_USE_KEY
            )
            : $diffs;

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
    $panelPath = rawurldecode(str_replace('+', '/', $encodedPath));
    $kirby = App::instance();

    try {
        if (preg_match('#^(?:site/)?files/(.+)$#', $panelPath, $m) === 1) {
            return $kirby->site()->file($m[1]);
        }

        if (preg_match('#^pages/(.+)/files/(.+)$#', $panelPath, $m) === 1) {
            return $kirby->page($m[1])?->file($m[2]);
        }

        if (preg_match('#^users/([^/]+)/files/(.+)$#', $panelPath, $m) === 1) {
            return $kirby->user($m[1])?->file($m[2]);
        }

        if (preg_match('#^account/files/(.+)$#', $panelPath, $m) === 1) {
            return $kirby->user()?->file($m[1]);
        }
    } catch (\Throwable) {
        return null;
    }

    return null;
};

/**
 * @return array<string, array<string, mixed>>
 */
$collectAreaFields = static function (array $layout): array {
    $fields = [];
    $tabs = $layout['tabs'] ?? [];

    if (is_array($tabs) === false) {
        return [];
    }

    foreach ($tabs as $tab) {
        if (is_array($tab) === false) {
            continue;
        }

        $columns = $tab['columns'] ?? [];
        if (is_array($columns) === false) {
            continue;
        }

        foreach ($columns as $column) {
            if (is_array($column) === false) {
                continue;
            }

            $sections = $column['sections'] ?? [];
            if (is_array($sections) === false) {
                continue;
            }

            foreach ($sections as $section) {
                if (is_array($section) === false || ($section['type'] ?? null) !== 'fields') {
                    continue;
                }

                $sectionFields = $section['rawFields'] ?? $section['fields'] ?? [];
                if (is_array($sectionFields) === false) {
                    continue;
                }

                foreach ($sectionFields as $fieldName => $field) {
                    if (is_array($field) === false) {
                        continue;
                    }

                    $normalized = strtolower((string)$fieldName);
                    $field['name'] = $normalized;
                    $fields[$normalized] = $field;
                }
            }
        }
    }

    return $fields;
};

$optimizeArea = static function (string $areaName) use (
    $resolveLanguage,
    $collectAreaFields,
    $reviewContent,
    $diffFields
): Response {
    $kirby = App::instance();
    $body  = $kirby->request()->body()->toArray();
    $areaClass = 'GrommasDietz\\Areas\\BlueprintAreas';

    if ($kirby->user() === null) {
        return Response::json(
            ['status' => 'error', 'message' => 'Unauthorized'],
            401
        );
    }

    if (
        class_exists($areaClass) === false ||
        method_exists($areaClass, 'view') === false ||
        method_exists($areaClass, 'draft') === false
    ) {
        return Response::json(
            ['status' => 'error', 'message' => 'Blueprint Areas not available'],
            404
        );
    }

    [$language, $languageCode, $ruleLanguage] = $resolveLanguage($kirby);

    if (is_string($languageCode)) {
        $kirby->setCurrentLanguage($languageCode);
        $kirby->setCurrentTranslation($languageCode);
    }

    /** @var array<string, mixed> $view */
    $view = \call_user_func([$areaClass, 'view'], rawurldecode($areaName));
    $content = is_array($view['values'] ?? null) ? $view['values'] : [];
    $layout = is_array($view['layout'] ?? null) ? $view['layout'] : [];
    $blueprintFields = $collectAreaFields($layout);

    $review = $reviewContent($content, $blueprintFields, $body, $ruleLanguage);
    $fixed = $review['fixed'];
    $diffs = $diffFields($content, $fixed);
    $preview = ($body['preview'] ?? false) === true;

    if ($preview === false && $diffs !== []) {
        \call_user_func([$areaClass, 'draft'], rawurldecode($areaName), $fixed);
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
        'pattern' => 'kirby-proofreader/users/(:any)/optimize',
        'method'  => 'POST',
        'action'  => function (string $encodedId) use ($optimizeModel): Response {
            try {
                $user = Find::user(rawurldecode($encodedId));
            } catch (\Throwable) {
                return Response::json(
                    ['status' => 'error', 'message' => 'User not found'],
                    404
                );
            }

            return $optimizeModel($user);
        },
    ],
    [
        'pattern' => 'kirby-proofreader/account/optimize',
        'method'  => 'POST',
        'action'  => function () use ($optimizeModel): Response {
            try {
                $user = Find::user('account');
            } catch (\Throwable) {
                return Response::json(
                    ['status' => 'error', 'message' => 'User not found'],
                    404
                );
            }

            return $optimizeModel($user);
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
    [
        'pattern' => 'kirby-proofreader/areas/(:any)/optimize',
        'method'  => 'POST',
        'action'  => function (string $areaName) use ($optimizeArea): Response {
            return $optimizeArea($areaName);
        },
    ],
];
