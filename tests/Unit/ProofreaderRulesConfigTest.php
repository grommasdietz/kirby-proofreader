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

        $this->assertSame(
            'Text «quoted» and ‹aside›',
            Proofreader::fixQuotes('Text "quoted" and \'aside\'')
        );
        $this->assertSame(
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

        $this->assertSame(
            ['dashes', 'ellipsis'],
            array_column(Proofreader::rulesForPanel(), 'name')
        );
        $this->assertSame(
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

        $this->assertSame(
            ['unicode', 'ellipsis', 'quotes', 'apostrophes', 'dashes', 'spaces', 'dimensions'],
            array_column(Proofreader::rulesForPanel(), 'name')
        );
        $this->assertSame(
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

        $this->assertSame(
            ['customTrademark', 'customCopyright', 'noMatches'],
            array_column(Proofreader::rulesForPanel(), 'name')
        );
        $this->assertSame(
            ['Trademark', 'Copyright', 'No matches'],
            array_column(Proofreader::rulesForPanel(), 'label')
        );
        $this->assertSame(
            ['customTrademark', 'customCopyright'],
            array_column($review['suggestions'], 'rule')
        );
        $this->assertSame('Label™ ©', $review['fixed']['subtitle']);
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

        $this->assertSame('Client', $review['suggestions'][0]['fieldLabel']);
        $this->assertSame('Client', $review['suggestions'][0]['pathLabel']);
    }
}
