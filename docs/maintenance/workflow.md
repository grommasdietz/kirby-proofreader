# Releasing

Use this checklist to keep releases consistent and shippable.

- [ ] Confirm license is correct (run `php tools/switch-license.php --license=mit|commercial` if needed)
- [ ] Generate/update third-party notices (`php tools/generate-third-party-notices.php`, append JS notices when bundling assets)
- [ ] Build Panel assets and ensure compiled `index.js` / `index.css` are committed (`pnpm build`, runs inside `pnpm run verify`)
- [ ] Verify checks: `composer run verify` (php-cs-fixer dry run, Psalm, PHPUnit) and `pnpm run verify` (build, eslint, Playwright)
- [ ] Produce a clean distributable archive without dev aides (`composer package:clean`, respects `.gitattributes` export rules to drop tools, AI guides, and docs-only files)
- [ ] Remove template-only helpers with `php tools/cleanup-template.php --apply` if they are no longer useful for local maintenance, then update docs to match
- [ ] Check versioning/changelog as your release process requires
- [ ] Keep ZIP/submodule installs clean: no local runtime artifacts or playground data in Git
- [ ] CI alignment: GitHub Actions runs the same PHP/JS checks and triggers semantic-release on `main`. Ensure conventional commits and required tokens are in place

---

Next: Return to [Documentation](../index.md)
