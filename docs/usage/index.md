# Usage

Kirby Proofreader adds a Panel button for page, site and file views. It previews
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

Optional built-in `dimensions` and `paragraphs` rules are available but disabled
by default. `dimensions` normalizes dimension separators such as `5 x 5 cm` to
`5 × 5 cm`; `paragraphs` removes empty HTML paragraphs and stale trailing `<br>`
tags or whitespace from paragraph ends.

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
        'paragraphs',
    ],
];
```

The `dashes` rule normalizes dash characters first and preserves existing
spacing: number ranges use en dashes, spaced hyphens in text use em dashes,
and existing en dashes in word ranges are kept as en dashes. The `spaces` rule
then collapses repeated regular spaces, removes regular spaces at paragraph
starts and ends, removes spaces before punctuation except French high
punctuation, normalizes six-per-em spaces around en dashes, hair spaces around
em dashes, narrow non-breaking spaces after ordinals, and non-breaking spaces
after one-letter words and 2–4 letter sentence starters outside paragraph
starts, and before 2–4 letter paragraph-ending words.

The optional `dimensions` rule handles values such as `5 x 5 cm`. The optional
`paragraphs` rule applies only to HTML fields. It removes empty `<p>` elements
whose content is blank, `&nbsp;` or `<br>`, and removes trailing `<br>` elements
and whitespace from paragraph ends.

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
text, `writer` and `list` fields as HTML text nodes, `structure`, `blocks`
and `layout` fields recursively, and `entries` fields through their nested
field definition. Other fields are ignored to avoid touching technical values
such as URLs, dates, emails, slugs or select values.

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
                'custom-entries' => 'entries',
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

Supported include formats are `plain`, `html`, `structure`, `entries`,
`blocks` and `layout`. Field-type aliases are accepted too: `text` and
`textarea` map to `plain`, while `writer` and `list` map to `html`. List
entries without a mapped format, for example `'names' => ['intro']`, are
treated as `plain` unless the field already has a default format.

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

### Protect patterns

Some text spans should pass through all typography rules unchanged — phone
numbers being the most common case. The `grommasdietz.proofreader.protect`
option lets you declare patterns to protect.

The built-in `phone` preset recognises two notations:

- **International** — starts with `+` followed by a country code, e.g.
  `+49 89 1234-5678`
- **Domestic chained** — three or more hyphen-separated digit groups, e.g.
  `0800-123-4567`

Two-group sequences such as `2010-2020` are deliberately left unprotected so
the `dashes` rule can still convert year and page ranges to en dashes.

```php
return [
    'grommasdietz.proofreader.protect' => [
        'phone' => true,
    ],
];
```

You can also supply arbitrary regex patterns for any span you want to preserve
— product codes, order numbers, ISBNs, or anything else:

```php
return [
    'grommasdietz.proofreader.protect' => [
        'phone'      => true,
        'skuPattern' => '/\bSKU-\d+-\d+\b/u',
        'isbn'       => '/\b97[89]-\d{1,5}-\d{1,7}-\d{1,7}-\d\b/u',
    ],
];
```

Set a preset or custom entry to `false` to explicitly disable it:

```php
return [
    'grommasdietz.proofreader.protect' => [
        'phone' => false,
    ],
];
```

## CLI

Kirby Proofreader registers two commands for the
[Kirby CLI](https://github.com/getkirby/cli). Install the CLI first:

```bash
composer global require getkirby/cli
```

### proofreader:fix

Applies typography fixes to a page, the site model, or a batch of pages. By
default fixes are saved as unpublished changes (same as the Panel button). Use
`--publish` to write directly to the published version.

Batch scopes process stored pages only; virtual pages are skipped because they
cannot be written back to content files.

Use `--field=<name>` to limit either review or fix to one top-level content
field. Combine it with `--rules` for scoped corrections or omit `--rules` to
apply every enabled rule to that field.

```bash
# Fix a single page (saves to changes version)
kirby proofreader:fix projects/my-project

# Fix the site model
kirby proofreader:fix

# Dry-run — show what would change without saving
kirby proofreader:fix projects/my-project --dry-run

# Fix all pages on the site
kirby proofreader:fix --all

# Fix direct children of a page
kirby proofreader:fix projects --children

# Fix a page and all its descendants
kirby proofreader:fix projects --recursive

# Fix only the summary field across all pages
kirby proofreader:fix --all --field=summary

# Fix only dashes in the summary field for a page tree
kirby proofreader:fix projects --recursive --field=summary --rules=dashes

# Publish immediately instead of saving as changes
kirby proofreader:fix projects/my-project --publish

# Limit to specific rules
kirby proofreader:fix projects/my-project --rules=ellipsis,dashes

# Target a specific language in a multi-language install
kirby proofreader:fix projects/my-project --language=de
```

#### Available flags on fix

| Flag                | Description                                       |
| ------------------- | ------------------------------------------------- |
| `--all`             | Process all pages on the site                     |
| `--children`        | Process direct children of the given page         |
| `--recursive`       | Process the given page and all its descendants    |
| `--publish`         | Write to the published version instead of changes |
| `--dry-run`         | Preview changes without saving                    |
| `--language=<code>` | Language code for multi-language installs         |
| `--rules=<list>`    | Comma-separated rule names to apply               |
| `--field=<name>`    | Process one top-level content field only          |

### proofreader:review

Shows suggestions without saving anything. Useful for a quick audit before
committing to changes.

```bash
# Review a single page
kirby proofreader:review projects/my-project

# Review the site model
kirby proofreader:review

# Review all pages on the site
kirby proofreader:review --all

# Review direct children of a page
kirby proofreader:review projects --children

# Review a page and all its descendants
kirby proofreader:review projects --recursive

# Review only the summary field across all pages
kirby proofreader:review --all --field=summary

# Limit to specific rules
kirby proofreader:review projects/my-project --rules=dashes,spaces

# Target a specific language
kirby proofreader:review projects/my-project --language=de
```

#### Available flags on review

| Flag                | Description                                   |
| ------------------- | --------------------------------------------- |
| `--all`             | Review all pages on the site                  |
| `--children`        | Review direct children of the given page      |
| `--recursive`       | Review the given page and all its descendants |
| `--language=<code>` | Language code for multi-language installs     |
| `--rules=<list>`    | Comma-separated rule names to apply           |
| `--field=<name>`    | Review one top-level content field only       |

---

Next: Continue with [Architecture](./architecture.md)
