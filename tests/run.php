#!/usr/bin/env php
<?php

declare(strict_types=1);

$autoload = __DIR__ . '/../playground/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(
        STDERR,
        "Playground dependencies are missing. Run composer install -d playground.\n"
    );
    exit(1);
}

$loader = require $autoload;

if ($loader instanceof Composer\Autoload\ClassLoader) {
    $loader->setPsr4('GrommasDietz\\Proofreader\\', []);
}

require __DIR__ . '/Support/TestEnvironment.php';

use GrommasDietz\Proofreader\Tests\Support\TestEnvironment;

try {
    $kirby = TestEnvironment::boot();

    if ($kirby->plugin('grommasdietz/proofreader') === null) {
        throw new RuntimeException('Plugin registration failed.');
    }

    if ($kirby->page('home')?->id() !== 'home') {
        throw new RuntimeException('Playground home page could not be loaded.');
    }

    fwrite(STDOUT, "Runtime smoke test passed.\n");
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        'Runtime smoke test failed: ' . $exception->getMessage() . PHP_EOL
    );
    exit(1);
} finally {
    TestEnvironment::cleanup();
}
