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
        self::assertSame($expected, Proofreader::fixUnicodeComposition($input));
    }

    public static function unicodeCompositionProvider(): array
    {
        return [
            'lowercase diaeresis' => ["a\u{0308}", 'ä'],
            'lowercase u diaeresis' => ["fu\u{0308}r", 'für'],
            'uppercase diaeresis' => ["U\u{0308}ber", 'Über'],
            'acute accent'        => ["Cafe\u{0301}", 'Café'],
            'already composed'    => ['Mädchen', 'Mädchen'],
            'plain text'          => ['Editorial text', 'Editorial text'],
        ];
    }

    public function testReviewFieldsMatchesDashedContentKeysToUnderscoredBlueprintFields(): void
    {
        $review = Proofreader::reviewFields(
            ['portrait-layout' => "steht fu\u{0308}r"],
            ['portrait_layout' => ['label' => 'Portrait', 'type' => 'text']],
            ['unicode']
        );

        self::assertSame('steht für', $review['fixed']['portrait-layout']);
        self::assertCount(1, $review['suggestions']);
        self::assertSame('portrait-layout', $review['suggestions'][0]['field']);
        self::assertSame('Portrait', $review['suggestions'][0]['fieldLabel']);
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
        self::assertSame($expected, Proofreader::fixEllipsis($input));
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
        self::assertSame($expected, Proofreader::fixDashes($input));
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
            'numeric chain three parts' => ['0800-123-4567', "0800{$rangeSpace}–{$rangeSpace}123{$rangeSpace}–{$rangeSpace}4567"],
        ];
    }

    public function testDashCharactersAndDashSpacingCanRunSeparately(): void
    {
        $rangeSpace = "\u{2006}";
        $dashSpace  = "\u{200A}";

        self::assertSame(
            'Range 2010 – 2020',
            Proofreader::fix('Range 2010 - 2020', ['dashes'])
        );
        self::assertSame(
            "Range 2010{$rangeSpace}–{$rangeSpace}2020",
            Proofreader::fix('Range 2010 - 2020', ['dashes', 'spaces'])
        );
        self::assertSame(
            "Route draft{$dashSpace}—{$dashSpace}review",
            Proofreader::fix('Route draft - review', ['dashes', 'spaces'])
        );
        self::assertSame(
            "Range 2010{$rangeSpace}–{$rangeSpace}2020",
            Proofreader::fix('Range 2010–2020', ['spaces'])
        );
        self::assertSame(
            "Route Hamburg{$rangeSpace}–{$rangeSpace}Bremen",
            Proofreader::fix('Route Hamburg – Bremen', ['dashes', 'spaces'])
        );
    }

    // -------------------------------------------------------------------------
    // fixQuotes
    // -------------------------------------------------------------------------

    public function testFixQuotesSkipsWhenNoLanguageOrConfiguredMarksExist(): void
    {
        self::assertSame('Text "quoted" here', Proofreader::fixQuotes('Text "quoted" here'));
    }

    public function testFixQuotesUsesEnglishQuotesWhenLanguageIsKnown(): void
    {
        self::assertSame('Text “quoted” here', Proofreader::fixQuotes('Text "quoted" here', 'en'));
    }

    public function testFixQuotesUsesGermanQuotes(): void
    {
        self::assertSame('Text „zitiert“ hier', Proofreader::fixQuotes('Text "zitiert" hier', 'de'));
    }

    public function testFixQuotesUsesSingleQuotePairs(): void
    {
        self::assertSame('Text ‘quoted’ here', Proofreader::fixQuotes("Text 'quoted' here", 'en'));
        self::assertSame('Text ‚zitiert‘ hier', Proofreader::fixQuotes("Text 'zitiert' hier", 'de'));
    }

    public function testFixQuotesUsesFrenchQuotes(): void
    {
        $thinNbsp = "\u{202F}";

        self::assertSame(
            "Texte «{$thinNbsp}cité{$thinNbsp}» ici",
            Proofreader::fixQuotes('Texte "cité" ici', 'fr')
        );
    }

    public function testFixQuotesNormalisesApostrophes(): void
    {
        self::assertSame('It’s ready', Proofreader::fixQuotes("It's ready", 'en'));
        self::assertSame('It’s ready', Proofreader::fixQuotes('It‘s ready', 'en'));
        self::assertSame('James’ notes', Proofreader::fixQuotes("James' notes", 'en'));
        self::assertSame('Hans’ Notiz', Proofreader::fixQuotes("Hans' Notiz", 'de'));
        self::assertSame('l’éditeur', Proofreader::fixQuotes("l'éditeur", 'fr'));
    }

    public function testFixQuotePairsDoesNotTreatApostrophesAsOpeningQuotes(): void
    {
        self::assertSame(
            "It's ‘quoted’ here",
            Proofreader::fixQuotePairs("It's 'quoted' here", 'en')
        );
    }

    // -------------------------------------------------------------------------
    // fixApostrophes
    // -------------------------------------------------------------------------

    public function testFixApostrophesNormalisesApostrophes(): void
    {
        self::assertSame('It’s ready', Proofreader::fixApostrophes("It's ready"));
        self::assertSame('It’s ready', Proofreader::fixApostrophes('It‘s ready'));
        self::assertSame('James’ notes', Proofreader::fixApostrophes("James' notes"));
        self::assertSame('Hans’ Notiz', Proofreader::fixApostrophes("Hans' Notiz"));
        self::assertSame('l’éditeur', Proofreader::fixApostrophes("l'éditeur"));
    }

    public function testFixApostrophesSkipsSingleQuotePairs(): void
    {
        self::assertSame(
            "It’s 'quoted' here",
            Proofreader::fixApostrophes("It's 'quoted' here")
        );
        self::assertSame(
            "Text 'James' here",
            Proofreader::fixApostrophes("Text 'James' here")
        );
    }

    public function testApostrophesRuleCanRunSeparatelyFromQuotes(): void
    {
        self::assertSame(
            'It’s "quoted" here',
            Proofreader::fix('It\'s "quoted" here', ['apostrophes'], 'en')
        );
        self::assertSame(
            'It\'s “quoted” here',
            Proofreader::fix('It\'s "quoted" here', ['quotes'], 'en')
        );
        self::assertSame(
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
        self::assertSame($expected, Proofreader::fixLeadingNbsp($input));
    }

    public static function leadingNbspProvider(): array
    {
        $nbsp = "\u{00A0}";

        return [
            '1-letter word at paragraph start' => ['I went', 'I went'],
            '2-letter word at paragraph start' => ['in review', 'in review'],
            '3-letter word at paragraph start' => ['The text', 'The text'],
            '4-letter word at paragraph start' => ['with review', 'with review'],
            'multiple selected words after paragraph start' => ['I go to review', 'I go to review'],
            'short sentence starter'  => ["Done. It was ready", "Done. It{$nbsp}was ready"],
            'short word after paragraph break' => ["Done.\nIt was ready", "Done.\nIt was ready"],
            'short article'           => ["This is a word", "This is a{$nbsp}word"],
            'excluded short words'    => ['and be for now', 'and be for now'],
            'tab separator at paragraph start' => ["in\treview", "in\treview"],
            'indented paragraph start' => ["  I went", "  I went"],
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
        self::assertSame($expected, Proofreader::fixTrailingNbsp($input));
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
        self::assertSame($expected, Proofreader::fixOrdinalSpacing($input));
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
        $thinNbsp = "\u{202F}";

        // Targets: Unicode composition, ellipsis and ordinal spacing.
        $input    = "Cafe\u{0301} docs... see 3. April";
        $expected = "Café docs… see 3.{$thinNbsp}April";

        self::assertSame($expected, Proofreader::fix($input));
    }

    public function testFixEmptyString(): void
    {
        self::assertSame('', Proofreader::fix(''));
    }

    public function testFixIsIdempotent(): void
    {
        // Applying fix() twice should produce the same result as once,
        // since NBSP is not space/tab and won't be re-processed.
        $input  = 'Docs... see 3. April';
        $once   = Proofreader::fix($input);
        $twice  = Proofreader::fix($once);

        self::assertSame($once, $twice);
    }

    public function testFixCanDisableDashRule(): void
    {
        self::assertSame('Text… from 2020 - 2024', Proofreader::fix(
            'Text... from 2020 - 2024',
            ['ellipsis']
        ));
    }

    public function testFixRepeatedSpacesCollapsesRegularHorizontalWhitespace(): void
    {
        $nbsp = "\u{00A0}";

        self::assertSame(
            "Proofreader note{$nbsp}stays",
            Proofreader::fixRepeatedSpaces("Proofreader   note{$nbsp}stays")
        );
    }

    public function testFixParagraphEdgeSpacesTrimsRegularHorizontalWhitespace(): void
    {
        self::assertSame(
            "First paragraph\nSecond paragraph",
            Proofreader::fixParagraphEdgeSpaces("  First paragraph  \n\tSecond paragraph\t")
        );
    }

    public function testFixPunctuationSpacingRemovesSpacesBeforePunctuation(): void
    {
        self::assertSame(
            'Proofreader note!',
            Proofreader::fixPunctuationSpacing('Proofreader note !')
        );
        self::assertSame(
            'Proofreader note: ready',
            Proofreader::fixPunctuationSpacing('Proofreader note : ready')
        );
    }

    public function testFixPunctuationSpacingKeepsColonPrefixedWords(): void
    {
        self::assertSame(
            'von :mlzd',
            Proofreader::fixPunctuationSpacing('von :mlzd')
        );
    }

    public function testFixPunctuationSpacingKeepsFrenchHighPunctuationSpacing(): void
    {
        self::assertSame(
            'Proofreader note !',
            Proofreader::fixPunctuationSpacing('Proofreader note !', 'fr_FR')
        );
        self::assertSame(
            'Proofreader note.',
            Proofreader::fixPunctuationSpacing('Proofreader note .', 'fr_FR')
        );
    }

    public function testFixDimensionsNormalisesMultiplicationSigns(): void
    {
        $space = "\u{2006}";

        self::assertSame(
            "Frame 5{$space}×{$space}5 cm",
            Proofreader::fixDimensions('Frame 5 x 5 cm')
        );
        self::assertSame(
            "Frame 5 cm{$space}×{$space}5 cm",
            Proofreader::fixDimensions('Frame 5 cm x 5 cm')
        );
        self::assertSame(
            "Frame 5{$space}×{$space}5 cm",
            Proofreader::fixDimensions('Frame 5×5 cm')
        );
    }

    public function testRulesForPanelReturnsDefaultRuleOrder(): void
    {
        self::assertSame(
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

        self::assertSame($fields, Proofreader::fixFields($fields, $blueprint));
    }

    public function testFixFieldsAppliesFixToTextField(): void
    {
        $fields    = ['title' => 'Hello...'];
        $blueprint = ['title' => ['type' => 'text']];
        $result    = Proofreader::fixFields($fields, $blueprint);

        self::assertSame('Hello…', $result['title']);
    }

    public function testFixFieldsIncludesPageTitleWithoutBlueprintField(): void
    {
        $fields = ['title' => 'Hello...'];
        $result = Proofreader::fixFields($fields, []);

        self::assertSame('Hello…', $result['title']);
    }

    public function testReviewFieldsLabelsImplicitPageTitleLikeKirby(): void
    {
        $review = Proofreader::reviewFields(['title' => 'Hello...'], []);

        self::assertSame('Title', $review['suggestions'][0]['fieldLabel']);
    }

    public function testFixFieldsAppliesFixToTextareaField(): void
    {
        $nbsp      = "\u{00A0}";
        $fields    = ['body' => 'Read the docs... now'];
        $blueprint = ['body' => ['type' => 'textarea']];
        $result    = Proofreader::fixFields($fields, $blueprint);

        // Ellipsis + trailing NBSP.
        self::assertSame("Read the docs…{$nbsp}now", $result['body']);
    }

    public function testFixFieldsAppliesFixHtmlToListField(): void
    {
        $rangeSpace = "\u{2006}";
        $fields    = ['items' => '<ul><li>Check...</li><li>2025 - 2026</li></ul>'];
        $blueprint = ['items' => ['type' => 'list']];
        $result    = Proofreader::fixFields($fields, $blueprint);

        self::assertSame(
            "<ul><li>Check…</li><li>2025{$rangeSpace}–{$rangeSpace}2026</li></ul>",
            $result['items']
        );
    }

    public function testFixFieldsAppliesFixToEntriesField(): void
    {
        $fields = ['awards' => "- Award...\n- 2020 - 2021"];
        $blueprint = [
            'awards' => [
                'label' => 'Awards',
                'type'  => 'entries',
                'field' => [
                    'label' => 'Title',
                    'type'  => 'text',
                ],
            ],
        ];

        $result = Proofreader::fixFields($fields, $blueprint, ['ellipsis', 'dashes']);

        self::assertSame("- Award…\n- 2020 – 2021\n", $result['awards']);
    }

    public function testReviewFieldsReturnsEntriesSuggestions(): void
    {
        $fields = ['awards' => "- Award...\n- 2020 - 2021"];
        $blueprint = [
            'awards' => [
                'label' => 'Awards',
                'type'  => 'entries',
                'field' => [
                    'label' => 'Title',
                    'type'  => 'text',
                ],
            ],
        ];

        $review = Proofreader::reviewFields($fields, $blueprint, ['ellipsis', 'dashes']);

        self::assertSame(['ellipsis', 'dashes'], array_column($review['suggestions'], 'rule'));
        self::assertSame(
            ['Awards -> Entry 1', 'Awards -> Entry 2'],
            array_column($review['suggestions'], 'pathLabel')
        );
    }

    public function testFixFieldsSkipsUnsupportedEntriesFieldType(): void
    {
        $fields = ['links' => "- https://kirby-proofreader.test/one...\n"];
        $blueprint = [
            'links' => [
                'type'  => 'entries',
                'field' => ['type' => 'url'],
            ],
        ];

        $result = Proofreader::fixFields($fields, $blueprint, ['ellipsis']);

        self::assertSame("- https://kirby-proofreader.test/one...\n", $result['links']);
    }

    public function testFixFieldsLeavesUnknownTypeUnchanged(): void
    {
        // Fields absent from the blueprint pass through without modification.
        $fields    = ['mystery' => 'Hello...'];
        $blueprint = [];
        $result    = Proofreader::fixFields($fields, $blueprint);

        self::assertSame('Hello...', $result['mystery']);
    }

    public function testFixFieldsHandlesEmptyInput(): void
    {
        self::assertSame([], Proofreader::fixFields([], []));
    }

    public function testFixFieldsCaseInsensitiveBlueprintKey(): void
    {
        // Blueprint keys should be looked up case-insensitively.
        $fields    = ['Title' => 'Hello...'];
        $blueprint = ['title' => ['type' => 'text']];
        $result    = Proofreader::fixFields($fields, $blueprint);

        self::assertSame('Hello…', $result['Title']);
    }

    public function testFixFieldsPassesThroughNonStringValues(): void
    {
        // Arrays (e.g. structure fields stored as arrays) are not processed.
        $fields    = ['items' => ['a', 'b']];
        $blueprint = ['items' => ['type' => 'text']]; // type says text but value is array
        $result    = Proofreader::fixFields($fields, $blueprint);

        self::assertSame(['a', 'b'], $result['items']);
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

        self::assertSame('Hello…', $result['title']);
        self::assertSame('Body...', $result['body']);
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

        self::assertGreaterThanOrEqual(3, count($review['suggestions']));
        self::assertContains('quotes', array_column($review['suggestions'], 'rule'));
        self::assertContains('ellipsis', array_column($review['suggestions'], 'rule'));
        self::assertContains('Content Blocks -> Text 1 -> Text', array_column($review['suggestions'], 'pathLabel'));
        self::assertSame('„Hello“…', $review['fixed']['title']);
    }

    public function testReviewFieldsKeepsParagraphBreaksInHtmlPreviews(): void
    {
        $review = Proofreader::reviewFields(
            ['body' => '<p>First paragraph...</p><p>Second paragraph...</p>'],
            ['body' => ['type' => 'writer']],
            ['ellipsis']
        );

        self::assertSame("First paragraph...\nSecond paragraph...", $review['suggestions'][0]['previewBefore']);
        self::assertSame("First paragraph…\nSecond paragraph…", $review['suggestions'][0]['previewAfter']);
    }

    // -------------------------------------------------------------------------
    // fixHtml
    // -------------------------------------------------------------------------

    public function testFixHtmlFixesTextNodes(): void
    {
        self::assertSame('<p>Hello…</p>', Proofreader::fixHtml('<p>Hello...</p>'));
    }

    public function testFixHtmlSkipsCodeElements(): void
    {
        $input    = '<p>Text...</p><code>code...</code>';
        $expected = '<p>Text…</p><code>code...</code>';

        self::assertSame($expected, Proofreader::fixHtml($input));
    }

    public function testFixHtmlSkipsPreElements(): void
    {
        $input    = '<p>Text...</p><pre>pre...</pre>';
        $expected = '<p>Text…</p><pre>pre...</pre>';

        self::assertSame($expected, Proofreader::fixHtml($input));
    }

    public function testFixHtmlSkipsCodeLikeElements(): void
    {
        $input = '<p>Text...</p><pre><code>code...</code> pre...</pre><kbd>kbd...</kbd><samp>samp...</samp><math>math...</math><script>script...</script><style>style...</style>';
        $expected = '<p>Text…</p><pre><code>code...</code> pre...</pre><kbd>kbd...</kbd><samp>samp...</samp><math>math...</math><script>script...</script><style>style...</style>';

        self::assertSame($expected, Proofreader::fixHtml($input));
    }

    public function testFixHtmlPreservesTagsExactly(): void
    {
        $dashSpace = "\u{200A}";
        $input    = '<p><strong>bold - text</strong></p>';
        $expected = "<p><strong>bold{$dashSpace}—{$dashSpace}text</strong></p>";

        self::assertSame($expected, Proofreader::fixHtml($input));
    }

    public function testFixHtmlEmptyString(): void
    {
        self::assertSame('', Proofreader::fixHtml(''));
    }

    public function testFixHtmlFixesDashesInTextNodes(): void
    {
        $rangeSpace = "\u{2006}";
        $input    = '<p>2020 - 2024</p>';
        $expected = "<p>2020{$rangeSpace}–{$rangeSpace}2024</p>";

        self::assertSame($expected, Proofreader::fixHtml($input));
    }

    public function testFixHtmlTrimsParagraphEdgeSpaces(): void
    {
        self::assertSame(
            '<p>Text</p><p><strong>More</strong></p>',
            Proofreader::fixHtml('<p> Text </p><p><strong> More </strong></p>', ['spaces'])
        );
    }

    public function testFixHtmlKeepsInlineTagSpacing(): void
    {
        self::assertSame(
            '<p>Text <strong>keeps</strong> spacing</p>',
            Proofreader::fixHtml('<p>Text <strong>keeps</strong> spacing</p>', ['spaces'])
        );
    }

    public function testFixHtmlAddsLeadingNbspAfterInlineTags(): void
    {
        $nbsp = "\u{00A0}";

        self::assertSame(
            "<p><strong>Intro</strong> a{$nbsp}note</p>",
            Proofreader::fixHtml('<p><strong>Intro</strong> a note</p>', ['spaces'])
        );
    }

    public function testFixHtmlAddsSentenceStarterNbspAcrossInlineTags(): void
    {
        $nbsp = "\u{00A0}";

        self::assertSame(
            "<p>Done. <em>It{$nbsp}was</em></p>",
            Proofreader::fixHtml('<p>Done. <em>It was</em></p>', ['spaces'])
        );
    }

    // -------------------------------------------------------------------------
    // fixFields — writer
    // -------------------------------------------------------------------------

    public function testFixFieldsAppliesFixHtmlToWriterField(): void
    {
        $fields    = ['body' => '<p>Hello...</p>'];
        $blueprint = ['body' => ['type' => 'writer']];
        $result    = Proofreader::fixFields($fields, $blueprint);

        self::assertSame('<p>Hello…</p>', $result['body']);
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

        self::assertSame('<p>Hello…</p>', $result['body']);
        self::assertSame('https://kirby-proofreader.test', $result['url']);
        self::assertSame('proof@kirby-proofreader.test', $result['email']);
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

        self::assertSame('Review entry…', $result[0]['name']);
        self::assertSame("Editorial role{$dashSpace}—{$dashSpace}planning", $result[0]['role']);
        self::assertSame('Documentation entry…', $result[1]['name']);
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

        self::assertSame('proof@kirby-proofreader.test', $result[0]['contact']);
        self::assertSame('https://kirby-proofreader.test', $result[0]['reference']);
        self::assertSame('2026-05-08', $result[0]['checked']);
        self::assertSame('focused', $result[0]['mode']);
    }

    public function testProcessStructureRowsEmptyRows(): void
    {
        self::assertSame([], Proofreader::processStructureRows([], ['name' => ['type' => 'text']]));
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

        self::assertSame('<p>Hello…</p>', $result[0]['content']['text']);
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

        self::assertSame("3.{$thinNbsp}April…", $result[0]['content']['text']);
    }

    public function testProcessBlocksSkipsUnknownBlockType(): void
    {
        $blocks    = [['type' => 'custom', 'id' => 'b3', 'content' => ['text' => 'Hello...']]];
        $fieldsets = []; // no fieldset for 'custom'

        $result = Proofreader::processBlocks($blocks, $fieldsets);

        self::assertSame('Hello...', $result[0]['content']['text']);
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

        self::assertSame('<p>Hello…</p>', $result[0]['content']['body']);
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

        self::assertSame('<p>Hello…</p>', $decodedResult[0]['content']['text']);
    }

    public function testFixFieldsSkipsBlocksWithInvalidJson(): void
    {
        $fields    = ['content' => 'not-valid-json'];
        $blueprint = ['content' => ['type' => 'blocks', 'fieldsets' => []]];

        $result = Proofreader::fixFields($fields, $blueprint);

        self::assertSame('not-valid-json', $result['content']);
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

        self::assertSame('<p>Hello…</p>', $decodedResult[0]['columns'][0]['blocks'][0]['content']['text']);
    }
}
