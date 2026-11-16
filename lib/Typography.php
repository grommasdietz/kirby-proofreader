<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader;

use InvalidArgumentException;
use Kirby\Cms\App;
use Kirby\Cms\Language;

/**
 * Immutable typography snapshot shared by corrections and editor integrations.
 * Native SmartyPants keys stay scalar; definitions describe their semantic use.
 */
final class Typography
{
    private const QUOTES = [
        'default' => ['“', '”', '‘', '’'],
        'en' => ['“', '”', '‘', '’'],
        'de' => ['„', '“', '‚', '‘'],
        'fr' => ["«\u{202F}", "\u{202F}»", '‹', '›'],
    ];

    /** @var array<string, array{character:string, before:string, after:string}> */
    private readonly array $definitions;

    /** @param array<string, mixed> $options */
    private function __construct(private readonly array $options, private readonly ?string $language)
    {
        $endash = $this->option('endash') ?? '–';
        $wordSpace = $this->option('space.endash') ?? "\u{2009}";
        $rangeSpace = $this->option('space.range') ?? $this->option('space.endash') ?? "\u{202F}";
        $thoughtSpace = $this->option('space.emdash') ?? "\u{200A}";
        $dimensionSpace = $this->option('space.multiplication') ?? "\u{202F}";

        $this->definitions = [
            'range' => ['character' => $endash, 'before' => $rangeSpace, 'after' => $rangeSpace],
            'word' => ['character' => $endash, 'before' => $wordSpace, 'after' => $wordSpace],
            'thought' => ['character' => $this->option('emdash') ?? '—', 'before' => $thoughtSpace, 'after' => $thoughtSpace],
            'dimension' => ['character' => '×', 'before' => $dimensionSpace, 'after' => $dimensionSpace],
            'unit' => ['character' => '', 'before' => $this->option('space.unit') ?? "\u{00A0}", 'after' => ''],
        ];
    }

    /** @param array<string, mixed> $options */
    public static function fromOptions(array $options = [], ?string $language = null): self
    {
        return new self($options, $language);
    }

    /**
     * Merge project defaults beneath an explicit language definition. Array
     * definitions make this usable from config-ready callbacks and CLI fixtures.
     *
     * @param Language|array<string, mixed>|null $definition
     */
    public static function forDefinition(Language|array|null $definition = null): self
    {
        $options = self::globalOptions();
        $language = null;
        $overrides = [];

        if ($definition instanceof Language) {
            $language = $definition->code();
            $overrides = $definition->smartypants();
        } elseif (is_array($definition)) {
            $language = is_string($definition['code'] ?? null) ? $definition['code'] : null;
            $overrides = $definition['smartypants'] ?? [];
        }

        return new self(array_replace($options, is_array($overrides) ? $overrides : []), $language);
    }

    public static function forLanguage(?string $language = null): self
    {
        $definition = self::languageDefinition($language);

        return $definition !== null
            ? self::forDefinition($definition)
            : new self(self::globalOptions(), $language);
    }

    /** @return array{character:string, before:string, after:string} */
    public function definition(string $role): array
    {
        return $this->definitions[$role] ?? throw new InvalidArgumentException('Unknown typography role: ' . $role);
    }

    public function clipboard(string $role): string
    {
        $definition = $this->definition($role);

        return $definition['before'] . $definition['character'] . $definition['after'];
    }

    /** @return array<string, mixed> */
    public function options(): array
    {
        return $this->options;
    }

    public function option(string $key): ?string
    {
        return self::stringOption($this->options, $key);
    }

    /** @param array<string, mixed> $options */
    public static function stringOption(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;
        if (!is_string($value) || ($value === '' && !str_starts_with($key, 'space.'))) {
            return null;
        }

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @return array{0:string, 1:string, 2:string, 3:string}|null */
    public function quotes(): ?array
    {
        $base = strtolower(explode('-', str_replace('_', '-', (string) $this->language))[0]);
        $fallback = self::QUOTES[$base] ?? null;
        $quotes = [];
        foreach (['doublequote.open', 'doublequote.close', 'singlequote.open', 'singlequote.close'] as $index => $key) {
            $value = $this->option($key) ?? $fallback[$index] ?? null;
            if ($value === null) {
                return null;
            }
            $quotes[] = $value;
        }

        return [$quotes[0], $quotes[1], $quotes[2], $quotes[3]];
    }

    /** @return array<string, mixed> */
    private static function globalOptions(): array
    {
        $options = App::instance(lazy: true)?->option('smartypants', []);

        return is_array($options) ? $options : [];
    }

    private static function languageDefinition(?string $language): ?Language
    {
        $kirby = App::instance(lazy: true);
        if ($kirby === null || !$kirby->multilang() || $language === null || $language === '' || $language === 'default') {
            return null;
        }

        $normalize = static fn (string $code): string => strtolower(str_replace('_', '-', preg_replace('/[.@].*$/', '', $code) ?? $code));
        $code = $normalize($language);
        foreach ($kirby->languages() as $definition) {
            if ($normalize($definition->code()) === $code) {
                return $definition;
            }
        }
        foreach ($kirby->languages() as $definition) {
            $locale = $definition->locale(LC_CTYPE);
            if (is_string($locale) && $normalize($locale) === $code) {
                return $definition;
            }
        }
        $base = explode('-', $code)[0];
        foreach ($kirby->languages() as $definition) {
            if ($normalize($definition->code()) === $base) {
                return $definition;
            }
        }

        return null;
    }
}
