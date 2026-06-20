# Architecture

Kirby Proofreader has two runtime parts: a Panel button and a PHP review/apply
route.

## Panel Flow

The Panel button is registered in `src/index.js` and can be enabled in
blueprints with `proofreader: true`. The button resolves the current target from
the Panel view API path, asks the route for a preview, opens the review dialog
and sends the selected rules and fields back when the editor applies fixes.

The review dialog keeps title fixes separate from content fields. Content field
changes are saved to Kirby's changes version. Page and site title changes use
Kirby's native `changeTitle()` action because titles are not part of the regular
content changes layer.

## PHP Flow

`config/routes.php` resolves the current site, page, file, user or account
model, the active language and the source content version. It uses the changes
version when one exists and falls back to the latest version.

Custom Blueprint Areas are handled through an optional route that calls
`GrommasDietz\Areas\BlueprintAreas` only when the class is available. The route
reviews values from the area view payload, derives field definitions from the
area layout and saves fixes through the area's draft handling.

`lib/Proofreader.php` applies rules in deterministic order:

1. Unicode composition
2. Ellipsis
3. Quotes
4. Apostrophes
5. Dash characters
6. Spacing
7. Optional dimensions, when enabled
8. Optional paragraph cleanup, when enabled

The dash character and spacing steps are intentionally separate. This lets the
review dialog show a clear first suggestion for the dash glyph and a later
suggestion for the language-specific spacing around existing or corrected
dashes.

The optional dimensions and paragraph cleanup rules are predefined but omitted
from the default rule list. Projects can enable dimensions alongside the default
rules when they want `x` in dimension values to become a multiplication sign.
Paragraph cleanup runs on HTML fields only; it removes empty paragraphs and
stale trailing whitespace or `<br>` elements from paragraph ends.

## Field Coverage

The plugin processes `text`, `textarea`, `writer`, `list`, `structure`,
`blocks` and `layout` fields. Writer, list and block HTML content is handled as
text nodes so tags, links and protected code-like elements stay intact.

Non-text fields such as `url`, `email`, `date`, `select` and `toggle` are left
unchanged.

## Typography Sources

The built-in rules work without configuration. Quote characters, dash
characters and dash spacing can also follow Kirby's native SmartyPants options,
globally or from language definitions. Single-language installs only change
quotes when global quote marks are configured. Apostrophe cleanup is a separate
built-in rule so projects can keep it while disabling quote-pair conversion.
Custom editorial rules belong in `grommasdietz.proofreader.rules`, where they
can be ordered alongside built-in rules.

---

Next: Continue with [Contributions](../contributions/index.md)
