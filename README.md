# Kirby Proofreader

Kirby Proofreader adds a Panel button on Kirby CMS for reviewing typographic corrections in page and site content before applying them. Field changes are saved to Kirby's changes version, title fixes use Kirby's native title action and save immediately.

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

Add the button to a page or site blueprint:

```yaml
buttons:
  proofreader: true
  languages: true
  status: true
  settings: true
```

### Options

The default rule order is `unicode`, `ellipsis`, `quotes`, `apostrophes`, `dashes`, `spaces`. `dimensions` is a predefined optional rule for values such as `5 x 5 cm`. Rules can be reordered, disabled or extended. Configure via `site/config/config.php`:

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
`structure`, `blocks` and `layout` fields. Custom field names or types can be included or excluded:

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
