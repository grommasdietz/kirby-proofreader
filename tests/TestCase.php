<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader\Tests;

use Kirby\Cms\App;
use GrommasDietz\Proofreader\Tests\Support\TestEnvironment;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case that exposes a helper to boot the Kirby playground.
 */
abstract class TestCase extends BaseTestCase
{
    protected App $kirby;

    /**
     * Boots Kirby for the test suite. Pass overrides to tweak configuration.
     *
     * @param array<string,mixed> $overrides
     * @param array<string,string> $additionalPlugins Optional plugin directory => absolute path map.
     */
    protected function bootKirby(array $overrides = [], array $additionalPlugins = []): App
    {
        $this->kirby = TestEnvironment::boot($overrides, $additionalPlugins);

        return $this->kirby;
    }

    protected function tearDown(): void
    {
        if (isset($this->kirby)) {
            $this->kirby->impersonate(null);
        }

        TestEnvironment::restoreHandlers();

        App::destroy();

        parent::tearDown();
    }
}
