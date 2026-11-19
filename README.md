# Kirby Proofreader

Kirby Proofreader adds a Panel button on Kirby CMS for reviewing typographic corrections in content views before applying them. Field changes are saved to Kirby's changes version, title fixes use Kirby's native title action and save immediately.

![Cover image showing an example of the plugin in use](/.github/assets/hero-image.png)

## Requirements

- Kirby 5.2+
- PHP 8.2+

## Installation

```bash
composer require grommasdietz/kirby-proofreader
```

> [!TIP]
> For a manual install, download the **Source code (zip)** archive of an immutable GitHub release tag and copy it to `site/plugins/kirby-proofreader`.

## Quickstart

Add the button to a page, site, file, user or custom area blueprint:

```yaml
buttons:
  proofreader: true
  languages: true
  status: true
  settings: true
```

See [usage](docs/usage/index.md) for rule configuration, language-aware corrections, protected spans and CLI review/fix commands.

Numeric ranges, word dashes and dimensions can use separate language-owned
spacing settings. See the [language configuration and upgrade notes](docs/usage/index.md#language-definitions).

Integrations can use the shared [typography profile API](docs/usage/typography.md).

### Documentation

Full reference for [usage](docs/usage/index.md), [contributions](docs/contributions/index.md) and [maintenance](docs/maintenance/index.md) lives in [documentation](docs/index.md).

---

## Project information

- [Changelog](CHANGELOG.md)
- [Security policy and reporting](SECURITY.md)
- [Contributing](CONTRIBUTING.md)

---

## License

[MIT](LICENSE.md) © 2026 Grommas Dietz
