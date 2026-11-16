<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader\Tests\Unit;

use GrommasDietz\Proofreader\Proofreader;
use GrommasDietz\Proofreader\Tests\TestCase;
use Kirby\Cms\Language;

final class FrenchExtensionExampleTest extends TestCase
{
    private function bootExample(array $spacing): void
    {
        $this->bootKirby(['options' => ['grommasdietz.proofreader' => [
            'rules' => ['spaces', 'regionalFrench' => require dirname(__DIR__, 2) . '/docs/examples/french-punctuation.php'],
            'protect' => ['url' => '~\bhttps?://[^\s<>]+~u'],
        ]]]);
        $this->kirby->languages(false)->append(new Language([
            'code' => 'fr-fr', 'locale' => 'fr_FR', 'smartypants' => $spacing,
        ]));
    }

    public function testExplicitRegionalProfileEnablesConservativeInsertion(): void
    {
        $this->bootExample(['space.colon' => '&nbsp;', 'space.semicolon' => '&#8239;', 'space.marks' => '&#8239;']);
        $input = '<p>Attention: merci! https://example.com! 12:30</p><code>Bonjour!</code><p lang="en">Hello!</p>';
        $expected = "<p>Attention\u{00A0}: merci\u{202F}! https://example.com! 12:30</p><code>Bonjour!</code><p lang=\"en\">Hello!</p>";
        self::assertSame($expected, Proofreader::fixHtml($input, null, 'fr-FR'));
        self::assertSame($expected, Proofreader::fixHtml($expected, null, 'fr-FR'));
    }

    public function testUnspacedRegionalChoicesAndUnconfiguredLanguagesStayUnchanged(): void
    {
        $this->bootExample(['space.colon' => '&nbsp;', 'space.semicolon' => '', 'space.marks' => '']);
        self::assertSame("Bonjour!\u{00A0}Merci;", Proofreader::fix('Bonjour! Merci;', null, 'fr-FR'));
        self::assertSame('Bonjour!', Proofreader::fix('Bonjour!', null, 'fr-CA'));
        self::assertSame('Hello!', Proofreader::fix('Hello!', null, 'en'));
    }
}
