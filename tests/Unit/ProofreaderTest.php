<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader\Tests\Unit;

use GrommasDietz\Proofreader\Proofreader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProofreaderTest extends TestCase
{
    // -------------------------------------------------------------------------
    // fixUnicodeComposition
    // -------------------------------------------------------------------------

    /**
     * @dataProvider unicodeCompositionProvider
     */
    #[DataProvider('unicodeCompositionProvider')]
    public function testFixUnicodeComposition(string $input, string $expected): void
    {
        $this->assertSame($expected, Proofreader::fixUnicodeComposition($input));
    }

    public static function unicodeCompositionProvider(): array
    {
        return [
            'lowercase diaeresis' => ["a\u{0308}", 'ä'],
            'uppercase diaeresis' => ["U\u{0308}ber", 'Über'],
            'acute accent'        => ["Cafe\u{0301}", 'Café'],
            'already composed'    => ['Mädchen', 'Mädchen'],
            'plain text'          => ['Editorial text', 'Editorial text'],
        ];
    }

    // -------------------------------------------------------------------------
    // fixEllipsis
    // -------------------------------------------------------------------------

    /**
     * @dataProvider ellipsisProvider
     */
    #[DataProvider('ellipsisProvider')]
    public function testFixEllipsis(string $input, string $expected): void
    {
        $this->assertSame($expected, Proofreader::fixEllipsis($input));
    }

    public static function ellipsisProvider(): array
    {
        return [
            'three dots'              => ['Hello...', 'Hello…'],
            'four dots'               => ['Hello....', 'Hello…'],
            'six dots'                => ['Hello......', 'Hello…'],
            'space before dots'       => ['Hello ...', 'Hello…'],
            'tab before dots'         => ["Hello\t...", 'Hello…'],
            'dots at start'           => ['...hello', '…hello'],
            'dots alone'              => ['...', '…'],
            'two dots unchanged'      => ['Hello..', 'Hello..'],
            'one dot unchanged'       => ['Hello.', 'Hello.'],
            'multiple occurrences'    => ['a... b...', 'a… b…'],
        ];
    }

    // -------------------------------------------------------------------------
    // fixDashes
    // -------------------------------------------------------------------------

    /**
     * @dataProvider dashProvider
     */
    #[DataProvider('dashProvider')]
    public function testFixDashes(string $input, string $expected): void
    {
        $this->assertSame($expected, Proofreader::fixDashes($input));
    }

    public static function dashProvider(): array
    {
        $rangeSpace = "\u{2006}";
        $dashSpace  = "\u{200A}";

        return [
            'numeric range hyphen'    => ['2010 - 2020', "2010{$rangeSpace}–{$rangeSpace}2020"],
            'numeric range compact hyphen' => ['2010-2020', "2010{$rangeSpace}–{$rangeSpace}2020"],
            'numeric range en-dash'   => ['2010 – 2020', "2010{$rangeSpace}–{$rangeSpace}2020"],
            'numeric range compact en-dash' => ['2010–2020', "2010{$rangeSpace}–{$rangeSpace}2020"],
            'numeric range em-dash'   => ['2010 — 2020', "2010{$rangeSpace}–{$rangeSpace}2020"],
            'word context hyphen'     => ['draft - review', "draft{$dashSpace}—{$dashSpace}review"],
            'word context compact em-dash' => ['draft—review', "draft{$dashSpace}—{$dashSpace}review"],
            'word context en-dash'    => ['draft – review', "draft{$rangeSpace}–{$rangeSpace}review"],
            'word context em-dash'    => ['draft — review', "draft{$dashSpace}—{$dashSpace}review"],
            'intended place range en-dash' => ['Hamburg – Bremen', "Hamburg{$rangeSpace}–{$rangeSpace}Bremen"],
            'no surrounding spaces'   => ['well-known', 'well-known'],
            'hyphen mid-word'         => ['self-aware', 'self-aware'],
            'multiple dashes'         => ['a - b and 1 - 2', "a{$dashSpace}—{$dashSpace}b and 1{$rangeSpace}–{$rangeSpace}2"],
        ];
    }

    public function testDashCharactersAndDashSpacingCanRunSeparately(): void
    {
        $rangeSpace = "\u{2006}";
        $dashSpace  = "\u{200A}";

        $this->assertSame(
            'Range 2010 – 2020',
            Proofreader::fix('Range 2010 - 2020', ['dashes'])
        );
        $this->assertSame(
            "Range 2010{$rangeSpace}–{$rangeSpace}2020",
            Proofreader::fix('Range 2010 - 2020', ['dashes', 'spaces'])
        );
        $this->assertSame(
            "Route draft{$dashSpace}—{$dashSpace}review",
            Proofreader::fix('Route draft - review', ['dashes', 'spaces'])
        );
        $this->assertSame(
            "Range 2010{$rangeSpace}–{$rangeSpace}2020",
            Proofreader::fix('Range 2010–2020', ['spaces'])
        );
        $this->assertSame(
            "Route Hamburg{$rangeSpace}–{$rangeSpace}Bremen",
            Proofreader::fix('Route Hamburg – Bremen', ['dashes', 'spaces'])
        );
    }

    // -------------------------------------------------------------------------
    // fixQuotes
    // -------------------------------------------------------------------------

    public function testFixQuotesSkipsWhenNoLanguageOrConfiguredMarksExist(): void
    {
        $this->assertSame('Text "quoted" here', Proofreader::fixQuotes('Text "quoted" here'));
    }

    public function testFixQuotesUsesEnglishQuotesWhenLanguageIsKnown(): void
    {
        $this->assertSame('Text “quoted” here', Proofreader::fixQuotes('Text "quoted" here', 'en'));
    }

    public function testFixQuotesUsesGermanQuotes(): void
    {
        $this->assertSame('Text „zitiert“ hier', Proofreader::fixQuotes('Text "zitiert" hier', 'de'));
    }

    public function testFixQuotesUsesSingleQuotePairs(): void
    {
        $this->assertSame('Text ‘quoted’ here', Proofreader::fixQuotes("Text 'quoted' here", 'en'));
        $this->assertSame('Text ‚zitiert‘ hier', Proofreader::fixQuotes("Text 'zitiert' hier", 'de'));
    }

    public function testFixQuotesUsesFrenchQuotes(): void
    {
        $thinNbsp = "\u{202F}";

        $this->assertSame(
            "Texte «{$thinNbsp}cité{$thinNbsp}» ici",
            Proofreader::fixQuotes('Texte "cité" ici', 'fr')
        );
    }

    public function testFixQuotesNormalisesApostrophes(): void
    {
        $this->assertSame('It’s ready', Proofreader::fixQuotes("It's ready", 'en'));
        $this->assertSame('It’s ready', Proofreader::fixQuotes('It‘s ready', 'en'));
        $this->assertSame('James’ notes', Proofreader::fixQuotes("James' notes", 'en'));
        $this->assertSame('Hans’ Notiz', Proofreader::fixQuotes("Hans' Notiz", 'de'));
        $this->assertSame('l’éditeur', Proofreader::fixQuotes("l'éditeur", 'fr'));
    }

    public function testFixQuotePairsDoesNotTreatApostrophesAsOpeningQuotes(): void
    {
        $this->assertSame(
            "It's ‘quoted’ here",
            Proofreader::fixQuotePairs("It's 'quoted' here", 'en')
        );
    }

    // -------------------------------------------------------------------------
    // fixApostrophes
    // -------------------------------------------------------------------------

    public function testFixApostrophesNormalisesApostrophes(): void
    {
        $this->assertSame('It’s ready', Proofreader::fixApostrophes("It's ready"));
        $this->assertSame('It’s ready', Proofreader::fixApostrophes('It‘s ready'));
        $this->assertSame('James’ notes', Proofreader::fixApostrophes("James' notes"));
        $this->assertSame('Hans’ Notiz', Proofreader::fixApostrophes("Hans' Notiz"));
        $this->assertSame('l’éditeur', Proofreader::fixApostrophes("l'éditeur"));
    }

    public function testFixApostrophesSkipsSingleQuotePairs(): void
    {
        $this->assertSame(
            "It’s 'quoted' here",
            Proofreader::fixApostrophes("It's 'quoted' here")
        );
        $this->assertSame(
            "Text 'James' here",
            Proofreader::fixApostrophes("Text 'James' here")
        );
    }

    public function testApostrophesRuleCanRunSeparatelyFromQuotes(): void
    {
        $this->assertSame(
            'It’s "quoted" here',
            Proofreader::fix('It\'s "quoted" here', ['apostrophes'], 'en')
        );
        $this->assertSame(
            'It\'s “quoted” here',
            Proofreader::fix('It\'s "quoted" here', ['quotes'], 'en')
        );
        $this->assertSame(
            'It’s “quoted” here',
            Proofreader::fix('It\'s "quoted" here', ['quotes', 'apostrophes'], 'en')
        );
    }

    // -------------------------------------------------------------------------
    // fixLeadingNbsp
    // -------------------------------------------------------------------------

    /**
     * @dataProvider leadingNbspProvider
     */
    #[DataProvider('leadingNbspProvider')]
    public function testFixLeadingNbsp(string $input, string $expected): void
    {
        $this->assertSame($expected, Proofreader::fixLeadingNbsp($input));
    }

    public static function leadingNbspProvider(): array
    {
        $nbsp = "\u{00A0}";

        return [
            '1-letter word'           => ["I went", "I{$nbsp}went"],
            '2-letter word'           => ["in review", "in{$nbsp}review"],
            '3-letter word'           => ["The text", "The{$nbsp}text"],
            '4-letter sentence starter' => ["with review", "with{$nbsp}review"],
            'multiple selected words' => ["I go to review", "I{$nbsp}go to review"],
            'short sentence starter'  => ["Done. It was ready", "Done. It{$nbsp}was ready"],
            'short article'           => ["This is a word", "This{$nbsp}is a{$nbsp}word"],
            'excluded short words'    => ['and be for now', 'and be for now'],
            'tab separator'           => ["in\treview", "in{$nbsp}review"],
            'already nbsp'            => ["in{$nbsp}review", "in{$nbsp}review"],
            'empty string'            => ['', ''],
        ];
    }

    // -------------------------------------------------------------------------
    // fixTrailingNbsp
    // -------------------------------------------------------------------------

    /**
     * @dataProvider trailingNbspProvider
     */
    #[DataProvider('trailingNbspProvider')]
    public function testFixTrailingNbsp(string $input, string $expected): void
    {
        $this->assertSame($expected, Proofreader::fixTrailingNbsp($input));
    }

    public static function trailingNbspProvider(): array
    {
        $nbsp = "\u{00A0}";

        return [
            'short word before period'  => ["go to it.", "go to{$nbsp}it."],
            'short word before comma'   => ["nice, yes, so.", "nice, yes,{$nbsp}so."],
            'short word at end'         => ["go to it", "go to{$nbsp}it"],
            'short paragraph end'       => ["A longer paragraph to me", "A longer paragraph to{$nbsp}me"],
            'excluded trailing words'   => ['done for and be', 'done for and be'],
            '4-letter paragraph end'    => ["let them", "let{$nbsp}them"],
            'short word before exclaim' => ["Done, yes!", "Done,{$nbsp}yes!"],
            'multiline'                 => ["line one it\nline two", "line one{$nbsp}it\nline{$nbsp}two"],
            'empty string'              => ['', ''],
        ];
    }

    // -------------------------------------------------------------------------
    // fixOrdinalSpacing
    // -------------------------------------------------------------------------

    /**
     * @dataProvider ordinalProvider
     */
    #[DataProvider('ordinalProvider')]
    public function testFixOrdinalSpacing(string $input, string $expected): void
    {
        $this->assertSame($expected, Proofreader::fixOrdinalSpacing($input));
    }

    public static function ordinalProvider(): array
    {
        $thinNbsp = "\u{202F}";

        return [
            '1-digit ordinal'           => ["3. January", "3.{$thinNbsp}January"],
            '2-digit ordinal'           => ["21. century", "21.{$thinNbsp}century"],
            '3-digit ordinal'           => ["100. item", "100.{$thinNbsp}item"],
            '4-digit unchanged'         => ['1000. item', '1000. item'],
            'tab separator'             => ["3.\tJanuary", "3.{$thinNbsp}January"],
            'multiple spaces'           => ["3.   January", "3.{$thinNbsp}January"],
            'no following letter'       => ['3. ', '3. '],
            'already thin-nbsp'         => ["3.{$thinNbsp}January", "3.{$thinNbsp}January"],
            'sentence period unchanged' => ['Hello. World', 'Hello. World'],
        ];
    }

    // -------------------------------------------------------------------------
    // fix (pipeline)
    // -------------------------------------------------------------------------

    public function testFixAppliesAllInOrder(): void
    {
        $nbsp     = "\u{00A0}";
        $thinNbsp = "\u{202F}";

        // Targets: Unicode composition, ellipsis, ordinal spacing, leading NBSP.
        $input    = "Cafe\u{0301} docs... see 3. April";
        $expected = "Café{$nbsp}docs… see 3.{$thinNbsp}April";

        $this->assertSame($expected, Proofreader::fix($input));
    }

    public function testFixEmptyString(): void
    {
        $this->assertSame('', Proofreader::fix(''));
    }

    public function testFixIsIdempotent(): void
    {
        // Applying fix() twice should produce the same result as once,
        // since NBSP is not space/tab and won't be re-processed.
        $input  = 'Docs... see 3. April';
        $once   = Proofreader::fix($input);
        $twice  = Proofreader::fix($once);

        $this->assertSame($once, $twice);
    }

    public function testFixCanDisableDashRule(): void
    {
        $this->assertSame('Text… from 2020 - 2024', Proofreader::fix(
            'Text... from 2020 - 2024',
            ['ellipsis']
        ));
    }

    public function testFixRepeatedSpacesCollapsesRegularHorizontalWhitespace(): void
    {
        $nbsp = "\u{00A0}";

        $this->assertSame(
            "Proofreader note{$nbsp}stays",
            Proofreader::fixRepeatedSpaces("Proofreader   note{$nbsp}stays")
        );
    }

    public function testFixPunctuationSpacingRemovesSpacesBeforePunctuation(): void
    {
        $this->assertSame(
            'Proofreader note!',
            Proofreader::fixPunctuationSpacing('Proofreader note !')
        );
        $this->assertSame(
            'Proofreader note: ready',
            Proofreader::fixPunctuationSpacing('Proofreader note : ready')
        );
    }

    public function testFixPunctuationSpacingKeepsColonPrefixedWords(): void
    {
        $this->assertSame(
            'von :mlzd',
            Proofreader::fixPunctuationSpacing('von :mlzd')
        );
    }

    public function testFixPunctuationSpacingKeepsFrenchHighPunctuationSpacing(): void
    {
        $this->assertSame(
            'Proofreader note !',
            Proofreader::fixPunctuationSpacing('Proofreader note !', 'fr_FR')
        );
        $this->assertSame(
            'Proofreader note.',
            Proofreader::fixPunctuationSpacing('Proofreader note .', 'fr_FR')
        );
    }

    public function testFixDimensionsNormalisesMultiplicationSigns(): void
    {
        $space = "\u{2006}";

        $this->assertSame(
            "Frame 5{$space}×{$space}5 cm",
            Proofreader::fixDimensions('Frame 5 x 5 cm')
        );
        $this->assertSame(
            "Frame 5 cm{$space}×{$space}5 cm",
            Proofreader::fixDimensions('Frame 5 cm x 5 cm')
        );
        $this->assertSame(
            "Frame 5{$space}×{$space}5 cm",
            Proofreader::fixDimensions('Frame 5×5 cm')
        );
    }

    public function testRulesForPanelReturnsDefaultRuleOrder(): void
    {
        $this->assertSame(
            ['unicode', 'ellipsis', 'quotes', 'apostrophes', 'dashes', 'spaces'],
            array_column(Proofreader::rulesForPanel(), 'name')
        );
    }

    // -------------------------------------------------------------------------
    // fixFields
    // -------------------------------------------------------------------------

    public function testFixFieldsSkipsNonTextTypes(): void
    {
        $fields = [
            'checkboxes' => 'unicode, dashes',
            'color'      => '#ff0000',
            'date'       => '2026-05-08',
            'email'      => 'proof@kirby-proofreader.test',
            'entries'    => 'editorial-review',
            'files'      => 'proofreader-preview.png',
            'gap'        => 'Review gap...',
            'headline'   => 'Review headline...',
            'hidden'     => 'Hidden review note...',
            'info'       => 'Review info...',
            'line'       => 'Review line...',
            'link'       => 'https://kirby-proofreader.test',
            'multiselect' => 'unicode, dashes',
            'number'     => '5',
            'object'     => 'label: "Object note..."',
            'pages'      => 'editorial-review',
            'radio'      => 'focused',
            'range'      => '5',
            'select'     => 'focused',
            'slug'       => 'editorial-review',
            'stats'      => '23',
            'tags'       => 'proofreader, review',
            'tel'        => '+49 30 123456',
            'time'       => '09:30',
            'toggle'     => 'true',
            'toggles'    => 'unicode, dashes',
            'url'        => 'https://kirby-proofreader.test',
            'users'      => 'proof@kirby-proofreader.test',
        ];
        $blueprint = [
            'checkboxes' => ['type' => 'checkboxes'],
            'color'      => ['type' => 'color'],
            'date'       => ['type' => 'date'],
            'email'      => ['type' => 'email'],
            'entries'    => ['type' => 'entries'],
            'files'      => ['type' => 'files'],
            'gap'        => ['type' => 'gap'],
            'headline'   => ['type' => 'headline'],
            'hidden'     => ['type' => 'hidden'],
            'info'       => ['type' => 'info'],
            'line'       => ['type' => 'line'],
            'link'       => ['type' => 'link'],
            'multiselect' => ['type' => 'multiselect'],
            'number'     => ['type' => 'number'],
            'object'     => ['type' => 'object'],
            'pages'      => ['type' => 'pages'],
            'radio'      => ['type' => 'radio'],
            'range'      => ['type' => 'range'],
            'select'     => ['type' => 'select'],
            'slug'       => ['type' => 'slug'],
            'stats'      => ['type' => 'stats'],
            'tags'       => ['type' => 'tags'],
            'tel'        => ['type' => 'tel'],
            'time'       => ['type' => 'time'],
            'toggle'     => ['type' => 'toggle'],
            'toggles'    => ['type' => 'toggles'],
            'url'        => ['type' => 'url'],
            'users'      => ['type' => 'users'],
        ];

        $this->assertSame($fields, Proofreader::fixFields($fields, $blueprint));
    }

    public function testFixFieldsAppliesFixToTextField(): void
    {
        $fields    = ['title' => 'Hello...'];
        $blueprint = ['title' => ['type' => 'text']];
        $result    = Proofreader::fixFields($fields, $blueprint);

        $this->assertSame('Hello…', $result['title']);
    }

    public function testFixFieldsIncludesPageTitleWithoutBlueprintField(): void
    {
        $fields = ['title' => 'Hello...'];
        $result = Proofreader::fixFields($fields, []);

        $this->assertSame('Hello…', $result['title']);
    }

    public function testReviewFieldsLabelsImplicitPageTitleLikeKirby(): void
    {
        $review = Proofreader::reviewFields(['title' => 'Hello...'], []);

        $this->assertSame('Title', $review['suggestions'][0]['fieldLabel']);
    }

    public function testFixFieldsAppliesFixToTextareaField(): void
    {
        $nbsp      = "\u{00A0}";
        $fields    = ['body' => 'Read the docs... now'];
        $blueprint = ['body' => ['type' => 'textarea']];
        $result    = Proofreader::fixFields($fields, $blueprint);

        // Ellipsis + leading NBSP after the sentence starter + trailing NBSP.
        $this->assertSame("Read{$nbsp}the docs…{$nbsp}now", $result['body']);
    }

    public function testFixFieldsAppliesFixHtmlToListField(): void
    {
        $rangeSpace = "\u{2006}";
        $fields    = ['items' => '<ul><li>Check...</li><li>2025 - 2026</li></ul>'];
        $blueprint = ['items' => ['type' => 'list']];
        $result    = Proofreader::fixFields($fields, $blueprint);

        $this->assertSame(
            "<ul><li>Check…</li><li>2025{$rangeSpace}–{$rangeSpace}2026</li></ul>",
            $result['items']
        );
    }

    public function testFixFieldsLeavesUnknownTypeUnchanged(): void
    {
        // Fields absent from the blueprint pass through without modification.
        $fields    = ['mystery' => 'Hello...'];
        $blueprint = [];
        $result    = Proofreader::fixFields($fields, $blueprint);

        $this->assertSame('Hello...', $result['mystery']);
    }

    public function testFixFieldsHandlesEmptyInput(): void
    {
        $this->assertSame([], Proofreader::fixFields([], []));
    }

    public function testFixFieldsCaseInsensitiveBlueprintKey(): void
    {
        // Blueprint keys should be looked up case-insensitively.
        $fields    = ['Title' => 'Hello...'];
        $blueprint = ['title' => ['type' => 'text']];
        $result    = Proofreader::fixFields($fields, $blueprint);

        $this->assertSame('Hello…', $result['Title']);
    }

    public function testFixFieldsPassesThroughNonStringValues(): void
    {
        // Arrays (e.g. structure fields stored as arrays) are not processed.
        $fields    = ['items' => ['a', 'b']];
        $blueprint = ['items' => ['type' => 'text']]; // type says text but value is array
        $result    = Proofreader::fixFields($fields, $blueprint);

        $this->assertSame(['a', 'b'], $result['items']);
    }

    public function testFixFieldsCanLimitTopLevelFields(): void
    {
        $fields = [
            'title' => 'Hello...',
            'body'  => 'Body...',
        ];
        $blueprint = [
            'title' => ['type' => 'text'],
            'body'  => ['type' => 'textarea'],
        ];

        $result = Proofreader::fixFields($fields, $blueprint, null, null, ['title']);

        $this->assertSame('Hello…', $result['title']);
        $this->assertSame('Body...', $result['body']);
    }

    public function testReviewFieldsReturnsSegmentSuggestions(): void
    {
        $json = json_encode([
            ['type' => 'text', 'id' => 'b1', 'content' => ['text' => '<p>Hello...</p>']],
        ]);
        $fields = [
            'title'  => '"Hello"...',
            'blocks' => $json,
        ];
        $blueprint = [
            'title'  => ['label' => 'Title', 'type' => 'text'],
            'blocks' => [
                'label'     => 'Content Blocks',
                'type'      => 'blocks',
                'fieldsets' => [
                    'text' => [
                        'name'   => 'Text',
                        'fields' => ['text' => ['label' => 'Text', 'type' => 'writer']],
                    ],
                ],
            ],
        ];

        $review = Proofreader::reviewFields($fields, $blueprint, null, 'de');

        $this->assertGreaterThanOrEqual(3, count($review['suggestions']));
        $this->assertContains('quotes', array_column($review['suggestions'], 'rule'));
        $this->assertContains('ellipsis', array_column($review['suggestions'], 'rule'));
        $this->assertContains('Content Blocks -> Text 1 -> Text', array_column($review['suggestions'], 'pathLabel'));
        $this->assertSame('„Hello“…', $review['fixed']['title']);
    }

    public function testReviewFieldsKeepsParagraphBreaksInHtmlPreviews(): void
    {
        $review = Proofreader::reviewFields(
            ['body' => '<p>First paragraph...</p><p>Second paragraph...</p>'],
            ['body' => ['type' => 'writer']],
            ['ellipsis']
        );

        $this->assertSame("First paragraph...\nSecond paragraph...", $review['suggestions'][0]['previewBefore']);
        $this->assertSame("First paragraph…\nSecond paragraph…", $review['suggestions'][0]['previewAfter']);
    }

    // -------------------------------------------------------------------------
    // fixHtml
    // -------------------------------------------------------------------------

    public function testFixHtmlFixesTextNodes(): void
    {
        $this->assertSame('<p>Hello…</p>', Proofreader::fixHtml('<p>Hello...</p>'));
    }

    public function testFixHtmlSkipsCodeElements(): void
    {
        $input    = '<p>Text...</p><code>code...</code>';
        $expected = '<p>Text…</p><code>code...</code>';

        $this->assertSame($expected, Proofreader::fixHtml($input));
    }

    public function testFixHtmlSkipsPreElements(): void
    {
        $input    = '<p>Text...</p><pre>pre...</pre>';
        $expected = '<p>Text…</p><pre>pre...</pre>';

        $this->assertSame($expected, Proofreader::fixHtml($input));
    }

    public function testFixHtmlSkipsCodeLikeElements(): void
    {
        $input = '<p>Text...</p><pre><code>code...</code> pre...</pre><kbd>kbd...</kbd><samp>samp...</samp><math>math...</math><script>script...</script><style>style...</style>';
        $expected = '<p>Text…</p><pre><code>code...</code> pre...</pre><kbd>kbd...</kbd><samp>samp...</samp><math>math...</math><script>script...</script><style>style...</style>';

        $this->assertSame($expected, Proofreader::fixHtml($input));
    }

    public function testFixHtmlPreservesTagsExactly(): void
    {
        $dashSpace = "\u{200A}";
        $input    = '<p><strong>bold - text</strong></p>';
        $expected = "<p><strong>bold{$dashSpace}—{$dashSpace}text</strong></p>";

        $this->assertSame($expected, Proofreader::fixHtml($input));
    }

    public function testFixHtmlEmptyString(): void
    {
        $this->assertSame('', Proofreader::fixHtml(''));
    }

    public function testFixHtmlFixesDashesInTextNodes(): void
    {
        $rangeSpace = "\u{2006}";
        $input    = '<p>2020 - 2024</p>';
        $expected = "<p>2020{$rangeSpace}–{$rangeSpace}2024</p>";

        $this->assertSame($expected, Proofreader::fixHtml($input));
    }

    // -------------------------------------------------------------------------
    // fixFields — writer
    // -------------------------------------------------------------------------

    public function testFixFieldsAppliesFixHtmlToWriterField(): void
    {
        $fields    = ['body' => '<p>Hello...</p>'];
        $blueprint = ['body' => ['type' => 'writer']];
        $result    = Proofreader::fixFields($fields, $blueprint);

        $this->assertSame('<p>Hello…</p>', $result['body']);
    }

    public function testFixFieldsSkipsUrlEmailAlongsideWriter(): void
    {
        $fields = [
            'body'  => '<p>Hello...</p>',
            'url'   => 'https://kirby-proofreader.test',
            'email' => 'proof@kirby-proofreader.test',
        ];
        $blueprint = [
            'body'  => ['type' => 'writer'],
            'url'   => ['type' => 'url'],
            'email' => ['type' => 'email'],
        ];
        $result = Proofreader::fixFields($fields, $blueprint);

        $this->assertSame('<p>Hello…</p>', $result['body']);
        $this->assertSame('https://kirby-proofreader.test', $result['url']);
        $this->assertSame('proof@kirby-proofreader.test', $result['email']);
    }

    // -------------------------------------------------------------------------
    // processStructureRows
    // -------------------------------------------------------------------------

    public function testProcessStructureRowsFixesEligibleFields(): void
    {
        $dashSpace = "\u{200A}";
        $rows = [
            ['name' => 'Review entry...', 'role' => 'Editorial role - planning'],
            ['name' => 'Documentation entry...', 'role' => 'Content role - review'],
        ];
        $subFields = [
            'name' => ['type' => 'text'],
            'role' => ['type' => 'text'],
        ];

        $result = Proofreader::processStructureRows($rows, $subFields);

        $this->assertSame('Review entry…', $result[0]['name']);
        $this->assertSame("Editorial role{$dashSpace}—{$dashSpace}planning", $result[0]['role']);
        $this->assertSame('Documentation entry…', $result[1]['name']);
    }

    public function testProcessStructureRowsSkipsNonEligibleFields(): void
    {
        $rows = [[
            'contact'   => 'proof@kirby-proofreader.test',
            'reference' => 'https://kirby-proofreader.test',
            'checked'   => '2026-05-08',
            'mode'      => 'focused',
        ]];
        $subFields = [
            'contact'   => ['type' => 'email'],
            'reference' => ['type' => 'url'],
            'checked'   => ['type' => 'date'],
            'mode'      => ['type' => 'select'],
        ];

        $result = Proofreader::processStructureRows($rows, $subFields);

        $this->assertSame('proof@kirby-proofreader.test', $result[0]['contact']);
        $this->assertSame('https://kirby-proofreader.test', $result[0]['reference']);
        $this->assertSame('2026-05-08', $result[0]['checked']);
        $this->assertSame('focused', $result[0]['mode']);
    }

    public function testProcessStructureRowsEmptyRows(): void
    {
        $this->assertSame([], Proofreader::processStructureRows([], ['name' => ['type' => 'text']]));
    }

    // -------------------------------------------------------------------------
    // processBlocks
    // -------------------------------------------------------------------------

    public function testProcessBlocksFixesWriterContentField(): void
    {
        $blocks = [
            ['type' => 'text', 'id' => 'b1', 'content' => ['text' => '<p>Hello...</p>']],
        ];
        $fieldsets = [
            'text' => ['fields' => ['text' => ['type' => 'writer']]],
        ];

        $result = Proofreader::processBlocks($blocks, $fieldsets);

        $this->assertSame('<p>Hello…</p>', $result[0]['content']['text']);
    }

    public function testProcessBlocksFixesTextareaContentField(): void
    {
        $blocks = [
            ['type' => 'quote', 'id' => 'b2', 'content' => ['text' => '3. April...']],
        ];
        $fieldsets = [
            'quote' => ['fields' => ['text' => ['type' => 'textarea']]],
        ];

        $thinNbsp = "\u{202F}";
        $result   = Proofreader::processBlocks($blocks, $fieldsets);

        $this->assertSame("3.{$thinNbsp}April…", $result[0]['content']['text']);
    }

    public function testProcessBlocksSkipsUnknownBlockType(): void
    {
        $blocks    = [['type' => 'custom', 'id' => 'b3', 'content' => ['text' => 'Hello...']]];
        $fieldsets = []; // no fieldset for 'custom'

        $result = Proofreader::processBlocks($blocks, $fieldsets);

        $this->assertSame('Hello...', $result[0]['content']['text']);
    }

    public function testProcessBlocksHandlesTabbedFieldsets(): void
    {
        $blocks = [
            ['type' => 'text', 'id' => 'b4', 'content' => ['body' => '<p>Hello...</p>']],
        ];
        $fieldsets = [
            'text' => [
                'tabs' => [
                    'content' => ['fields' => ['body' => ['type' => 'writer']]],
                ],
            ],
        ];

        $result = Proofreader::processBlocks($blocks, $fieldsets);

        $this->assertSame('<p>Hello…</p>', $result[0]['content']['body']);
    }

    // -------------------------------------------------------------------------
    // fixFields — blocks (JSON round-trip, PHP native json_decode)
    // -------------------------------------------------------------------------

    public function testFixFieldsAppliesFixToBlocksField(): void
    {
        $json  = json_encode([
            ['type' => 'text', 'id' => 'b1', 'content' => ['text' => '<p>Hello...</p>']],
        ]);
        $fields    = ['content' => $json];
        $blueprint = [
            'content' => [
                'type'      => 'blocks',
                'fieldsets' => [
                    'text' => ['fields' => ['text' => ['type' => 'writer']]],
                ],
            ],
        ];

        $result        = Proofreader::fixFields($fields, $blueprint);
        $decodedResult = json_decode($result['content'], associative: true);

        $this->assertSame('<p>Hello…</p>', $decodedResult[0]['content']['text']);
    }

    public function testFixFieldsSkipsBlocksWithInvalidJson(): void
    {
        $fields    = ['content' => 'not-valid-json'];
        $blueprint = ['content' => ['type' => 'blocks', 'fieldsets' => []]];

        $result = Proofreader::fixFields($fields, $blueprint);

        $this->assertSame('not-valid-json', $result['content']);
    }

    public function testFixFieldsAppliesFixToLayoutField(): void
    {
        $json = json_encode([
            [
                'columns' => [
                    [
                        'blocks' => [
                            ['type' => 'text', 'id' => 'layout-b1', 'content' => ['text' => '<p>Hello...</p>']],
                        ],
                    ],
                ],
            ],
        ]);
        $fields    = ['layout' => $json];
        $blueprint = [
            'layout' => [
                'type'      => 'layout',
                'fieldsets' => [
                    'text' => ['fields' => ['text' => ['type' => 'writer']]],
                ],
            ],
        ];

        $result        = Proofreader::fixFields($fields, $blueprint);
        $decodedResult = json_decode($result['layout'], associative: true);

        $this->assertSame('<p>Hello…</p>', $decodedResult[0]['columns'][0]['blocks'][0]['content']['text']);
    }
}
