<?php

use Kirby\Cms\App;

$autoload = __DIR__ . '/vendor/autoload.php';

if (is_file($autoload)) {
    require_once $autoload;
}

App::plugin('grommasdietz/proofreader', [
    'options' => [
        'button' => [
            'text' => false,
        ],
        // Controls the enabled rules and their execution order. String entries
        // use built-in rules; keyed arrays can disable, relabel or override.
        // Optional built-ins such as "dimensions" can be added here.
        'rules' => ['unicode', 'ellipsis', 'quotes', 'dashes', 'spaces'],
    ],
    'panel' => [
        'viewButtons' => [
            'proofreader' => function (): array {
                return [
                    'component' => 'k-proofreader-view-button',
                    'props'     => [
                        'showText' => option('grommasdietz.proofreader.button.text', false),
                    ],
                ];
            },
        ],
    ],
    'routes'       => require __DIR__ . '/config/routes.php',
    'translations' => require __DIR__ . '/config/translations.php',
]);
