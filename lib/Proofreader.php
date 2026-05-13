<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader;

use Kirby\Cms\App;
use Kirby\Cms\Blueprint;
use Kirby\Data\Yaml;
use Kirby\Toolkit\I18n;

final class Proofreader
{
    /**
     * Deterministic rule order. Later rules see the output of earlier rules.
     *
     * @var list<string>
     */
    private const BUILTIN_RULES = ['unicode', 'ellipsis', 'quotes', 'apostrophes', 'dashes', 'spaces', 'dimensions'];

    /**
     * Regex patterns for built-in protect presets.
     *
     * 'phone' covers:
     *   - International notation: +XX with at least 6 more digits/separators
     *   - Domestic chained notation: 3 or more hyphen-separated groups (e.g. 0800-123-4567)
     *     Two-group sequences (e.g. 2010-2020) are deliberately left unprotected so
     *     the dashes rule can still convert year ranges.
     *
     * @var array<string, list<string>>
     */
    private const BUILTIN_PROTECT_PATTERNS = [
        'phone' => [
            '/(?<!\d)\+\d[\d\p{Zs}\t\-\(\)\.]{6,20}(?![\d\p{L}])/u',
            '/(?<!\d)\d{2,}(?:-\d{2,}){2,}(?![\d\p{L}])/u',
        ],
    ];
    private const DEFAULT_RULES = ['unicode', 'ellipsis', 'quotes', 'apostrophes', 'dashes', 'spaces'];
    private const EN_DASH_SPACE = "\u{2006}";
    private const EM_DASH_SPACE = "\u{200A}";
    private const MULTIPLICATION_SPACE = "\u{2006}";
    private const APOSTROPHE = '’';

    /**
     * @var array{range:string, break:string}
     */
    private const DASH_CHARACTERS = ['range' => '–', 'break' => '—'];

    /**
     * Locale-aware dash spacing definitions. Kirby's language definitions don't
     * expose typography spacing conventions, so this stays plugin-owned.
     *
     * @var array<string, array{range:string, break:string}>
     */
    private const DASH_SPACING = [
        'default' => ['range' => self::EN_DASH_SPACE, 'break' => self::EM_DASH_SPACE],
        'en'      => ['range' => self::EN_DASH_SPACE, 'break' => self::EM_DASH_SPACE],
        'de'      => ['range' => self::EN_DASH_SPACE, 'break' => self::EM_DASH_SPACE],
        'fr'      => ['range' => self::EN_DASH_SPACE, 'break' => self::EM_DASH_SPACE],
    ];

    /**
     * Fallback map for common Latin decomposed sequences when ext-intl or the
     * Symfony normalizer polyfill is unavailable.
     *
     * @var array<string, string>
     */
    private const COMPOSED_DIACRITICS = [
        "A\u{0300}" => 'À',
        "A\u{0301}" => 'Á',
        "A\u{0302}" => 'Â',
        "A\u{0303}" => 'Ã',
        "A\u{0308}" => 'Ä',
        "A\u{030A}" => 'Å',
        "C\u{0327}" => 'Ç',
        "E\u{0300}" => 'È',
        "E\u{0301}" => 'É',
        "E\u{0302}" => 'Ê',
        "E\u{0308}" => 'Ë',
        "I\u{0300}" => 'Ì',
        "I\u{0301}" => 'Í',
        "I\u{0302}" => 'Î',
        "I\u{0308}" => 'Ï',
        "N\u{0303}" => 'Ñ',
        "O\u{0300}" => 'Ò',
        "O\u{0301}" => 'Ó',
        "O\u{0302}" => 'Ô',
        "O\u{0303}" => 'Õ',
        "O\u{0308}" => 'Ö',
        "U\u{0300}" => 'Ù',
        "U\u{0301}" => 'Ú',
        "U\u{0302}" => 'Û',
        "U\u{0308}" => 'Ü',
        "Y\u{0301}" => 'Ý',
        "a\u{0300}" => 'à',
        "a\u{0301}" => 'á',
        "a\u{0302}" => 'â',
        "a\u{0303}" => 'ã',
        "a\u{0308}" => 'ä',
        "a\u{030A}" => 'å',
        "c\u{0327}" => 'ç',
        "e\u{0300}" => 'è',
        "e\u{0301}" => 'é',
        "e\u{0302}" => 'ê',
        "e\u{0308}" => 'ë',
        "i\u{0300}" => 'ì',
        "i\u{0301}" => 'í',
        "i\u{0302}" => 'î',
        "i\u{0308}" => 'ï',
        "n\u{0303}" => 'ñ',
        "o\u{0300}" => 'ò',
        "o\u{0301}" => 'ó',
        "o\u{0302}" => 'ô',
        "o\u{0303}" => 'õ',
        "o\u{0308}" => 'ö',
        "u\u{0300}" => 'ù',
        "u\u{0301}" => 'ú',
        "u\u{0302}" => 'û',
        "u\u{0308}" => 'ü',
        "y\u{0301}" => 'ý',
        "y\u{0308}" => 'ÿ',
    ];

    /**
     * Field type formats used by the default traversal. Configured includes
     * can reuse these formats for custom field names and field types.
     *
     * @var array<string, string>
     */
    private const FIELD_FORMATS = [
        'text'      => 'plain',
        'textarea'  => 'plain',
        'writer'    => 'html',
        'list'      => 'html',
        'structure' => 'structure',
        'blocks'    => 'blocks',
        'layout'    => 'layout',
    ];

    /**
     * @var array<string, string>
     */
    private const FIELD_FORMAT_ALIASES = [
        'plain'     => 'plain',
        'text'      => 'plain',
        'textarea'  => 'plain',
        'html'      => 'html',
        'writer'    => 'html',
        'list'      => 'html',
        'structure' => 'structure',
        'blocks'    => 'blocks',
        'layout'    => 'layout',
    ];
    private const PROTECTED_HTML_TAG_PATTERN = 'code|pre|kbd|samp|script|style|math';

    /**
     * Locale quote definitions: double open/close, single open/close.
     *
     * @var array<string, array{0:string, 1:string, 2:string, 3:string}>
     */
    private const QUOTES = [
        'default' => ['“', '”', '‘', '’'],
        'en'      => ['“', '”', '‘', '’'],
        'de'      => ['„', '“', '‚', '‘'],
        'fr'      => ["«\u{202F}", "\u{202F}»", '‹', '›'],
    ];

    /**
     * Words that should never get automatic NBSPs, even in sentence-start or
     * paragraph-end contexts.
     *
     * @var list<string>
     */
    private const NBSP_EXCLUDED_WORDS = ['and', 'be', 'for'];
    private const DIMENSION_UNITS = [
        'mm',
        'cm',
        'dm',
        'm',
        'km',
        'µm',
        'um',
        'nm',
        'px',
        'pt',
        'rem',
        'em',
        'in',
        'ft',
    ];

    /**
     * Composes decomposed Unicode sequences (e.g. "a" + diaeresis) into their
     * canonical single-codepoint form where possible.
     */
    public static function fixUnicodeComposition(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        if (class_exists(\Normalizer::class)) {
            $normalised = \Normalizer::normalize($text, \Normalizer::FORM_C);

            if (is_string($normalised)) {
                return $normalised;
            }
        }

        return strtr($text, self::COMPOSED_DIACRITICS);
    }

    /**
     * Replaces three or more consecutive dots (with optional leading space)
     * with the proper ellipsis character.
     */
    public static function fixEllipsis(string $text): string
    {
        return preg_replace('/[ \t]*\.{3,}/u', '…', $text) ?? $text;
    }

    /**
     * Replaces hyphens/dashes with the correct typographic dash and spacing.
     *
     * - Numeric context: en dash with six-per-em spaces (U+2006)
     * - Word context: em dash with hair spaces (U+200A)
     *
     * Already-correct en/em-dashes are also normalised if the context
     * dictates a different dash.
     */
    public static function fixDashes(string $text, ?string $language = null): string
    {
        return self::fixDashSpacing(self::fixDashCharacters($text, $language), $language);
    }

    /**
     * Replaces hyphens/dashes with the correct dash character only and keeps
     * existing spacing for the spaces rule.
     */
    public static function fixDashCharacters(string $text, ?string $language = null): string
    {
        $dash = self::dashCharactersForLanguage($language);

        $result = preg_replace_callback(
            '/(?<![\p{L}\p{N}])(\d+(?:[\p{Zs}\t]*[-–—][\p{Zs}\t]*\d+)+)(?![\p{L}\p{N}])/u',
            static function (array $m) use ($dash): string {
                return preg_replace_callback(
                    '/([\p{Zs}\t]*)[-–—]([\p{Zs}\t]*)/u',
                    static fn (array $inner): string => $inner[1] . $dash['range'] . $inner[2],
                    $m[0]
                ) ?? $m[0];
            },
            $text
        ) ?? $text;

        $result = preg_replace_callback(
            '/(\S+)(?:([\p{Zs}\t]+)-([\p{Zs}\t]+)|([\p{Zs}\t]*)([–—])([\p{Zs}\t]*))(\S+)/u',
            static function (array $m) use ($dash): string {
                $left  = $m[1];
                $right = $m[7];
                $spaceBefore = $m[2] !== '' ? $m[2] : $m[4];
                $spaceAfter  = $m[3] !== '' ? $m[3] : $m[6];

                if (is_numeric($left) && is_numeric($right)) {
                    return $left . $spaceBefore . $dash['range'] . $spaceAfter . $right;
                }

                $existingDash = $m[5] ?? '';

                if ($existingDash === '–' || $existingDash === $dash['range']) {
                    return $left . $spaceBefore . $dash['range'] . $spaceAfter . $right;
                }

                return $left . $spaceBefore . $dash['break'] . $spaceAfter . $right;
            },
            $result
        );

        return $result ?? $text;
    }

    /**
     * Normalises spacing around existing en/em dashes.
     */
    public static function fixDashSpacing(string $text, ?string $language = null): string
    {
        $spacing = self::dashSpacingForLanguage($language);
        $dash = self::dashCharactersForLanguage($language);
        $dashPattern = implode(
            '|',
            array_map(
                static fn (string $char): string => preg_quote($char, '/'),
                array_values(array_unique(['–', '—', $dash['range'], $dash['break']]))
            )
        );

        if ($dashPattern === '') {
            return $text;
        }

        $result = preg_replace_callback(
            '/(\S)[\p{Zs}\t]*(' . $dashPattern . ')[\p{Zs}\t]*(?=\S)/u',
            static function (array $m) use ($spacing, $dash): string {
                $space = in_array($m[2], ['–', $dash['range']], true)
                    ? $spacing['range']
                    : $spacing['break'];

                return $m[1] . $space . $m[2] . $space;
            },
            $text
        );

        return $result ?? $text;
    }

    /**
     * Replaces straight quote pairs and apostrophes with language-aware marks.
     */
    public static function fixQuotes(string $text, ?string $language = null): string
    {
        return self::fixApostrophes(self::fixQuotePairs($text, $language));
    }

    /**
     * Replaces straight quote pairs with language-aware marks.
     */
    public static function fixQuotePairs(string $text, ?string $language = null): string
    {
        $quotes = self::quotesForLanguage($language);

        if ($quotes === null) {
            return $text;
        }

        $result = preg_replace_callback(
            '/"([^"\r\n]+)"/u',
            static fn (array $m): string => $quotes[0] . $m[1] . $quotes[1],
            $text
        );

        $text = $result ?? $text;

        $result = preg_replace_callback(
            "/(?<![\p{L}\p{N}])'([^'\r\n]+)'(?![\p{L}\p{N}])/u",
            static fn (array $m): string => $quotes[2] . $m[1] . $quotes[3],
            $text
        );

        return $result ?? $text;
    }

    /**
     * Replaces apostrophes in words with the typographic apostrophe.
     */
    public static function fixApostrophes(string $text): string
    {
        $protectedClosers = [];
        $text = preg_replace_callback(
            "/(?<![\p{L}\p{N}])'([^'\r\n]+)'(?![\p{L}\p{N}])/u",
            static function (array $m) use (&$protectedClosers): string {
                $token = "\u{E000}proofreader-apostrophe-" . count($protectedClosers) . "\u{E001}";
                $protectedClosers[$token] = "'";

                return "'" . $m[1] . $token;
            },
            $text
        ) ?? $text;

        $result = preg_replace(
            "/(?<=\p{L})['´‘ʼ](?=\p{L})/u",
            self::APOSTROPHE,
            $text
        );

        $text = $result ?? $text;

        $result = preg_replace(
            "/(?<=\p{L}[sS])['´‘ʼ](?=[\p{Zs}\t\r\n.,;:!?)]|$)/u",
            self::APOSTROPHE,
            $text
        );

        return strtr($result ?? $text, $protectedClosers);
    }

    /**
     * Inserts non-breaking spaces after one-letter words ("a", "I") anywhere
     * and after 2–4 letter sentence starters.
     */
    public static function fixLeadingNbsp(string $text): string
    {
        $result = preg_replace_callback(
            '/(^|[.!?]\s+)(\p{L}{2,4})[ \t]+(?=[\p{L}\p{N}])/um',
            static function (array $m): string {
                $word = $m[2];

                if (in_array(strtolower($word), self::NBSP_EXCLUDED_WORDS, true)) {
                    return $m[0];
                }

                return $m[1] . $word . "\u{00A0}";
            },
            $text
        ) ?? $text;

        $result = preg_replace(
            '/\b([aAiI])[ \t]+(?=[\p{L}\p{N}])/u',
            "$1\u{00A0}",
            $result
        );

        return $result ?? $text;
    }

    /**
     * Inserts a non-breaking space before 2–4 letter paragraph-ending words.
     */
    public static function fixTrailingNbsp(string $text): string
    {
        $result = preg_replace_callback(
            '/[ \t](\p{L}{2,4})([.!?]?)(?=$|\R)/um',
            static function (array $m): string {
                $word = $m[1];

                if (in_array(strtolower($word), self::NBSP_EXCLUDED_WORDS, true)) {
                    return $m[0];
                }

                return "\u{00A0}" . $word . $m[2];
            },
            $text
        );

        return $result ?? $text;
    }

    /**
     * Collapses repeated regular horizontal whitespace to one plain space.
     * Non-breaking typography spaces are preserved.
     */
    public static function fixRepeatedSpaces(string $text): string
    {
        $result = preg_replace('/[ \t]{2,}/u', ' ', $text);

        return $result ?? $text;
    }

    /**
     * Removes regular spaces before punctuation. French keeps spacing before
     * high punctuation marks, so only comma/period cleanup is applied there.
     */
    public static function fixPunctuationSpacing(string $text, ?string $language = null): string
    {
        $result = preg_replace('/[ \t]+([,.])/u', '$1', $text) ?? $text;

        if (self::isFrenchLanguage($language) === true) {
            return $result;
        }

        $result = preg_replace(
            '/[ \t]+([;:!?])(?=$|[\p{Zs}\t\r\n\p{P}])/u',
            '$1',
            $result
        );

        return $result ?? $text;
    }

    /**
     * Inserts a narrow no-break space (U+202F) between ordinal numbers
     * (up to three digits followed by a period) and the following word.
     *
     * Example: "3. January" → "3.\u{202F}January"
     */
    public static function fixOrdinalSpacing(string $text): string
    {
        $result = preg_replace(
            '/(?<!\d)(\d{1,3}\.)[ \t]+(?=\p{L})/u',
            "$1\u{202F}",
            $text
        );

        return $result ?? $text;
    }

    /**
     * Replaces dimension separators like "5 x 5 cm" with a multiplication sign.
     */
    public static function fixDimensions(string $text): string
    {
        $number = '\d+(?:[.,]\d+)?';
        $unitSpace = '[\p{Zs}\t]*';
        $unit = implode(
            '|',
            array_map(static fn (string $value): string => preg_quote($value, '/'), self::DIMENSION_UNITS)
        );

        $result = preg_replace_callback(
            '/(?<![\p{L}\p{N}])(' . $number . ')(' . $unitSpace . '(?:' . $unit . '))?[\p{Zs}\t]*[xX×][\p{Zs}\t]*(' . $number . ')(' . $unitSpace . '(?:' . $unit . '))?(?=$|[\p{Zs}\t\p{P}])/u',
            static function (array $m): string {
                $leftUnit = $m[2] ?? '';
                $rightUnit = $m[4] ?? '';

                return $m[1]
                    . $leftUnit
                    . self::MULTIPLICATION_SPACE
                    . '×'
                    . self::MULTIPLICATION_SPACE
                    . $m[3]
                    . $rightUnit;
            },
            $text
        );

        return $result ?? $text;
    }

    /**
     * Applies enabled typography rules in sequence.
     *
     * Spans matched by configured protect patterns are tokenised before any
     * rule runs and restored verbatim afterwards.
     *
     * @param list<string>|null $rules
     */
    public static function fix(
        string $text,
        ?array $rules = null,
        ?string $language = null
    ): string {
        [$text, $tokens] = self::tokenizeProtected($text);

        foreach (self::normaliseRules($rules) as $rule) {
            $text = self::applyRule($text, $rule, $language);
        }

        return self::restoreProtected($text, $tokens);
    }

    /**
     * Applies typography fixes to text nodes inside an HTML string.
     *
     * Tags are passed through unchanged. Text nodes outside code-like
     * protected elements are fixed with fix().
     *
     * @param list<string>|null $rules
     */
    public static function fixHtml(
        string $html,
        ?array $rules = null,
        ?string $language = null
    ): string {
        if ($html === '') {
            return $html;
        }

        $parts = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $html;
        }

        $skipDepth = 0;
        $out  = '';

        foreach ($parts as $part) {
            if (!str_starts_with($part, '<')) {
                $out .= $skipDepth > 0 ? $part : self::fix($part, $rules, $language);
                continue;
            }

            $out .= $part;

            if (preg_match('/^<\s*\/\s*(' . self::PROTECTED_HTML_TAG_PATTERN . ')\b[^>]*>/i', $part)) {
                $skipDepth = max(0, $skipDepth - 1);
                continue;
            }

            $isSelfClosing = preg_match('/\/\s*>$/', $part) === 1;

            if (
                $isSelfClosing === false &&
                preg_match('/^<\s*(' . self::PROTECTED_HTML_TAG_PATTERN . ')\b[^>]*>/i', $part)
            ) {
                $skipDepth++;
            }
        }

        return $out;
    }

    /**
     * Returns the flat list of active protect regex patterns from config.
     *
     * Each entry under `grommasdietz.proofreader.protect` is processed:
     * - `'phone' => true`              → expands all patterns from BUILTIN_PROTECT_PATTERNS['phone']
     * - `'myPattern' => '/regex/u'`    → used directly as a user-supplied pattern
     * - `'phone' => false`             → skipped (disabled)
     *
     * @return list<string>
     */
    private static function configuredProtectPatterns(): array
    {
        $config = self::optionValue('grommasdietz.proofreader.protect', []);

        if (!is_array($config) || $config === []) {
            return [];
        }

        $patterns = [];

        foreach ($config as $key => $value) {
            if ($value === false) {
                continue;
            }

            if ($value === true && is_string($key)) {
                foreach (self::BUILTIN_PROTECT_PATTERNS[$key] ?? [] as $pattern) {
                    $patterns[] = $pattern;
                }
                continue;
            }

            if (is_string($value) && $value !== '') {
                $patterns[] = $value;
            }
        }

        return array_values(array_unique($patterns));
    }

    /**
     * Replaces spans matched by active protect patterns with PUA-range tokens.
     *
     * Uses U+E002/U+E003 as delimiters, distinct from the apostrophe token
     * pair (U+E000/U+E001) used elsewhere in the class.
     *
     * @return array{string, array<string, string>}
     */
    private static function tokenizeProtected(string $text): array
    {
        $patterns = self::configuredProtectPatterns();

        if ($patterns === []) {
            return [$text, []];
        }

        $tokens = [];
        $index  = 0;

        foreach ($patterns as $pattern) {
            if ($pattern === '') {
                continue;
            }

            $text = preg_replace_callback(
                $pattern,
                static function (array $m) use (&$tokens, &$index): string {
                    $token = "\u{E002}proofreader-protect-{$index}\u{E003}";
                    $tokens[$token] = $m[0];
                    $index++;

                    return $token;
                },
                $text
            ) ?? $text;
        }

        return [$text, $tokens];
    }

    /**
     * Restores tokenised protected spans to their original values.
     *
     * @param array<string, string> $tokens
     */
    private static function restoreProtected(string $text, array $tokens): string
    {
        if ($tokens === []) {
            return $text;
        }

        return strtr($text, $tokens);
    }

    /**
     * @param list<string>|null $rules
     * @return list<string>
     */
    private static function normaliseRules(?array $rules): array
    {
        $configured = self::configuredRuleDefinitions();
        $source = $rules ?? array_keys($configured);
        $enabled = [];

        foreach ($source as $rule) {
            if (
                is_string($rule) &&
                isset($configured[$rule]) &&
                !in_array($rule, $enabled, true)
            ) {
                $enabled[] = $rule;
            }
        }

        return $enabled;
    }

    /**
     * Returns configured rule metadata for the Panel in effective execution
     * order. Built-ins use translated labels in the Panel; custom rules can
     * provide a label in their option definition.
     *
     * @return list<array{name:string, label:string|null, builtin:bool}>
     */
    public static function rulesForPanel(): array
    {
        return array_map(
            static fn (array $rule): array => [
                'name'    => $rule['name'],
                'label'   => $rule['label'],
                'builtin' => $rule['builtin'],
            ],
            array_values(self::configuredRuleDefinitions())
        );
    }

    /**
     * @return array<string, array{name:string, label:string|null, callback:callable|null, builtin:bool}>
     */
    private static function configuredRuleDefinitions(): array
    {
        $configured = self::optionValue('grommasdietz.proofreader.rules', null);

        if (!is_array($configured)) {
            return self::defaultRuleDefinitions();
        }

        $definitions = [];

        foreach ($configured as $key => $value) {
            $definition = self::ruleDefinitionFromConfig($key, $value);

            if ($definition === null) {
                continue;
            }

            if ($definition['enabled'] === false) {
                unset($definitions[$definition['name']]);
                continue;
            }

            $definitions[$definition['name']] = [
                'name'     => $definition['name'],
                'label'    => $definition['label'],
                'callback' => $definition['callback'],
                'builtin'  => $definition['builtin'],
            ];
        }

        return $definitions;
    }

    /**
     * @return array<string, array{name:string, label:string|null, callback:null, builtin:true}>
     */
    private static function defaultRuleDefinitions(): array
    {
        $definitions = [];

        foreach (self::DEFAULT_RULES as $rule) {
            $definitions[$rule] = [
                'name'     => $rule,
                'label'    => null,
                'callback' => null,
                'builtin'  => true,
            ];
        }

        return $definitions;
    }

    /**
     * @return array{name:string, label:string|null, callback:callable|null, builtin:bool, enabled:bool}|null
     */
    private static function ruleDefinitionFromConfig(int|string $key, mixed $value): ?array
    {
        $name = null;
        $label = null;
        $callback = null;
        $enabled = true;

        if (is_string($value)) {
            $name = $value;
        } elseif (is_string($key) && is_bool($value)) {
            $name = $key;
            $enabled = $value;
        } elseif (is_string($key) && is_callable($value)) {
            $name = $key;
            $callback = $value;
        } elseif (is_array($value)) {
            $nameValue = $value['name'] ?? null;
            $name = is_string($nameValue) && $nameValue !== ''
                ? $nameValue
                : (is_string($key) ? $key : null);

            $enabledValue = $value['enabled'] ?? true;
            $enabled = is_bool($enabledValue) ? $enabledValue : true;

            $labelValue = $value['label'] ?? null;
            $label = is_string($labelValue) && $labelValue !== '' ? $labelValue : null;
            $callback = self::ruleCallbackFromConfig($value);
        }

        if (!is_string($name) || $name === '') {
            return null;
        }

        $builtin = in_array($name, self::BUILTIN_RULES, true);

        if ($enabled === true && $builtin === false && $callback === null) {
            return null;
        }

        return [
            'name'     => $name,
            'label'    => $label,
            'callback' => $callback,
            'builtin'  => $builtin,
            'enabled'  => $enabled,
        ];
    }

    /**
     * @param array<string, mixed> $definition
     */
    private static function ruleCallbackFromConfig(array $definition): ?callable
    {
        if (is_callable($definition['callback'] ?? null)) {
            return $definition['callback'];
        }

        $pattern = $definition['pattern'] ?? $definition['regex'] ?? null;
        $replacement = $definition['replace'] ?? $definition['replacement'] ?? null;

        if (!is_string($pattern) || $pattern === '') {
            return null;
        }

        if (is_callable($replacement)) {
            return static function (string $text) use ($pattern, $replacement): string {
                $result = @preg_replace_callback($pattern, $replacement, $text);

                return is_string($result) ? $result : $text;
            };
        }

        if (is_string($replacement)) {
            return static function (string $text) use ($pattern, $replacement): string {
                $result = @preg_replace($pattern, $replacement, $text);

                return is_string($result) ? $result : $text;
            };
        }

        return null;
    }

    private static function applyConfiguredRuleCallback(
        string $text,
        string $rule,
        ?string $language
    ): ?string {
        $callback = self::configuredRuleDefinitions()[$rule]['callback'] ?? null;

        if (is_callable($callback) === false) {
            return null;
        }

        $result = $callback($text, $language, $rule);

        return is_string($result) ? $result : $text;
    }

    private static function optionValue(string $key, array|null $default): mixed
    {
        try {
            $app = App::instance(lazy: true);

            return $app?->option($key, $default) ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    private static function applyRule(
        string $text,
        string $rule,
        ?string $language = null
    ): string {
        $configured = self::applyConfiguredRuleCallback($text, $rule, $language);

        if ($configured !== null) {
            return $configured;
        }

        return match ($rule) {
            'unicode'     => self::fixUnicodeComposition($text),
            'ellipsis'    => self::fixEllipsis($text),
            'quotes'      => self::fixQuotePairs($text, $language),
            'apostrophes' => self::fixApostrophes($text),
            'dashes'      => self::fixDashCharacters($text, $language),
            'spaces'      => self::fixOrdinalSpacing(
                self::fixDashSpacing(
                    self::fixTrailingNbsp(
                        self::fixLeadingNbsp(
                            self::fixPunctuationSpacing(
                                self::fixRepeatedSpaces($text),
                                $language
                            )
                        )
                    ),
                    $language
                )
            ),
            'dimensions'  => self::fixDimensions($text),
            default       => $text,
        };
    }

    /**
     * @return array{range:string, break:string}
     */
    private static function dashCharactersForLanguage(?string $language): array
    {
        $options = self::smartypantsOptionsForLanguage($language);

        return [
            'range' => self::smartypantsStringOption($options, 'endash') ?? self::DASH_CHARACTERS['range'],
            'break' => self::smartypantsStringOption($options, 'emdash') ?? self::DASH_CHARACTERS['break'],
        ];
    }

    /**
     * @return array{range:string, break:string}
     */
    private static function dashSpacingForLanguage(?string $language): array
    {
        $code = self::baseLanguageCode($language !== null ? $language : 'default');
        $fallback = self::DASH_SPACING[$code] ?? self::DASH_SPACING['default'];
        $options = self::smartypantsOptionsForLanguage($language);

        return [
            'range' => self::smartypantsStringOption($options, 'space.endash') ?? $fallback['range'],
            'break' => self::smartypantsStringOption($options, 'space.emdash') ?? $fallback['break'],
        ];
    }

    /**
     * @return array{0:string, 1:string, 2:string, 3:string}|null
     */
    private static function quotesForLanguage(?string $language): ?array
    {
        $code = self::baseLanguageCode($language);
        $fallback = self::QUOTES[$code] ?? null;
        $options = self::smartypantsOptionsForLanguage($language);

        if ($fallback === null) {
            $doubleOpen = self::smartypantsStringOption($options, 'doublequote.open');
            $doubleClose = self::smartypantsStringOption($options, 'doublequote.close');
            $singleOpen = self::smartypantsStringOption($options, 'singlequote.open');
            $singleClose = self::smartypantsStringOption($options, 'singlequote.close');

            if (
                $doubleOpen === null ||
                $doubleClose === null ||
                $singleOpen === null ||
                $singleClose === null
            ) {
                return null;
            }

            return [$doubleOpen, $doubleClose, $singleOpen, $singleClose];
        }

        return [
            self::smartypantsStringOption($options, 'doublequote.open') ?? $fallback[0],
            self::smartypantsStringOption($options, 'doublequote.close') ?? $fallback[1],
            self::smartypantsStringOption($options, 'singlequote.open') ?? $fallback[2],
            self::smartypantsStringOption($options, 'singlequote.close') ?? $fallback[3],
        ];
    }

    private static function isFrenchLanguage(?string $language): bool
    {
        return self::baseLanguageCode($language) === 'fr';
    }

    private static function baseLanguageCode(?string $language): string
    {
        $code = strtolower((string) ($language ?? ''));

        return preg_replace('/[_-].*$/', '', $code) ?? $code;
    }

    /**
     * @return array<string, mixed>
     */
    private static function smartypantsOptionsForLanguage(?string $language): array
    {
        $options = self::optionValue('smartypants', []);
        $options = is_array($options) ? $options : [];

        try {
            $kirby = App::instance(lazy: true);

            if ($kirby === null || $kirby->multilang() === false) {
                return $options;
            }

            $code = self::baseLanguageCode($language);
            $languageObject = null;

            if ($code === '' || $code === 'default') {
                return $options;
            }

            $languageObject = $kirby->language($code);

            if ($languageObject === null) {
                return $options;
            }

            $languageOptions = $languageObject->smartypants();

            if (is_array($languageOptions) === false) {
                return $options;
            }

            return array_replace($options, $languageOptions);
        } catch (\Throwable) {
            return $options;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function smartypantsStringOption(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;

        if (!is_string($value) || $value === '') {
            return null;
        }

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $blueprint
     */
    private static function fieldFormatFor(string $field, array $blueprint): ?string
    {
        $type = self::fieldType($blueprint);
        $defaultFormat = $type !== null ? (self::FIELD_FORMATS[$type] ?? null) : null;
        $config = self::fieldConfig();

        if (self::fieldMatchesConfig($field, $type, $config['exclude'] ?? []) === true) {
            return null;
        }

        return self::fieldFormatFromConfig($field, $type, $defaultFormat, $config['include'] ?? [])
            ?? $defaultFormat;
    }

    /**
     * @param array<string, mixed> $blueprint
     */
    private static function fieldType(array $blueprint): ?string
    {
        $type = $blueprint['type'] ?? null;

        if (!is_string($type) || $type === '') {
            return null;
        }

        return strtolower($type);
    }

    /**
     * @return array<string, mixed>
     */
    private static function fieldConfig(): array
    {
        $config = self::optionValue('grommasdietz.proofreader.fields', []);

        return is_array($config) ? $config : [];
    }

    private static function fieldMatchesConfig(string $field, ?string $type, mixed $config): bool
    {
        if (is_array($config) === false) {
            return false;
        }

        return self::configListContains(strtolower($field), $config['names'] ?? [])
            || ($type !== null && self::configListContains($type, $config['types'] ?? []));
    }

    private static function fieldFormatFromConfig(
        string $field,
        ?string $type,
        ?string $defaultFormat,
        mixed $config
    ): ?string {
        if (is_array($config) === false) {
            return null;
        }

        $nameFormat = self::fieldFormatFromConfigList(strtolower($field), $config['names'] ?? [], $defaultFormat);

        if ($nameFormat !== null) {
            return $nameFormat;
        }

        if ($type === null) {
            return null;
        }

        return self::fieldFormatFromConfigList($type, $config['types'] ?? [], $defaultFormat);
    }

    private static function configListContains(string $needle, mixed $entries): bool
    {
        if (is_array($entries) === false) {
            return false;
        }

        foreach ($entries as $key => $value) {
            if (is_int($key)) {
                if (is_string($value) && strtolower($value) === $needle) {
                    return true;
                }

                continue;
            }

            if (strtolower((string) $key) === $needle && $value !== false) {
                return true;
            }
        }

        return false;
    }

    private static function fieldFormatFromConfigList(
        string $needle,
        mixed $entries,
        ?string $defaultFormat
    ): ?string {
        if (is_array($entries) === false) {
            return null;
        }

        foreach ($entries as $key => $value) {
            if (is_int($key)) {
                if (is_string($value) && strtolower($value) === $needle) {
                    return self::normaliseFieldFormat(true, $defaultFormat);
                }

                continue;
            }

            if (strtolower((string) $key) === $needle) {
                return self::normaliseFieldFormat($value, $defaultFormat);
            }
        }

        return null;
    }

    /**
     * @param true|string $value
     */
    private static function normaliseFieldFormat(mixed $value, ?string $defaultFormat): ?string
    {
        if ($value === true) {
            return $defaultFormat ?? 'plain';
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        return self::FIELD_FORMAT_ALIASES[strtolower($value)] ?? null;
    }

    /**
     * Applies typography fixes to eligible fields in a content array.
     *
     * Supported field types and how they are processed:
     * - text, textarea  → plain-text fix via fix()
     * - writer, list    → HTML-aware fix via fixHtml()
     * - structure       → YAML decoded, each row processed recursively
     * - blocks          → JSON decoded, each block's content processed recursively
     * - layout          → JSON decoded, blocks in each column processed recursively
     *
     * Custom field names and field types can opt into one of the same formats
     * through the `grommasdietz.proofreader.fields.include` option. Excludes
     * always win. All other field types (url, email, date, select, toggle, …)
     * are returned unchanged. Fields absent from the blueprint are also passed
     * through unless included by name.
     *
     * @param  array<string, mixed>                $fields          Content key-value pairs
     * @param  array<string, array<string, mixed>> $blueprintFields Blueprint field definitions indexed by field name
     * @param  list<string>|null                   $rules           Enabled rule names
     * @param  list<string>|null                   $onlyFields      Top-level field names to process
     * @return array<string, mixed>
     */
    public static function fixFields(
        array $fields,
        array $blueprintFields,
        ?array $rules = null,
        ?string $language = null,
        ?array $onlyFields = null
    ): array {
        $blueprintFields = self::normaliseBlueprintFields($blueprintFields, true);
        $fixed = [];
        $selectedFields = $onlyFields === null
            ? null
            : array_map(static fn (string $field): string => strtolower($field), $onlyFields);

        foreach ($fields as $key => $value) {
            $lkey = strtolower((string) $key);
            $bp = $blueprintFields[$lkey] ?? [];

            if ($selectedFields !== null && !in_array($lkey, $selectedFields, true)) {
                $fixed[$key] = $value;
                continue;
            }

            $format = self::fieldFormatFor((string) $key, $bp);

            if ($format !== null) {
                $fixed[$key] = self::fixFieldValue($value, $format, $bp, $rules, $language);
            } else {
                $fixed[$key] = $value;
            }
        }

        return $fixed;
    }

    /**
     * Builds editor-facing suggestions and the resulting fixed content.
     *
     * @param  array<string, mixed>                $fields
     * @param  array<string, array<string, mixed>> $blueprintFields
     * @param  list<string>|null                   $rules
     * @param  list<string>|null                   $onlyFields
     * @return array{rules:list<string>, suggestions:list<array<string, string>>, fixed:array<string, mixed>}
     */
    public static function reviewFields(
        array $fields,
        array $blueprintFields,
        ?array $rules = null,
        ?string $language = null,
        ?array $onlyFields = null
    ): array {
        $enabledRules = self::normaliseRules($rules);

        return [
            'rules'       => $enabledRules,
            'suggestions' => self::collectFieldSuggestions(
                $fields,
                $blueprintFields,
                $enabledRules,
                $language,
                $onlyFields
            ),
            'fixed'       => self::fixFields(
                $fields,
                $blueprintFields,
                $enabledRules,
                $language,
                $onlyFields
            ),
        ];
    }

    /**
     * @param  array<string, mixed>                $fields
     * @param  array<string, array<string, mixed>> $blueprintFields
     * @param  list<string>                        $rules
     * @param  list<string>|null                   $onlyFields
     * @return list<array<string, string>>
     */
    private static function collectFieldSuggestions(
        array $fields,
        array $blueprintFields,
        array $rules,
        ?string $language,
        ?array $onlyFields = null
    ): array {
        $blueprintFields = self::normaliseBlueprintFields($blueprintFields, true);
        $suggestions = [];
        $selectedFields = $onlyFields === null
            ? null
            : array_map(static fn (string $field): string => strtolower($field), $onlyFields);

        foreach ($fields as $key => $value) {
            $field = (string) $key;
            $lkey  = strtolower($field);
            $bp    = $blueprintFields[$lkey] ?? [];

            if ($selectedFields !== null && !in_array($lkey, $selectedFields, true)) {
                continue;
            }

            $format = self::fieldFormatFor($field, $bp);
            $label = self::fieldLabel($field, $bp);

            if ($format !== null) {
                array_push(
                    $suggestions,
                    ...self::collectFieldValueSuggestions(
                        $value,
                        $format,
                        $bp,
                        $field,
                        $label,
                        $field,
                        $label,
                        $rules,
                        $language
                    )
                );
            }
        }

        return $suggestions;
    }

    /**
     * @param array<string, mixed> $fieldBlueprint
     * @param list<string>|null $rules
     */
    private static function fixFieldValue(
        mixed $value,
        string $format,
        array $fieldBlueprint,
        ?array $rules,
        ?string $language
    ): mixed {
        return match ($format) {
            'plain'     => is_string($value) ? self::fix($value, $rules, $language) : $value,
            'html'      => is_string($value) ? self::fixHtml($value, $rules, $language) : $value,
            'structure' => self::fixStructureValue($value, $fieldBlueprint, $rules, $language),
            'blocks'    => self::fixBlocksValue($value, $fieldBlueprint, $rules, $language),
            'layout'    => self::fixLayoutValue($value, $fieldBlueprint, $rules, $language),
            default     => $value,
        };
    }

    /**
     * @param array<string, mixed> $fieldBlueprint
     * @param list<string> $rules
     * @return list<array<string, string>>
     */
    private static function collectFieldValueSuggestions(
        mixed $value,
        string $format,
        array $fieldBlueprint,
        string $field,
        string $fieldLabel,
        string $path,
        string $pathLabel,
        array $rules,
        ?string $language
    ): array {
        if (($format === 'plain' || $format === 'html') && is_string($value)) {
            return self::buildRuleSuggestions(
                $value,
                $format,
                $field,
                $fieldLabel,
                $path,
                $pathLabel,
                $rules,
                $language
            );
        }

        return match ($format) {
            'structure' => self::collectStructureSuggestions(
                $value,
                $fieldBlueprint,
                $field,
                $fieldLabel,
                $path,
                $pathLabel,
                $rules,
                $language
            ),
            'blocks' => self::collectBlocksSuggestions(
                $value,
                $fieldBlueprint,
                $field,
                $fieldLabel,
                $path,
                $pathLabel,
                $rules,
                $language
            ),
            'layout' => self::collectLayoutSuggestions(
                $value,
                $fieldBlueprint,
                $field,
                $fieldLabel,
                $path,
                $pathLabel,
                $rules,
                $language
            ),
            default     => [],
        };
    }

    /**
     * @param list<string> $rules
     * @return list<array<string, string>>
     */
    private static function buildRuleSuggestions(
        string $value,
        string $format,
        string $field,
        string $fieldLabel,
        string $path,
        string $pathLabel,
        array $rules,
        ?string $language
    ): array {
        $suggestions = [];
        $current = $value;

        foreach ($rules as $rule) {
            $next = $format === 'html'
                ? self::fixHtml($current, [$rule], $language)
                : self::applyRule($current, $rule, $language);

            if ($next !== $current) {
                $suggestions[] = [
                    'id'            => sha1($path . '|' . $rule . '|' . $current . '|' . $next),
                    'rule'          => $rule,
                    'field'         => $field,
                    'fieldLabel'    => $fieldLabel,
                    'path'          => $path,
                    'pathLabel'     => $pathLabel,
                    'format'        => $format,
                    'previewBefore' => self::previewText($current, $format),
                    'previewAfter'  => self::previewText($next, $format),
                ];

                $current = $next;
            }
        }

        return $suggestions;
    }

    /**
     * @param  array<string, mixed> $fieldBlueprint
     * @param  list<string>         $rules
     * @return list<array<string, string>>
     */
    private static function collectStructureSuggestions(
        mixed $value,
        array $fieldBlueprint,
        string $field,
        string $fieldLabel,
        string $basePath,
        string $baseLabel,
        array $rules,
        ?string $language
    ): array {
        $rows      = is_string($value) ? Yaml::decode($value) : $value;
        $subFields = self::normaliseBlueprintFields($fieldBlueprint['fields'] ?? []);

        if (!is_array($rows) || $subFields === []) {
            return [];
        }

        $suggestions = [];

        foreach (array_values($rows) as $rowIndex => $row) {
            foreach ((array) $row as $subKey => $subValue) {
                $subField = (string) $subKey;
                $subBp    = $subFields[strtolower($subField)] ?? [];
                $format   = self::fieldFormatFor($subField, $subBp);

                if ($format === null) {
                    continue;
                }

                $subLabel = self::fieldLabel($subField, $subBp);
                $path = $basePath . '.' . $rowIndex . '.' . $subField;
                $pathLabel = $baseLabel . ' -> Row ' . ($rowIndex + 1) . ' -> ' . $subLabel;

                array_push(
                    $suggestions,
                    ...self::collectFieldValueSuggestions(
                        $subValue,
                        $format,
                        $subBp,
                        $field,
                        $fieldLabel,
                        $path,
                        $pathLabel,
                        $rules,
                        $language
                    )
                );
            }
        }

        return $suggestions;
    }

    /**
     * @param  array<string, mixed> $fieldBlueprint
     * @param  list<string>         $rules
     * @return list<array<string, string>>
     */
    private static function collectBlocksSuggestions(
        mixed $value,
        array $fieldBlueprint,
        string $field,
        string $fieldLabel,
        string $basePath,
        string $baseLabel,
        array $rules,
        ?string $language
    ): array {
        $blocks = is_string($value) ? json_decode($value, associative: true) : $value;

        if (!is_array($blocks)) {
            return [];
        }

        /** @var list<array<string, mixed>> $blocks */
        $blocks = array_values($blocks);

        return self::collectBlockArraySuggestions(
            $blocks,
            $fieldBlueprint['fieldsets'] ?? [],
            $field,
            $fieldLabel,
            $basePath,
            $baseLabel,
            $rules,
            $language
        );
    }

    /**
     * @param  array<string, mixed> $fieldBlueprint
     * @param  list<string>         $rules
     * @return list<array<string, string>>
     */
    private static function collectLayoutSuggestions(
        mixed $value,
        array $fieldBlueprint,
        string $field,
        string $fieldLabel,
        string $basePath,
        string $baseLabel,
        array $rules,
        ?string $language
    ): array {
        $layout = is_string($value) ? json_decode($value, associative: true) : $value;

        if (!is_array($layout)) {
            return [];
        }

        $suggestions = [];

        foreach (array_values($layout) as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }

            $columns = $row['columns'] ?? [];
            if (!is_array($columns)) {
                continue;
            }

            foreach (array_values($columns) as $colIndex => $column) {
                if (!is_array($column)) {
                    continue;
                }

                $blocks = $column['blocks'] ?? [];
                if (!is_array($blocks)) {
                    continue;
                }

                /** @var list<array<string, mixed>> $blockList */
                $blockList = array_values($blocks);

                array_push(
                    $suggestions,
                    ...self::collectBlockArraySuggestions(
                        $blockList,
                        $fieldBlueprint['fieldsets'] ?? [],
                        $field,
                        $fieldLabel,
                        $basePath . '.' . $rowIndex . '.columns.' . $colIndex . '.blocks',
                        $baseLabel . ' -> Layout ' . ($rowIndex + 1) . '.' . ($colIndex + 1),
                        $rules,
                        $language
                    )
                );
            }
        }

        return $suggestions;
    }

    /**
     * @param  list<array<string, mixed>>          $blocks
     * @param  array<string, array<string, mixed>> $fieldsets
     * @param  list<string>                        $rules
     * @return list<array<string, string>>
     */
    private static function collectBlockArraySuggestions(
        array $blocks,
        array $fieldsets,
        string $field,
        string $fieldLabel,
        string $basePath,
        string $baseLabel,
        array $rules,
        ?string $language
    ): array {
        $suggestions = [];

        foreach (array_values($blocks) as $blockIndex => $block) {
            $type = (string) ($block['type'] ?? '');
            $fieldDefs = self::fieldsetFields($fieldsets, $type);

            if ($fieldDefs === [] || !isset($block['content'])) {
                continue;
            }

            $blockLabel = self::blockLabel($type, $fieldsets[$type] ?? []);

            foreach ((array) $block['content'] as $subKey => $subValue) {
                $subField = (string) $subKey;
                $subBp    = $fieldDefs[strtolower($subField)] ?? [];
                $format   = self::fieldFormatFor($subField, $subBp);

                if ($format === null) {
                    continue;
                }

                $subLabel = self::fieldLabel($subField, $subBp);
                $path = $basePath . '.' . $blockIndex . '.content.' . $subField;
                $pathLabel = $baseLabel . ' -> ' . $blockLabel . ' ' . ($blockIndex + 1) . ' -> ' . $subLabel;

                array_push(
                    $suggestions,
                    ...self::collectFieldValueSuggestions(
                        $subValue,
                        $format,
                        $subBp,
                        $field,
                        $fieldLabel,
                        $path,
                        $pathLabel,
                        $rules,
                        $language
                    )
                );
            }
        }

        return $suggestions;
    }

    /**
     * @param  array<string|int, mixed> $fieldsets
     * @return array<string, array<string, mixed>>
     */
    private static function fieldsetFields(array $fieldsets, string $type): array
    {
        $fieldset = self::fieldsetDefinition($fieldsets, $type);
        $fieldDefs = $fieldset['fields'] ?? [];

        if ($fieldDefs === []) {
            foreach ($fieldset['tabs'] ?? [] as $tab) {
                $fieldDefs = array_merge($fieldDefs, $tab['fields'] ?? []);
            }
        }

        return self::normaliseBlueprintFields($fieldDefs);
    }

    /**
     * @param  array<string|int, mixed> $fieldsets
     * @return array<string, mixed>
     */
    private static function fieldsetDefinition(array $fieldsets, string $type): array
    {
        $definition = $fieldsets[$type] ?? null;

        if (is_string($definition)) {
            return self::resolveBlueprintDefinition(['extends' => $definition]);
        }

        if (is_array($definition)) {
            return self::resolveBlueprintDefinition($definition);
        }

        foreach ($fieldsets as $fieldset) {
            if ($fieldset === $type) {
                return self::resolveBlueprintDefinition(['extends' => 'blocks/' . $type]);
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed> $fields
     * @return array<string, array<string, mixed>>
     */
    private static function normaliseBlueprintFields(array $fields, bool $includePageTitle = false): array
    {
        $normalised = [];

        foreach ($fields as $key => $definition) {
            if (is_string($definition)) {
                $definition = ['extends' => $definition];
            }

            if (is_array($definition) === false) {
                continue;
            }

            $normalised[strtolower((string) $key)] = self::resolveBlueprintDefinition($definition);
        }

        if ($includePageTitle === true && !isset($normalised['title'])) {
            $title = I18n::translate('title', 'Title');

            $normalised['title'] = [
                'label' => is_string($title) ? $title : 'Title',
                'type'  => 'text',
            ];
        }

        return $normalised;
    }

    /**
     * @param  array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private static function resolveBlueprintDefinition(array $definition): array
    {
        if (isset($definition['extends']) === false) {
            return $definition;
        }

        try {
            return Blueprint::extend($definition);
        } catch (\Throwable) {
            return $definition;
        }
    }

    /**
     * @param array<string, mixed> $blueprint
     */
    private static function fieldLabel(string $fallback, array $blueprint): string
    {
        $label = $blueprint['label'] ?? $blueprint['name'] ?? null;
        $fallbackLabel = ucfirst((string) preg_replace('/[-_]+/', ' ', $fallback));

        if ($label !== null) {
            return self::translatedLabel($label, $fallbackLabel);
        }

        return $fallbackLabel;
    }

    /**
     * @param array<string, mixed> $fieldset
     */
    private static function blockLabel(string $type, array $fieldset): string
    {
        $label = $fieldset['name'] ?? $fieldset['label'] ?? null;

        if ($label !== null) {
            return self::translatedLabel($label, ucfirst((string) preg_replace('/[-_]+/', ' ', $type)));
        }

        return ucfirst((string) preg_replace('/[-_]+/', ' ', $type));
    }

    private static function translatedLabel(mixed $label, string $fallback): string
    {
        if (is_string($label) === false && is_array($label) === false) {
            return $fallback;
        }

        $translated = I18n::translate($label, $label);

        if (is_string($translated) && $translated !== '') {
            return $translated;
        }

        if (is_array($translated)) {
            $translated = I18n::translate($translated, $fallback);

            if (is_string($translated) && $translated !== '') {
                return $translated;
            }
        }

        return $fallback;
    }

    private static function previewText(string $value, string $format): string
    {
        if ($format !== 'html') {
            return $value;
        }

        $withBreaks = preg_replace('/<br\s*\/?>/iu', "\n", $value) ?? $value;
        $withBreaks = preg_replace(
            '/<\/(?:p|div|li|h[1-6]|blockquote|ul|ol)>/iu',
            "$0\n",
            $withBreaks
        ) ?? $withBreaks;

        $plain = html_entity_decode(trim(strip_tags($withBreaks)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/[ \t]*\R[ \t]*/u', "\n", $plain) ?? $plain;

        return preg_replace("/\n{3,}/u", "\n\n", $plain) ?? $plain;
    }

    /**
     * @param array<string, mixed> $fieldBlueprint
     * @param list<string>|null $rules
     */
    private static function fixStructureValue(
        mixed $value,
        array $fieldBlueprint,
        ?array $rules,
        ?string $language
    ): mixed {
        if (is_string($value)) {
            return self::fixStructureField($value, $fieldBlueprint, $rules, $language);
        }

        $subFields = $fieldBlueprint['fields'] ?? [];

        if (!is_array($value) || $subFields === []) {
            return $value;
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($value);

        return self::processStructureRows($rows, $subFields, $rules, $language);
    }

    /**
     * @param array<string, mixed> $fieldBlueprint
     * @param list<string>|null $rules
     */
    private static function fixBlocksValue(
        mixed $value,
        array $fieldBlueprint,
        ?array $rules,
        ?string $language
    ): mixed {
        if (is_string($value)) {
            return self::fixBlocksField($value, $fieldBlueprint, $rules, $language);
        }

        if (!is_array($value)) {
            return $value;
        }

        /** @var list<array<string, mixed>> $blocks */
        $blocks = array_values($value);

        return self::processBlocks($blocks, $fieldBlueprint['fieldsets'] ?? [], $rules, $language);
    }

    /**
     * @param array<string, mixed> $fieldBlueprint
     * @param list<string>|null $rules
     */
    private static function fixLayoutValue(
        mixed $value,
        array $fieldBlueprint,
        ?array $rules,
        ?string $language
    ): mixed {
        if (is_string($value)) {
            return self::fixLayoutField($value, $fieldBlueprint, $rules, $language);
        }

        if (!is_array($value)) {
            return $value;
        }

        return self::processLayoutRows($value, $fieldBlueprint['fieldsets'] ?? [], $rules, $language);
    }

    /**
     * Decodes a YAML-encoded structure field, applies fixes to each row,
     * and re-encodes to YAML.
     *
     * @param array<string, mixed> $fieldBlueprint The structure's blueprint definition
     * @param list<string>|null $rules
     */
    private static function fixStructureField(
        string $value,
        array $fieldBlueprint,
        ?array $rules,
        ?string $language
    ): string {
        $rows      = Yaml::decode($value);
        $subFields = $fieldBlueprint['fields'] ?? [];

        if (!is_array($rows) || $subFields === []) {
            return $value;
        }

        return Yaml::encode(self::processStructureRows($rows, $subFields, $rules, $language));
    }

    /**
     * Decodes a JSON-encoded blocks field, applies fixes to each block's
     * content, and re-encodes to JSON.
     *
     * @param array<string, mixed> $fieldBlueprint The blocks' blueprint definition
     * @param list<string>|null $rules
     */
    private static function fixBlocksField(
        string $value,
        array $fieldBlueprint,
        ?array $rules,
        ?string $language
    ): string {
        $blocks    = json_decode($value, associative: true);
        $fieldsets = $fieldBlueprint['fieldsets'] ?? [];

        if (!is_array($blocks)) {
            return $value;
        }

        /** @var list<array<string, mixed>> $blocks */
        $blocks = array_values($blocks);

        $encoded = json_encode(self::processBlocks($blocks, $fieldsets, $rules, $language));

        return $encoded !== false ? $encoded : $value;
    }

    /**
     * Decodes a JSON-encoded layout field, applies fixes to blocks within
     * each column, and re-encodes to JSON.
     *
     * @param array<string, mixed> $fieldBlueprint The layout's blueprint definition
     * @param list<string>|null $rules
     */
    private static function fixLayoutField(
        string $value,
        array $fieldBlueprint,
        ?array $rules,
        ?string $language
    ): string {
        $layout    = json_decode($value, associative: true);
        $fieldsets = $fieldBlueprint['fieldsets'] ?? [];

        if (!is_array($layout)) {
            return $value;
        }

        $layout = self::processLayoutRows($layout, $fieldsets, $rules, $language);

        $encoded = json_encode($layout);

        return $encoded !== false ? $encoded : $value;
    }

    /**
     * @param  array<int|string, mixed> $layout
     * @param  array<string|int, mixed> $fieldsets
     * @param  list<string>|null        $rules
     * @return array<int|string, mixed>
     */
    private static function processLayoutRows(
        array $layout,
        array $fieldsets,
        ?array $rules,
        ?string $language
    ): array {
        return array_map(function (mixed $row) use ($fieldsets, $rules, $language): mixed {
            if (!is_array($row)) {
                return $row;
            }

            $columns = $row['columns'] ?? [];

            if (!is_array($columns)) {
                return $row;
            }

            $row['columns'] = array_map(function (mixed $col) use ($fieldsets, $rules, $language): mixed {
                if (!is_array($col)) {
                    return $col;
                }

                $blocks = $col['blocks'] ?? [];

                if (is_array($blocks)) {
                    /** @var list<array<string, mixed>> $blockList */
                    $blockList = array_values($blocks);

                    $col['blocks'] = self::processBlocks($blockList, $fieldsets, $rules, $language);
                }

                return $col;
            }, $columns);

            return $row;
        }, $layout);
    }

    /**
     * Applies typography fixes to decoded structure rows.
     *
     * This is the pure array-based implementation; encode/decode is handled
     * by the caller (or fixStructureField at runtime where Kirby is available).
     *
     * @param  list<array<string, mixed>>          $rows
     * @param  array<string, array<string, mixed>> $subFields
     * @param  list<string>|null                   $rules
     * @return list<array<string, mixed>>
     */
    public static function processStructureRows(
        array $rows,
        array $subFields,
        ?array $rules = null,
        ?string $language = null
    ): array {
        return array_map(
            fn ($row) => self::fixFields((array) $row, $subFields, $rules, $language),
            $rows
        );
    }

    /**
     * Applies typography fixes to an array of block objects.
     *
     * Each block has `type` and `content`. The content fields are looked up
     * from the matching fieldset definition (supports direct `fields` and
     * tabbed `tabs[*].fields` layouts).
     *
     * @param  list<array<string, mixed>>          $blocks
     * @param  array<string|int, mixed>            $fieldsets
     * @param  list<string>|null                   $rules
     * @return list<array<string, mixed>>
     */
    public static function processBlocks(
        array $blocks,
        array $fieldsets,
        ?array $rules = null,
        ?string $language = null
    ): array {
        return array_map(function (array $block) use ($fieldsets, $rules, $language): array {
            $type = $block['type'] ?? '';
            $fieldDefs = self::fieldsetFields($fieldsets, (string) $type);

            if ($fieldDefs !== [] && isset($block['content'])) {
                $block['content'] = self::fixFields((array) $block['content'], $fieldDefs, $rules, $language);
            }

            return $block;
        }, $blocks);
    }
}
