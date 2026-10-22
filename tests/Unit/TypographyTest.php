<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader\Tests\Unit;

use GrommasDietz\Proofreader\Proofreader;
use GrommasDietz\Proofreader\Tests\TestCase;
use Kirby\Cms\Language;
use PHPUnit\Framework\Attributes\DataProvider;

final class TypographyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootKirby();
    }

    #[DataProvider('quotationExamples')]
    public function testQuotationContext(string $input, string $expected, string $language): void
    {
        self::assertSame($expected, Proofreader::fixHtml($input, ['quotes', 'apostrophes'], $language));
        self::assertSame($expected, Proofreader::fixHtml($expected, ['quotes', 'apostrophes'], $language));
    }

    public static function quotationExamples(): array
    {
        return [
            'inline emphasis' => ['<p>"Hello <em>world</em>"</p>', '<p>“Hello <em>world</em>”</p>', 'en'],
            'nested quotation' => ['<p>"It\'s <em>\'ready\'</em>"</p>', '<p>“It’s <em>‘ready’</em>”</p>', 'en'],
            'quoted contraction' => ["'It's ready'", '‘It’s ready’', 'en'],
            'attributes intact' => ['<p data-title="a > b">"<a href="page://abc" title="Leave...">Hello</a>"</p>', '<p data-title="a > b">“<a href="page://abc" title="Leave...">Hello</a>”</p>', 'en'],
            'entities' => ['<p>&quot;Hello <em>world</em>&quot; &amp; &#39;ready&#39;</p>', '<p>“Hello <em>world</em>” &amp; ‘ready’</p>', 'en'],
            'language inheritance' => ['<section lang="de"><p>"Hallo" <span lang="en">"Hello"</span> "Welt"</p></section>', '<section lang="de"><p>„Hallo“ <span lang="en">“Hello”</span> „Welt“</p></section>', 'fr'],
            'single-quoted lang' => ["<p lang='en'>\"Hello\"</p>", "<p lang='en'>“Hello”</p>", 'de'],
            'unquoted lang' => ['<p lang=en>"Hello"</p>', '<p lang=en>“Hello”</p>', 'de'],
            'empty lang resets inheritance' => ['<p lang="">"Hello"</p>', '<p lang="">"Hello"</p>', 'de'],
            'unmatched blocks' => ['<p>"Hello</p><p>world"</p>', '<p>"Hello</p><p>world"</p>', 'en'],
            'code boundary' => ['<p>"Hello <code>"code"</code>world"</p>', '<p>"Hello <code>"code"</code>world"</p>', 'en'],
            'unmatched quote' => ['He said "hello', 'He said "hello', 'en'],
            'measurements unchanged without primes' => ['6\' 2" and 12" wide', '6\' 2" and 12" wide', 'en'],
            'leading English omissions' => ["'tis the '90s", '’tis the ’90s', 'en'],
            'German omission' => ["So 'nen Mist!", 'So ’nen Mist!', 'de'],
            'rock and roll' => ["Rock 'n' Roll", 'Rock ’n’ Roll', 'en'],
            'quoted omission word' => ["The word 'tis'", 'The word ‘tis’', 'en'],
            'quoted number' => ["The number '90'", 'The number ‘90’', 'en'],
            'encoded apostrophe' => ['<p>It&#39;s ready</p>', '<p>It’s ready</p>', 'en'],
            'attribute values are not language attributes' => ['<p title=\'A lang="de" example\'>"Hello"</p>', '<p title=\'A lang="de" example\'>“Hello”</p>', 'en'],
        ];
    }

    public function testRegionalTypographyUsesKirbyDefinitionsAndLocaleAliases(): void
    {
        $this->bootKirby(['options' => ['smartypants' => ['doublequote.open' => '[', 'doublequote.close' => ']']]]);
        $this->kirby->languages(false)->append(new Language([
            'code' => 'de-ch',
            'locale' => 'de_CH.UTF-8',
            'smartypants' => [
                'doublequote.open' => '&laquo;', 'doublequote.close' => '&raquo;',
                'singlequote.open' => '&lsaquo;', 'singlequote.close' => '&rsaquo;',
            ],
        ]));

        self::assertSame('«Hallo»', Proofreader::fixQuotePairs('"Hallo"', 'de_CH'));
        self::assertSame('<p lang="de-CH">«Hallo»</p>', Proofreader::fixHtml('<p lang="de-CH">"Hallo"</p>', ['quotes'], 'en'));
        // No plugin-owned regional table: an unconfigured variant inherits de.
        self::assertSame('„Hallo“', Proofreader::fixQuotePairs('"Hallo"', 'de-AT'));
        // Native locale aliases work even when the language code is only en.
        self::assertSame('“Hello”', Proofreader::fixQuotePairs('"Hello"', 'en-US'));
    }

    public function testFrenchSpacingUsesNativeLanguageOptionsIncludingEmptyStrings(): void
    {
        self::assertSame("Bonjour\u{202F}!", Proofreader::fixPunctuationSpacing('Bonjour !', 'fr-FR'));
        $this->kirby->languages(false)->append(new Language([
            'code' => 'fr',
            'smartypants' => ['space.colon' => '&nbsp;', 'space.semicolon' => '&#8239;', 'space.marks' => ''],
        ]));

        $input = '<p lang="fr">Bonjour : oui ; merci !</p><p lang="en">Hello !</p>';
        self::assertSame("<p lang=\"fr\">Bonjour\u{00A0}: oui\u{202F};\u{00A0}merci!</p><p lang=\"en\">Hello!</p>", Proofreader::fixHtml($input, ['spaces'], 'en'));
        self::assertSame('Bonjour!', Proofreader::fixPunctuationSpacing('Bonjour!', 'fr'));
    }

    #[DataProvider('optionalExamples')]
    public function testOptionalContentRules(string $rule, string $input, string $expected, ?string $language = null): void
    {
        $this->bootKirby(['options' => ['grommasdietz.proofreader.rules' => [$rule]]]);
        self::assertSame($expected, Proofreader::fix($input, [$rule], $language));
        self::assertSame($expected, Proofreader::fix($expected, [$rule], $language));
        self::assertSame('<p>' . $expected . '</p>', Proofreader::fixHtml('<p>' . $input . '</p>', [$rule], $language));
    }

    public static function optionalExamples(): array
    {
        return [
            'known abbreviations' => ['abbreviations', 'Das gilt z. B. i.d.R. und u. a. m.', "Das gilt z.\u{202F}B. i.\u{202F}d.\u{202F}R. und u.\u{202F}a.\u{202F}m.", 'de'],
            'initials and identifiers' => ['abbreviations', 'A. B. Test https://z.B.de x@z.B.de SKU-z.B.17', 'A. B. Test https://z.B.de x@z.B.de SKU-z.B.17', 'de'],
            'other languages unchanged' => ['abbreviations', 'Use z. B. here', 'Use z. B. here', 'en'],
            'imperial measurements' => ['primes', '6\' 2" and 18\'\'', '6′ 2″ and 18″'],
            'coordinates' => ['primes', '118° 19\' 43.5"', '118° 19′ 43.5″'],
            'encoded measurement marks' => ['primes', '6&#39; 2&quot;', '6′ 2″'],
            'quoted numeric content' => ['primes', '"Chapter 12" and \'12\' and \'90s', '"Chapter 12" and \'12\' and \'90s'],
            'currency suffix' => ['currencies', '20€ and 12,50 €', "20\u{202F}€ and 12,50\u{202F}€"],
            'currency prefix unchanged' => ['currencies', '$10 and £20', '$10 and £20'],
            'existing prefix space' => ['currencies', '$ 10', "$\u{202F}10"],
            'encoded currency symbols' => ['currencies', '20&euro; and &pound; 10', "20\u{202F}&euro; and &pound;\u{202F}10"],
            'other entities preserved' => ['currencies', '20&deg; &copy; 2026', '20&deg; &copy; 2026'],
            'symbols' => ['symbols', 'Brand(tm) Label (r) (c) 2026', "Brand™ Label® ©\u{00A0}2026"],
            'enumeration labels' => ['symbols', "(c) third item\n(r) next item", "(c) third item\n(r) next item"],
        ];
    }

    public function testSpacesBindShortWordsAtRealHtmlParagraphEnds(): void
    {
        self::assertSame("A useful final\u{00A0}word", Proofreader::fix('A useful final word', ['spaces']));
        $input = '<p>A useful <strong>final</strong> <em>word</em>.</p><p>Final output</p><p>Text here <code>code</code></p>';
        $expected = "<p>A useful <strong>final</strong>\u{00A0}<em>word</em>.</p><p>Final output</p><p>Text here <code>code</code></p>";
        self::assertSame($expected, Proofreader::fixHtml($input, ['spaces']));
        self::assertSame($expected, Proofreader::fixHtml($expected, ['spaces']));
        self::assertSame('<p>Final <em>out</em>put</p>', Proofreader::fixHtml('<p>Final <em>out</em>put</p>', ['spaces']));
        self::assertSame('<p title="a > b">Text longer</p>', Proofreader::fixHtml('<p title="a > b"> Text longer </p>', ['spaces']));
    }

    public function testSpacesUseFiveLetterLimitAndKeepExistingExclusions(): void
    {
        self::assertSame("Final\u{00A0}title", Proofreader::fix('Final title', ['spaces']));
        self::assertSame('Final output', Proofreader::fix('Final output', ['spaces']));
        self::assertSame('Variable x', Proofreader::fix('Variable x', ['spaces']));
        self::assertSame('Done for and be', Proofreader::fix('Done for and be', ['spaces']));
        self::assertSame("Text über\u{00A0}Öl", Proofreader::fix('Text über Öl', ['spaces']));
        self::assertSame("Text\u{00A0}here\nMore\u{00A0}words", Proofreader::fix("Text here\nMore words", ['spaces']));
    }

    public function testProtectPatternsAndCodeRemainIntactWithOptionalRules(): void
    {
        $rules = ['primes', 'quotes', 'apostrophes', 'abbreviations', 'currencies', 'symbols', 'spaces'];
        $this->bootKirby(['options' => ['grommasdietz.proofreader' => [
            'rules' => $rules,
            'protect' => ['literal' => '~\[\[.*?\]\]~u'],
        ]]]);
        $literal = '[["SKU 12" 20€ z. B.]]';
        $code = '<code>"Code" 12" 20€ z. B.</code>';
        self::assertSame($literal, Proofreader::fix($literal, null, 'de'));
        self::assertSame('<p>' . $literal . '</p>' . $code, Proofreader::fixHtml('<p>' . $literal . '</p>' . $code, null, 'de'));
    }

    public function testReviewUsesTheSameRuleOrderAndResultsAsFixingHtml(): void
    {
        $rules = ['primes', 'quotes', 'symbols', 'spaces'];
        $this->bootKirby(['options' => ['grommasdietz.proofreader.rules' => $rules]]);
        $review = Proofreader::reviewFields(
            ['body' => '<p>"A <em>title</em>" Brand(tm) 12" wide</p>'],
            ['body' => ['type' => 'writer']],
            null,
            'en'
        );
        self::assertSame($rules, array_column($review['suggestions'], 'rule'));
        self::assertSame("<p>“A <em>title</em>” Brand™ 12″\u{00A0}wide</p>", $review['fixed']['body']);
    }
}
