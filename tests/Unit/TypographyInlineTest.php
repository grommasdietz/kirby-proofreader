<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader\Tests\Unit;

use GrommasDietz\Proofreader\Proofreader;
use GrommasDietz\Proofreader\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class TypographyInlineTest extends TestCase
{
    #[DataProvider('inlineCases')]
    public function testInlineFormattingPreservesNumericAndOperatorOwnership(string $input, string $expected, array $rules): void
    {
        $this->bootKirby(['options' => ['grommasdietz.proofreader.rules' => ['dashes', 'spaces', 'dimensions']]]);
        self::assertSame($expected, Proofreader::fixHtml($input, $rules));
        self::assertSame($expected, Proofreader::fixHtml($expected, $rules));
    }

    public static function inlineCases(): array
    {
        return [
            'formatted left endpoint' => ['<p><em>2010</em> – 2020</p>', "<p><em>2010</em>\u{202F}–\u{202F}2020</p>", ['spaces']],
            'formatted endpoints' => ['<p><em>2010</em>–<strong>2020</strong></p>', "<p><em>2010</em>\u{202F}–\u{202F}<strong>2020</strong></p>", ['spaces']],
            'formatted operator' => ['<p>2010<em>-</em>2020</p>', "<p>2010<em>\u{202F}–\u{202F}</em>2020</p>", ['dashes', 'spaces']],
            'characters only' => ['<p><em>2010</em> - <strong>2020</strong></p>', '<p><em>2010</em> – <strong>2020</strong></p>', ['dashes']],
            'dimension chain' => ['<p><em>50</em> x <strong>60</strong> x 70 cm</p>', "<p><em>50</em>\u{202F}×\u{202F}<strong>60</strong>\u{202F}×\u{202F}70 cm</p>", ['dimensions']],
            'separate paragraphs' => ['<p>2010</p><p>– 2020</p>', '<p>2010</p><p>– 2020</p>', ['spaces']],
            'language boundary' => ['<p><em lang="de">2010</em> – 2020</p>', '<p><em lang="de">2010</em> – 2020</p>', ['spaces']],
            'code boundary' => ['<p>2010<code>-</code>2020</p>', '<p>2010<code>-</code>2020</p>', ['dashes', 'spaces']],
        ];
    }

    public function testProtectionWorksAcrossInlineNodes(): void
    {
        $this->bootKirby(['options' => ['grommasdietz.proofreader' => [
            'rules' => ['dashes', 'spaces', 'dimensions'],
            'protect' => ['literal' => '~\[\[.*?\]\]~u'],
        ]]]);
        $protected = '<p>[[<em>2010</em>-2020; 50 x <strong>60</strong>]]</p>';
        self::assertSame($protected, Proofreader::fixHtml($protected));
        self::assertSame($protected . "<p>2010\u{202F}–\u{202F}2020</p>", Proofreader::fixHtml($protected . '<p>2010-2020</p>'));
        $mixed = '<p>[[2010-2020 <em lang="de">50 x 60 x 70 cm</em>]]</p>';
        self::assertSame($mixed, Proofreader::fixHtml($mixed, ['dashes', 'dimensions']));
    }

    public function testDimensionsHonorExplicitUnitSpacingAcrossInlineNodes(): void
    {
        $this->bootKirby(['options' => [
            'smartypants' => ['space.unit' => '&#8239;'],
            'grommasdietz.proofreader.rules' => ['dimensions'],
        ]]);
        $input = '<p><em>50</em> cm x <strong>60</strong> cm x 70 cm</p>';
        $expected = "<p><em>50</em>\u{202F}cm\u{202F}×\u{202F}<strong>60</strong>\u{202F}cm\u{202F}×\u{202F}70\u{202F}cm</p>";
        self::assertSame($expected, Proofreader::fixHtml($input));
        self::assertSame("50\u{202F}×\u{202F}60\u{202F}×\u{202F}70\u{202F}cm", Proofreader::fixDimensions('50x60x70cm'));
    }

    public function testDimensionChainsRecognizeUnitsWithoutChangingTheirLetters(): void
    {
        foreach (['50 x 60 x 70 cm' => "50\u{202F}×\u{202F}60\u{202F}×\u{202F}70 cm", '5 px x 6 px x 7 px' => "5 px\u{202F}×\u{202F}6 px\u{202F}×\u{202F}7 px"] as $input => $expected) {
            self::assertSame($expected, Proofreader::fixDimensions($input));
            self::assertSame($expected, Proofreader::fixDimensions($expected));
        }
        foreach (['example x axis', 'A5x6B', '5x6th'] as $input) {
            self::assertSame($input, Proofreader::fixDimensions($input));
        }
    }

    public function testReviewAndFixApplyTheSameChangesAcrossInlineFormatting(): void
    {
        $this->bootKirby(['options' => ['grommasdietz.proofreader.rules' => ['dashes', 'spaces', 'dimensions']]]);
        $input = '<p><em>2010</em>-2020; <strong>50</strong> x 60 x 70 cm</p>';
        $review = Proofreader::reviewFields(['body' => $input], ['body' => ['type' => 'writer']]);
        self::assertSame(Proofreader::fixHtml($input), $review['fixed']['body']);
        self::assertSame(['dashes', 'spaces', 'dimensions'], array_column($review['suggestions'], 'rule'));
    }
}
