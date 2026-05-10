# Usage

Kirby Proofreader adds a Panel button for page and site views. It previews
typography fixes for eligible `text`, `textarea`, `writer`, `list`,
`structure`, `blocks`, and `layout` content before saving field changes as
unpublished changes. Blueprint definitions that use `extends` are resolved
before field eligibility is checked.

Title fixes are shown as their own scope, selected by default and saved
immediately via Kirby's native title action when applied.

## Options for Kirby Proofreader

Set plugin options in `site/config/config.php`.

### Rules

The default rule order is:

```php
[
    'unicode',
    'ellipsis',
    'quotes',
    'apostrophes',
    'dashes',
    'spaces',
]
```

An optional built-in `dimensions` rule is available but disabled by default.
It normalizes dimension separators such as `5 x 5 cm` to `5 × 5 cm`.

You can override the enabled rules and their order with one option in
`site/config/config.php`. String entries and keyed `true` entries use the
built-in rule definition:

```php
return [
    'grommasdietz.proofreader.rules' => [
        'unicode',
        'ellipsis',
        'apostrophes',
        'dashes',
        'spaces',
    ],
];
```

To disable a rule, include it as a keyed entry with `false`. This keeps the full
rule order visible in one place:

```php
return [
    'grommasdietz.proofreader.rules' => [
        'unicode',
        'ellipsis',
        'quotes' => false,
        'apostrophes',
        'dashes',
        'spaces',
    ],
];
```

Custom rules can be added inline as callbacks or regular-expression
replacements. A callback receives the current text, the current language code,
and the rule name. It must return the changed text:

```php
return [
    'grommasdietz.proofreader.rules' => [
        'unicode',
        'customTrademark' => [
            'label' => 'Trademark',
            'callback' => static fn (string $text): string => str_replace(
                'Label TM',
                'Label™',
                $text
            ),
        ],
        'customCopyright' => [
            'label' => 'Copyright',
            'pattern' => '/\s+\(c\)/u',
            'replace' => ' ©',
        ],
        'ellipsis',
        'quotes',
        'apostrophes',
        'dashes',
        'spaces',
        'dimensions',
    ],
];
```

The `dashes` rule normalizes dash characters first and preserves existing
spacing: number ranges use en dashes, spaced hyphens in text use em dashes,
and existing en dashes in word ranges are kept as en dashes. The `spaces` rule
then collapses repeated regular spaces, removes spaces before punctuation
except French high punctuation, normalizes six-per-em spaces around en dashes,
hair spaces around em dashes, narrow non-breaking spaces after ordinals, and
non-breaking spaces after one-letter words, after 2–4 letter sentence starters,
and before 2–4 letter paragraph-ending words.

Quote characters, dash characters and dash spacing can also come from Kirby's
native SmartyPants options. In multi-language installations, language-level
`smartypants` options win over global options. In single-language
installations, quote fixes are skipped unless global `smartypants` quote marks
are configured. The `apostrophes` rule normalizes straight apostrophes in words
to `’` and can stay enabled when quote-pair conversion is disabled:

```php
return [
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
];
```

Only the Proofreader's configured rules are applied; this does not enable
Kirby's full SmartyPants parser for content.

### Fields

By default, Kirby Proofreader handles `text` and `textarea` fields as plain
text, `writer` and `list` fields as HTML text nodes, and `structure`, `blocks`
and `layout` fields recursively. Other fields are ignored to avoid touching
technical values such as URLs, dates, emails, slugs or select values.

Custom field names or field types can be included with
`grommasdietz.proofreader.fields.include`. Default-covered fields can be
excluded with `grommasdietz.proofreader.fields.exclude`. Excludes always win
over defaults and includes:

```php
return [
    'grommasdietz.proofreader.fields' => [
        'include' => [
            'types' => [
                'custom-writer' => 'html',
                'custom-text' => 'plain',
                'custom-structure' => 'structure',
            ],
            'names' => [
                'intro' => 'plain',
            ],
        ],
        'exclude' => [
            'types' => ['text', 'textarea'],
            'names' => ['intro'],
        ],
    ],
];
```

Supported include formats are `plain`, `html`, `structure`, `blocks` and
`layout`. Field-type aliases are accepted too: `text` and `textarea` map to
`plain`, while `writer` and `list` map to `html`. List entries without a mapped
format, for example `'names' => ['intro']`, are treated as `plain` unless the
field already has a default format.

### Button label

Set `grommasdietz.proofreader.button.text` to `true` to show a text label next
to the Panel button icon:

```php
return [
    'grommasdietz.proofreader.button.text' => true,
];
```

HTML-backed fields are fixed only in text nodes. Code-like elements such as
`pre`, `code`, `kbd`, `samp`, `script`, `style` and `math` are left unchanged.

---

Next: Continue with [Architecture](./architecture.md)
