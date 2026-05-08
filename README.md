# Kirby Proofreader

Kirby Proofreader adds a Panel button on Kirby CMS for reviewing typographic corrections in page and site content before applying them. Field changes are saved to Kirby's changes version, title fixes use Kirby's native title action and save immediately.

![Cover image showing an example of the plugin in use](/.github/assets/hero-image.png)

## Requirements

- Kirby 5+
- PHP 8.2+

## Installation

Download or clone this repository into `site/plugins/kirby-proofreader`, or
install it with Composer:

```shell
composer require grommasdietz/kirby-proofreader
```

The plugin ships with built Panel assets, so no build step is required for
installation.

## Usage

Add the button to a page or site blueprint:

```yaml
buttons:
  proofreader: true
  languages: true
  status: true
  settings: true
```

The default rule order is `unicode`, `ellipsis`, `quotes`, `dashes`, `spaces`.
Rules can be reordered, disabled or extended in `site/config/config.php`.
`dimensions` is a predefined optional rule for values such as `5 x 5 cm`:

```php
return [
    'grommasdietz.proofreader.rules' => [
        'unicode',
        'ellipsis',
        'quotes',
        'dashes',
        'spaces',
        'dimensions',
    ],
];
```

Quote characters, dash characters and dash spacing follow Kirby's native
SmartyPants options when configured globally or per language. Single-language
installs need configured quote marks before the `quotes` rule changes quotes.
See [docs/usage/index.md](docs/usage/index.md) for details.

## Development

```shell
composer run setup
pnpm run setup
composer run verify
pnpm run verify
```

Panel source lives in `src/` and builds to the committed `index.js` and
`index.css` files with `pnpm build`.

## Documentation

- [Usage](docs/usage/index.md)
- [Architecture](docs/usage/architecture.md)
- [Contributing](docs/contributions/index.md)

## Security

See [SECURITY.md](SECURITY.md) for the disclosure policy.

## License

[MIT](LICENSE.md) © 2026 Grommas Dietz
