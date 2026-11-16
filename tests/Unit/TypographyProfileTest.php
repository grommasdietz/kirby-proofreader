<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader\Tests\Unit;

use GrommasDietz\Proofreader\Proofreader;
use GrommasDietz\Proofreader\Typography;
use GrommasDietz\Proofreader\Tests\TestCase;
use Kirby\Cms\Language;

final class TypographyProfileTest extends TestCase
{
    public function testDefinitionsKeepCharactersSeparateFromSpacing(): void
    {
        $profile = Typography::fromOptions();
        self::assertSame(['character' => '–', 'before' => "\u{202F}", 'after' => "\u{202F}"], $profile->definition('range'));
        self::assertSame("\u{2009}–\u{2009}", $profile->clipboard('word'));
        self::assertSame("\u{200A}—\u{200A}", $profile->clipboard('thought'));
        self::assertSame("\u{202F}×\u{202F}", $profile->clipboard('dimension'));
    }

    public function testDefinitionOverridesGlobalsAndRetainsExplicitEmptyValues(): void
    {
        $this->bootKirby(['options' => ['smartypants' => [
            'space.range' => '', 'space.emdash' => '&thinsp;', 'space.unit' => '&#8239;',
            'doublequote.open' => '&laquo;', 'doublequote.close' => '&raquo;',
        ]]]);
        $definition = ['code' => 'de', 'smartypants' => ['space.emdash' => '&hairsp;']];
        $profile = Typography::forDefinition($definition);
        self::assertSame('–', $profile->clipboard('range'));
        self::assertSame("\u{200A}—\u{200A}", $profile->clipboard('thought'));
        self::assertSame("\u{202F}", $profile->definition('unit')['before']);
        self::assertSame(['«', '»', '‚', '‘'], $profile->quotes());
    }

    public function testLocaleAliasesUseExactLanguageDefinition(): void
    {
        $this->bootKirby(['options' => ['smartypants' => ['space.range' => '']]]);
        $this->kirby->languages(false)->append(new Language([
            'code' => 'de-ch', 'locale' => 'de_CH.UTF-8',
            'smartypants' => ['space.range' => '&#8239;', 'space.emdash' => '&thinsp;'],
        ]));
        self::assertSame("\u{202F}–\u{202F}", Typography::forLanguage('de_CH')->clipboard('range'));
        self::assertSame('–', Typography::forLanguage('en')->clipboard('range'));
    }

    public function testProfilesAreSnapshotsAndOperationsDoNotReuseOldAppSettings(): void
    {
        $this->bootKirby(['options' => ['smartypants' => ['space.range' => '']]]);
        $profile = Typography::forLanguage('en');
        self::assertSame('2010–2020', Proofreader::fix('2010-2020', ['dashes', 'spaces'], 'en'));
        $this->bootKirby(['options' => ['smartypants' => ['space.range' => '&#8239;']]]);
        self::assertSame('–', $profile->clipboard('range'));
        self::assertSame("2010\u{202F}–\u{202F}2020", Proofreader::fix('2010-2020', ['dashes', 'spaces'], 'en'));
    }

    public function testConfiguredEnThoughtDashesKeepTheirOwnSpacing(): void
    {
        $this->bootKirby(['options' => ['smartypants' => [
            'emdash' => '&ndash;', 'space.range' => '&#8239;',
            'space.endash' => '&thinsp;', 'space.emdash' => '&hairsp;',
        ]]]);
        self::assertSame(
            "2010\u{202F}–\u{202F}2020; Wort\u{200A}–\u{200A}Wort",
            Proofreader::fixDashes('2010-2020; Wort - Wort')
        );
        self::assertSame("\u{200A}–\u{200A}", Typography::forLanguage()->clipboard('thought'));
    }

    public function testFailedCustomRulesDoNotLeaveOldProfilesInLaterOperations(): void
    {
        $this->bootKirby(['options' => [
            'smartypants' => ['space.range' => '&#8239;'],
            'grommasdietz.proofreader.rules' => ['dashes', 'spaces', 'failing' => [
                'callback' => static fn (): never => throw new \RuntimeException('Custom rule failed.'),
            ]],
        ]]);
        try {
            Proofreader::fix('2010-2020');
            self::fail('The configured rule should throw.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Custom rule failed.', $exception->getMessage());
        }
        $this->bootKirby(['options' => ['smartypants' => ['space.range' => '']]]);
        self::assertSame('2010–2020', Proofreader::fix('2010-2020', ['dashes', 'spaces']));
    }
}
