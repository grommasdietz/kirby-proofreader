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
        'rules' => ['unicode', 'ellipsis', 'quotes', 'apostrophes', 'dashes', 'spaces'],
        // Controls additional or skipped field coverage by field name or type.
        // Excludes override default coverage and configured includes.
        'fields' => [
            'include' => [],
            'exclude' => [],
        ],
        // Protects matched spans from all rule processing. Built-in presets
        // are enabled by key with true; custom regex strings are accepted too.
        // Example: ['phone' => true, 'myCode' => '/\bSKU-\d+/u']
        'protect' => [],
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
    'commands'     => require __DIR__ . '/config/commands.php',
]);
