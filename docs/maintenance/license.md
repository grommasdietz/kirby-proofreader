# Licensing

Keep licensing explicit and consistent for PHP and Node artifacts.

---

## Switch license

- MIT: `php tools/switch-license.php --license=mit`
- Commercial: `php tools/switch-license.php --license=commercial` (sets `package.json` to `UNLICENSED`)

The switcher copies the matching template from `licenses/` into [LICENSE.md](/LICENSE.md), updates the
`license` fields in `composer.json` and `package.json`, and rewrites the README license line.

---

## Third-party notices

- Generate [THIRD_PARTY_NOTICES.md](../../THIRD_PARTY_NOTICES.md) via `php tools/generate-third-party-notices.php` when distribution policies require it.
- If you bundle JavaScript assets, append the relevant JS notices to that file as well.

---

Next: Prepare a release with the [workflow](./workflow.md)
