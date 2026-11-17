<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader\Tests\Unit;

use GrommasDietz\Proofreader\Proofreader;
use GrommasDietz\Proofreader\Tests\TestCase;
use Kirby\Cms\Language;
use PHPUnit\Framework\Attributes\DataProvider;

final class CurrenciesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootKirby(['options' => ['grommasdietz.proofreader.rules' => ['currencies']]]);
    }

    #[DataProvider('prices')]
    public function testExplicitPrices(string $input, string $expected, ?string $language): void
    {
        self::assertSame($expected, Proofreader::fix($input, ['currencies'], $language));
        self::assertSame($expected, Proofreader::fix($expected, ['currencies'], $language));
    }

    public static function prices(): array
    {
        return [
            ['12.50€', "12,50\u{202F}€", 'de'],
            ['12,50 €', "12.50\u{202F}€", 'en'],
            ['12.5 EUR', "12,5\u{202F}EUR", 'de-DE'],
            ['1,234.56 €', "1.234,56\u{202F}€", 'de'],
            ['1.234,56 EUR', "1,234.56\u{202F}EUR", 'en-GB'],
            ['1,234,567.89€', "1.234.567,89\u{202F}€", 'de'],
            ['-12.50 €', "-12,50\u{202F}€", 'de'],
            ['$12,50', '$12.50', 'en'],
            ['$ 12,50', "$\u{202F}12.50", 'en'],
            ['EUR 12.50', "EUR\u{202F}12,50", 'de'],
            ['12.50&#x20ac;', "12,50\u{202F}&#x20ac;", 'de'],
            ['12.50&nbsp;&euro;', "12,50\u{202F}&euro;", 'de'],
            ['&amp; 12.50 €', "&amp; 12,50\u{202F}€", 'de'],
            ['12,50 €', "12,50\u{202F}€", null],
            ['12.50 €', "12.50\u{202F}€", 'fr'],
            ['1.234 €', "1.234\u{202F}€", 'en'],
            ['1.23.45 €', "1.23.45\u{202F}€", 'de'],
            ['17.11.2026 2026-11-17 12.50 1,234 20&deg;', '17.11.2026 2026-11-17 12.50 1,234 20&deg;', 'de'],
            ['SKU-12.50€ https://example.com/12.50€', 'SKU-12.50€ https://example.com/12.50€', 'de'],
        ];
    }

    public function testWriterMarkupRequiresAllowedMarkAndReviewMatchesApply(): void
    {
        $fields = [
            'marked' => ['type' => 'writer', 'marks' => ['italic', 'nobreak']],
            'unmarked' => ['type' => 'writer', 'marks' => ['italic']],
            'plain' => ['type' => 'text'],
            'price' => ['type' => 'number'],
        ];
        $input = ['marked' => '<p>12.50 €</p>', 'unmarked' => '<p>12.50 €</p>', 'plain' => '12.50 €', 'price' => '12.50'];
        $expected = [
            'marked' => "<p><span class=\"no-break\">12,50\u{200A}€</span></p>",
            'unmarked' => "<p>12,50\u{202F}€</p>",
            'plain' => "12,50\u{202F}€",
            'price' => '12.50',
        ];
        $review = Proofreader::reviewFields($input, $fields, null, 'de');
        self::assertSame($expected, $review['fixed']);
        self::assertCount(3, $review['suggestions']);
        self::assertSame($expected, Proofreader::fixFields($input, $fields, null, 'de'));
        self::assertSame([], Proofreader::reviewFields($expected, $fields, null, 'de')['suggestions']);
    }

    public function testInlineMarkupLanguagesAndExistingMarksStayIntact(): void
    {
        $input = '<p title="12.50 €"><span class="no-break" lang="de"><em>12.50 €</em></span> <a href="page://price">12,50 €</a></p><code>12.50 €</code>';
        $expected = "<p title=\"12.50 €\"><span class=\"no-break\" lang=\"de\"><em>12,50\u{200A}€</em></span> <a href=\"page://price\"><span class=\"no-break\">12.50\u{200A}€</span></a></p><code>12.50 €</code>";
        self::assertSame($expected, Proofreader::fixHtml($input, null, 'en', true));
        self::assertSame($expected, Proofreader::fixHtml($expected, null, 'en', true));
        self::assertSame("<p><em>12,50</em>\u{202F}€</p>", Proofreader::fixHtml('<p><em>12.50</em> €</p>', null, 'de', true));
        self::assertSame("<p>1<em>.234</em>,56\u{202F}€</p>", Proofreader::fixHtml('<p>1<em>,234</em>.56 €</p>', null, 'de', true));
        self::assertSame('<p>12.50<span lang="de"> €</span></p>', Proofreader::fixHtml('<p>12.50<span lang="de"> €</span></p>', null, 'en', true));
        self::assertSame('<p>12.50<br>€</p>', Proofreader::fixHtml('<p>12.50<br>€</p>', null, 'de', true));
    }

    public function testLegacyUnitSpacingIsInheritedAndPlainTextCannotGainBreakableHairSpaces(): void
    {
        $this->bootKirby(['options' => ['smartypants' => ['space.unit' => '&nbsp;']]]);
        self::assertSame("12,50\u{00A0}€", Proofreader::fixCurrencies('12.50 €', 'de'));
        $this->bootKirby(['options' => ['smartypants' => ['space.currency' => '&hairsp;']]]);
        self::assertSame("12,50\u{202F}€", Proofreader::fixCurrencies('12.50 €', 'de'));
    }

    public function testRegionalOptionsAndProtection(): void
    {
        $this->bootKirby(['options' => ['grommasdietz.proofreader' => [
            'rules' => ['currencies'], 'protect' => ['literal' => '~\[\[.*?\]\]~u'],
        ]]]);
        $this->kirby->languages(false)->append(new Language([
            'code' => 'de-ch', 'smartypants' => ['decimal.currency' => '.', 'space.currency' => '&thinsp;'],
        ]));
        self::assertSame("<p><span class=\"no-break\">12.50\u{2009}€</span></p>", Proofreader::fixHtml('<p>12,50 €</p>', null, 'de-CH', true));
        self::assertSame('[[12.50 €]]', Proofreader::fix('[[12.50 €]]', null, 'de'));
        self::assertSame('<p>[[<em>12.50</em> €]]</p>', Proofreader::fixHtml('<p>[[<em>12.50</em> €]]</p>', null, 'de', true));
    }
}
