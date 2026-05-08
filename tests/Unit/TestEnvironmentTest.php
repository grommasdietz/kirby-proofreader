<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader\Tests\Unit;

use Kirby\Cms\App;
use GrommasDietz\Proofreader\Tests\Support\TestEnvironment;
use GrommasDietz\Proofreader\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TestEnvironmentTest extends TestCase
{
    public function testBootstrapsPlaygroundSite(): void
    {
        $kirby = TestEnvironment::boot();

        $this->assertInstanceOf(App::class, $kirby);
        $this->assertSame('Kirby Playground', $kirby->site()->title()->value());
    }

    public function testAllowsConfigOverrides(): void
    {
        $kirby = TestEnvironment::boot([
            'options' => [
                'debug' => true,
            ],
        ]);

        $this->assertTrue($kirby->option('debug'));
    }
}
