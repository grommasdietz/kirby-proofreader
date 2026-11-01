# Setup

This plugin uses a Kirby site in `playground/` for integration and browser tests.

---

## Composer

Install Composer dependencies for the repo and the playground:

```bash
composer run setup
```

Setup provisions `KIRBY_CONTENT_SALT` and `KIRBY_COOKIE_KEY` in the ignored repository-root `.env`, outside the playground document root. The playground also provisions missing or empty values on first boot. Values are independent, survive playground resets and are never rotated automatically. Existing comments and unrelated settings are preserved. The two keys accept literal quoted or unquoted values of at least 32 characters; process environment values take precedence. Invalid or duplicate assignments raise an error. The repository root must be writable for initial generation.

The root dependency graph contains PHPUnit, Psalm, PHP CS Fixer and Kirby for self-contained PHP quality checks. The separate playground graph provides the disposable application used by runtime and browser tests.

---

## Node

Install Node dependencies and the Playwright Chromium browser:

```bash
pnpm run setup
```

On Linux CI, install Chromium and its operating-system packages with `pnpm exec playwright install --with-deps chromium`.

---

## Panel dev server

Use the kirbyup dev server while iterating on Panel code:

```bash
pnpm dev
```

---

Next: Continue with [Structure](./structure.md)
