<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader\Tests\Unit;

use GrommasDietz\Proofreader\Proofreader;
use GrommasDietz\Proofreader\Tests\TestCase;

final class ProofreaderRulesConfigTest extends TestCase
{
    public function testNativeSmartypantsOptionsDefineQuotesAndDashSpacing(): void
    {
        $this->bootKirby([
            'options' => [
                'languages' => false,
                'smartypants' => [
                    'doublequote.open' => '&laquo;',
                    'doublequote.close' => '&raquo;',
                    'singlequote.open' => '&lsaquo;',
                    'singlequote.close' => '&rsaquo;',
                    'emdash' => '&mdash;',
                    'endash' => '&ndash;',
                    'space.emdash' => '&hairsp;',
                    'space.endash' => '&thinsp;',
                ],
            ],
        ]);

        $hairSpace = "\u{200A}";
        $thinSpace = "\u{2009}";

        self::assertSame(
            'Text «quoted» and ‹aside›',
            Proofreader::fixQuotes('Text "quoted" and \'aside\'')
        );
        self::assertSame(
            "Range 2019{$thinSpace}–{$thinSpace}2024 and note{$hairSpace}—{$hairSpace}review",
            Proofreader::fix('Range 2019 - 2024 and note - review', ['dashes', 'spaces'])
        );
    }

    public function testRulesOptionCanDisableAndReorderRules(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'rules' => [
                        'dashes',
                        'quotes' => false,
                        'ellipsis',
                    ],
                ],
            ],
        ]);

        self::assertSame(
            ['dashes', 'ellipsis'],
            array_column(Proofreader::rulesForPanel(), 'name')
        );
        self::assertSame(
            'Text… from 2020 – 2024',
            Proofreader::fix('Text... from 2020 - 2024')
        );
    }

    public function testRulesOptionCanEnableOptionalDimensionRule(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'rules' => [
                        'unicode',
                        'ellipsis',
                        'quotes',
                        'apostrophes',
                        'dashes',
                        'spaces',
                        'dimensions',
                    ],
                ],
            ],
        ]);

        $space = "\u{2006}";
        $nbsp = "\u{00A0}";

        self::assertSame(
            ['unicode', 'ellipsis', 'quotes', 'apostrophes', 'dashes', 'spaces', 'dimensions'],
            array_column(Proofreader::rulesForPanel(), 'name')
        );
        self::assertSame(
            "Frame 5{$space}×{$space}5{$nbsp}cm",
            Proofreader::fix('Frame 5 x 5 cm')
        );
    }

    public function testRulesOptionSupportsInlineCustomRules(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'rules' => [
                        'customTrademark' => [
                            'label' => 'Trademark',
                            'callback' => static fn (string $text): string => str_replace(
                                'Label TM',
                                'Label™',
                                $text
                            ),
                        ],
                        'customCopyright' => [
                            'label'   => 'Copyright',
                            'pattern' => '/\s+\(c\)/u',
                            'replace' => ' ©',
                        ],
                        'noMatches' => [
                            'label'   => 'No matches',
                            'pattern' => '/NeverMatches/u',
                            'replace' => 'Matched',
                        ],
                    ],
                ],
            ],
        ]);

        $review = Proofreader::reviewFields(
            ['subtitle' => 'Label TM (c)'],
            ['subtitle' => ['type' => 'text']]
        );

        self::assertSame(
            ['customTrademark', 'customCopyright', 'noMatches'],
            array_column(Proofreader::rulesForPanel(), 'name')
        );
        self::assertSame(
            ['Trademark', 'Copyright', 'No matches'],
            array_column(Proofreader::rulesForPanel(), 'label')
        );
        self::assertSame(
            ['customTrademark', 'customCopyright'],
            array_column($review['suggestions'], 'rule')
        );
        self::assertSame('Label™ ©', $review['fixed']['subtitle']);
    }

    public function testReviewFieldsTranslatesBlueprintLabelKeys(): void
    {
        $this->bootKirby([
            'translations' => [
                'en' => [
                    'project.client' => 'Client',
                ],
            ],
        ]);
        $this->kirby->setCurrentTranslation('en');

        $review = Proofreader::reviewFields(
            ['client' => 'Client...'],
            ['client' => ['label' => 'project.client', 'type' => 'text']],
            ['ellipsis']
        );

        self::assertSame('Client', $review['suggestions'][0]['fieldLabel']);
        self::assertSame('Client', $review['suggestions'][0]['pathLabel']);
    }

    public function testBlocksFieldsResolveKirbyFieldsetShorthand(): void
    {
        $this->bootKirby();

        $blocks = json_encode([
            ['type' => 'text', 'id' => 'text-a', 'content' => ['text' => '<p>Hello...</p>']],
        ]);

        self::assertIsString($blocks);

        $review = Proofreader::reviewFields(
            ['blocks' => $blocks],
            ['blocks' => ['type' => 'blocks', 'fieldsets' => ['text']]],
            ['ellipsis']
        );

        self::assertSame(['ellipsis'], array_column($review['suggestions'], 'rule'));
        self::assertSame('<p>Hello…</p>', json_decode($review['fixed']['blocks'], true)[0]['content']['text']);
    }

    public function testFieldsOptionCanIncludeCustomPlainFieldTypes(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'fields' => [
                        'include' => [
                            'types' => [
                                'custom-text' => 'plain',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $review = Proofreader::reviewFields(
            ['summary' => 'Custom field...'],
            ['summary' => ['type' => 'custom-text']],
            ['ellipsis']
        );

        self::assertSame(['ellipsis'], array_column($review['suggestions'], 'rule'));
        self::assertSame('Custom field…', $review['fixed']['summary']);
    }

    public function testFieldsOptionCanIncludeCustomHtmlFieldNames(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'fields' => [
                        'include' => [
                            'names' => [
                                'body' => 'html',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $review = Proofreader::reviewFields(
            ['body' => '<p>Custom field...</p><code>Leave code...</code>'],
            ['body' => ['type' => 'custom-writer']],
            ['ellipsis']
        );

        self::assertSame(['ellipsis'], array_column($review['suggestions'], 'rule'));
        self::assertSame('<p>Custom field…</p><code>Leave code...</code>', $review['fixed']['body']);
    }

    public function testFieldsOptionCanIncludeCustomDecodedStructureTypesInsideBlocks(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'fields' => [
                        'include' => [
                            'types' => [
                                'custom-decoded-entries' => 'structure',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $blockPayload = json_encode([
            [
                'type'    => 'custom-review-block',
                'id'      => 'custom-review-block-1',
                'content' => [
                    'customEntries' => [
                        [
                            'entryText' => 'Custom nested field...',
                        ],
                    ],
                ],
            ],
        ]);
        self::assertIsString($blockPayload);

        $review = Proofreader::reviewFields(
            ['blocks' => $blockPayload],
            [
                'blocks' => [
                    'type'      => 'blocks',
                    'fieldsets' => [
                        'custom-review-block' => [
                            'name'   => 'Custom review block',
                            'fields' => [
                                'customEntries' => [
                                    'label'  => 'Custom entries',
                                    'type'   => 'custom-decoded-entries',
                                    'fields' => [
                                        'entryText' => ['label' => 'Entry text', 'type' => 'text'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            ['ellipsis'],
        );

        self::assertSame(
            [
                'Blocks -> Custom review block 1 -> Custom entries -> Row 1 -> Entry text',
            ],
            array_column($review['suggestions'], 'pathLabel')
        );

        $fixed = json_decode($review['fixed']['blocks'], associative: true);

        self::assertIsArray($fixed);
        self::assertSame('Custom nested field…', $fixed[0]['content']['customEntries'][0]['entryText']);
    }

    public function testFieldsOptionCanExcludeDefaultFieldNamesAndTypes(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'fields' => [
                        'exclude' => [
                            'names' => ['summary'],
                            'types' => ['writer'],
                        ],
                    ],
                ],
            ],
        ]);

        $review = Proofreader::reviewFields(
            [
                'summary' => 'Default text...',
                'body'    => '<p>Default writer...</p>',
            ],
            [
                'summary' => ['type' => 'text'],
                'body'    => ['type' => 'writer'],
            ],
            ['ellipsis']
        );

        self::assertSame([], $review['suggestions']);
        self::assertSame('Default text...', $review['fixed']['summary']);
        self::assertSame('<p>Default writer...</p>', $review['fixed']['body']);
    }

    // -------------------------------------------------------------------------
    // protect option
    // -------------------------------------------------------------------------

    public function testProtectPhonePresetPreventsDashConversionOnChainedNumbers(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'protect' => ['phone' => true],
                ],
            ],
        ]);

        // Three-group domestic number — must stay unchanged
        self::assertSame(
            '0800-123-4567',
            Proofreader::fix('0800-123-4567', ['dashes', 'spaces'])
        );
    }

    public function testProtectPhonePresetPreservesInternationalNumbers(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'protect' => ['phone' => true],
                ],
            ],
        ]);

        self::assertSame(
            '+49 89 1234-5678',
            Proofreader::fix('+49 89 1234-5678', ['dashes', 'spaces'])
        );
    }

    public function testProtectPhonePresetKeepsReviewSuggestionsInSyncWithFixedValues(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'protect' => ['phone' => true],
                ],
            ],
        ]);

        $review = Proofreader::reviewFields(
            ['phone' => '0800-123-4567'],
            ['phone' => ['type' => 'text']],
            ['dashes', 'spaces']
        );

        self::assertSame([], $review['suggestions']);
        self::assertSame('0800-123-4567', $review['fixed']['phone']);
    }

    public function testProtectPhonePresetDoesNotAffectTwoGroupYearRanges(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'protect' => ['phone' => true],
                ],
            ],
        ]);

        $rangeSpace = "\u{2006}";

        // Two-group range (year) must still receive en dash
        self::assertSame(
            "2010{$rangeSpace}–{$rangeSpace}2020",
            Proofreader::fix('2010-2020', ['dashes', 'spaces'])
        );
    }

    public function testProtectCustomRegexPatternPreservesMatchedSpans(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'protect' => ['skuPattern' => '/\bSKU-\d+-\d+\b/u'],
                ],
            ],
        ]);

        // SKU code must pass through dashes unchanged; surrounding text is fixed
        $result = Proofreader::fix('Order SKU-100-200 from 2020-2025', ['dashes', 'spaces']);

        $rangeSpace = "\u{2006}";

        self::assertStringContainsString('SKU-100-200', $result);
        self::assertStringContainsString("2020{$rangeSpace}–{$rangeSpace}2025", $result);
    }

    public function testInvalidProtectRegexPatternIsIgnored(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'protect' => ['brokenPattern' => '/[invalid/u'],
                ],
            ],
        ]);

        self::assertSame('Text…', Proofreader::fix('Text...', ['ellipsis']));
    }

    public function testProtectDisabledPresetIsSkipped(): void
    {
        $this->bootKirby([
            'options' => [
                'grommasdietz.proofreader' => [
                    'protect' => ['phone' => false],
                ],
            ],
        ]);

        $rangeSpace = "\u{2006}";

        // With preset disabled, chained numbers still get en-dash treatment
        self::assertSame(
            "0800{$rangeSpace}–{$rangeSpace}123{$rangeSpace}–{$rangeSpace}4567",
            Proofreader::fix('0800-123-4567', ['dashes', 'spaces'])
        );
    }
}
