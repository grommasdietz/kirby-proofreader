# Kirby Proofreader

Kirby Proofreader adds a Panel button on Kirby CMS for reviewing typographic corrections in content views before applying them. Field changes are saved to Kirby's changes version, title fixes use Kirby's native title action and save immediately.

![Cover image showing an example of the plugin in use](/.github/assets/hero-image.png)

## Requirements

- Kirby 5+
- PHP 8.2+

## Installation

```bash
composer require grommasdietz/kirby-proofreader
```

> [!TIP]
> If you don’t use Composer, you can download this repository and copy it to `site/plugins/kirby-proofreader`.

## Quickstart

Add the button to a page, site, file, user or custom area blueprint:

```yaml
buttons:
  proofreader: true
  languages: true
  status: true
  settings: true
```

### Options

The default rule order is `unicode`, `ellipsis`, `quotes`, `apostrophes`,
`dashes`, `spaces`. `dimensions` and `paragraphs` are predefined optional
rules. `dimensions` handles values such as `5 x 5 cm`; `paragraphs` removes
empty HTML paragraphs and stale trailing whitespace or `<br>` tags from
paragraph ends.
Rules can be reordered, disabled or extended. Configure via
`site/config/config.php`:

```php
return [
    'grommasdietz.proofreader.rules' => [
        'unicode',
        'ellipsis',
        'quotes' => false,
        'apostrophes',
        'dashes',
        'spaces',
        'dimensions',
        'paragraphs',
        'trademark' => [
            'label' => 'Trademark',
            'callback' => static fn (string $text): string => str_replace(
                'Label TM',
                'Label™',
                $text
            ),
        ],
    ],
];
```

> The keyed `false` entry disables the built-in `quotes` rule. The keyed
> `trademark` array adds a custom callback rule.

Quote characters, dash characters and dash spacing follow Kirby's native
SmartyPants options when configured globally or per language. Single-language
installs need SmartyPants configuration to enable quote rule.

Default field coverage includes `text`, `textarea`, `writer`, `list`,
`structure`, `entries`, `blocks` and `layout` fields. Custom field names or types can be included or excluded:

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

Use `grommasdietz.proofreader.protect` to prevent specific text spans from
being modified by any rule. The built-in `phone` preset protects international
(`+49 89 …`) and domestic chained (`0800-123-4567`) numbers. Arbitrary regex
patterns are accepted for other span types:

```php
return [
    'grommasdietz.proofreader.protect' => [
        'phone'      => true,
        'skuPattern' => '/\bSKU-\d+-\d+\b/u',
    ],
];
```

### CLI

With the [Kirby CLI](https://github.com/getkirby/cli) installed, fixes can
also be applied from the command line:

```bash
# Preview suggestions (read-only)
kirby proofreader:review projects/my-project

# Fix a page (saved as unpublished changes)
kirby proofreader:fix projects/my-project

# Fix all pages and publish immediately
kirby proofreader:fix --all --publish

# Fix only one field across all pages
kirby proofreader:fix --all --field=summary

# Dry-run batch fix
kirby proofreader:fix --all --dry-run
```

See [docs/usage/index.md](docs/usage/index.md) for the full flag reference.

### Documentation

Full reference for [usage](docs/usage/index.md), [contributions](docs/contributions/index.md) and [maintenance](docs/maintenance/index.md) lives in [documentation](docs/index.md).

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and changes.

---

## Security

See [SECURITY.md](SECURITY.md) for security policies and reporting vulnerabilities.

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidance and expectations.

---

## License

[MIT](LICENSE.md) © 2026 Grommas Dietz
