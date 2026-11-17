# Agent guide

Repository guide for the Kirby Proofreader plugin.

## Kirby Proofreader rules

- **Keep it ready-to-install.** Composer and GitHub tag-archive installs must work without running `composer` or `npm`. Keep committed build outputs and the PHP dependencies those installs need.
- Plugin entry: `index.php` registers `App::plugin('grommasdietz/proofreader', ...)`; PHP code lives under `lib/` (`GrommasDietz\\Proofreader\\`).
- Panel source: `src/` (including `src/components/**`) builds with `kirbyup` into `index.js`/`index.css`.
- Playground: `playground/` is the self-contained Kirby site for integration and browser tests. Add the smallest useful fixtures under `playground/site/**`.
- Never wrap imports in `try/catch`.
- Static analysis: when you add plugin folders (blueprints, config, translations, snippets, templates, resources, or `playground/site/plugins/gd-*` fixtures), include them in `psalm.xml.dist`.
- Use `tests/bootstrap.php`, `tests/TestCase.php` and `tests/Support/TestEnvironment.php` to boot the playground quickly. Run `pnpm run test:browser` when Panel behavior changes.
- Write docs in `docs/` and update contributor documentation when development commands or workflows change. Call out intentionally skipped suites in commit or PR notes.

<!-- shared:kirby-plugin-workflow — identical in every Grommas Dietz Kirby plugin repository. Change it in kirby-plugin first, then copy it unchanged. -->

## Shared workflow

- Do not commit local state: credentials, environment files, accounts, sessions, caches, databases, test reports, local plugin links or installed dependencies.
- Committed Panel assets such as `index.js` and `index.css` are build output. Edit `src/`, rebuild with `pnpm run build` and commit the result.
- Use Conventional Commits and focused diffs. Keep source, tests, translations, documentation and generated assets aligned.
- Keep public classes, options, routes, commands, stored data and blueprint handles stable unless a change intentionally breaks them.
- Add regression coverage for fixes. Keep fixtures deterministic and fictional, and never contact real services in default tests.
- Panel copy uses sentences or commas. Avoid semicolons and centered dots as separators.
- Prefer Kirby's built-in Panel icons. Add namespaced Remix Icon glyphs only when Kirby has no suitable icon.
- Install with `composer run setup` and `pnpm run setup`. Before handing over changes, run `composer run verify` and `pnpm run verify`, or `pnpm run verify:all` where the repository defines it. Use the focused lint, Psalm, test and build commands while iterating.
- Keep the README a short user-facing quickstart. Follow [STYLE_GUIDE.md](STYLE_GUIDE.md) and the [documentation guidance](docs/contributions/documentation.md).
- This file is canonical. Assistants such as Copilot read it directly, so do not add per-assistant instruction files.

<!-- /shared:kirby-plugin-workflow -->
