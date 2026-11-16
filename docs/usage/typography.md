# Typography profiles and regional extensions

Keep presentation choices in Kirby's language `smartypants` definitions.
Keep rule selection, field coverage and protection in Proofreader's options.
Writer character menus and punctuation sections can then use the same style
without enabling automatic corrections.

## Configuration and inheritance

The public configuration keeps scalar SmartyPants keys:

```php
'smartypants' => [
    'endash' => '&ndash;',
    'emdash' => '&mdash;',
    'space.range' => '&#8239;',
    'space.endash' => '&thinsp;',
    'space.emdash' => '&thinsp;',
    'space.multiplication' => '&#8239;',
],
```

These values describe a house style. Select each client's quotation marks,
dash length and regional punctuation in their language files. Kirby documents
the native options in its [SmartyPants reference](https://getkirby.com/docs/reference/system/options/smartypants).
`space.range` and `space.multiplication` are Proofreader extensions; Kirby's
native renderer does not interpret those additional keys.

Resolution uses built-in fallbacks, then project SmartyPants options, then the
matching language's explicit values. An exact language code or declared locale
is matched before a base-language fallback. `de-CH` and `de_CH` are equivalent
identifiers. An inline HTML `lang` attribute selects the corresponding profile;
it does not create an independent set of options.

An empty string is an explicit unspaced choice. Unicode strings and HTML
entities are accepted. Missing or invalid values use the fallback. For legacy
configurations, `space.range` falls back to an explicit `space.endash` value
before falling back to U+202F.

## Public resolver

`GrommasDietz\Proofreader\Typography` is an immutable snapshot. It exposes
definitions by role rather than requiring integrations to infer meaning from
the emitted character:

```php
use GrommasDietz\Proofreader\Typography;

$profile = Typography::forLanguage('de-CH');
$range = $profile->definition('range');
// ['character' => '–', 'before' => "\u{202F}", 'after' => "\u{202F}"]

$clipboard = $profile->clipboard('range'); // "\u{202F}–\u{202F}"
$colonSpace = $profile->option('space.colon'); // decoded string or null
```

| Method | Purpose |
| --- | --- |
| `forLanguage(?string $code = null)` | Resolve project options and a Kirby language. A null code uses project options only. |
| `forDefinition(Language\|array\|null $definition = null)` | Resolve project options beneath an explicit Kirby language or array definition. Useful in config-ready callbacks. |
| `fromOptions(array $options = [], ?string $language = null)` | Create a pure snapshot from supplied options. No project options are added. The language selects quotation fallbacks. |
| `definition(string $role)` | Return `character`, `before` and `after` strings. |
| `clipboard(string $role)` | Return the definition's spacing and character as one insertion string. |
| `option(string $key)` | Return a decoded scalar option, preserving explicit empty spacing values. |
| `quotes()` | Return double-open, double-close, single-open and single-close marks, or null when insufficient information is available. |

Supported roles are `range`, `word`, `thought`, `dimension` and `unit`.
The `word` role describes an existing word en dash. If the configured thought
dash uses that same character, nonnumeric occurrences use the `thought` role's
spacing; numeric endpoints still use `range` spacing. Arbitrary word pairs are
not inferred to be semantic ranges.

Definitions keep before and after spacing separate internally. There is no
additional nested public configuration schema. Native dash options remain
strings, and integrations can use `clipboard()` without copying fallback rules.
Profiles are snapshots: resolve again after configuration changes. Proofreader
reuses profiles only within one correction/review operation and clears them
even when a custom rule fails.

## Dimensions and inline formatting

The optional `dimensions` rule handles complete chains such as
`50 x 60 x 70 cm`, including dimensions split across inline elements. Dash rules
also recognize numeric ranges such as `<em>2010</em>–2020`. Corrections preserve
the numeric text and markup and change the separators in place.

Language changes, block boundaries and code-like elements stop cross-node
recognition. Configured protect patterns also apply across inline formatting.
ISO dates remain intact. Keep identifier and phone protection configured for
content that can contain ambiguous numeric codes.

An explicit native `space.unit` value also controls units in recognized
dimensions. For example, `'space.unit' => '&#8239;'` produces `50 × 60 cm`.
Without an explicit setting, the rule retains authored unit spacing. General
word-spacing rules continue to operate independently.

## French as a client-specific extension

French is a useful example of a regional policy that belongs to the client.
For example, the OQLF uses a non-breaking space before the colon and allows
either no space or a narrow space before `;`, `!` and `?`. See its
[punctuation spacing guidance](https://vitrinelinguistique.oqlf.gouv.qc.ca/22039/la-typographie/espacement/espacement-avant-et-apres-les-signes-de-ponctuation-et-les-symboles).
Choose the applicable convention with the client rather than assigning one
policy to every French language code.

For a client choosing a full non-breaking space before the colon and narrow
non-breaking spaces before the other high punctuation marks, add these values
to that language's existing `smartypants` definition:

```php
'space.colon' => '&nbsp;',
'space.semicolon' => '&#8239;',
'space.marks' => '&#8239;',
```

A client choosing no space before `;!?` can use empty strings for
`space.semicolon` and `space.marks`. These options already control the default
`spaces` rule's normalization of existing punctuation spaces. They do not
enable insertion of missing spaces.

The tested [French punctuation example](../examples/french-punctuation.php)
shows a custom rule for conservative missing-space insertion. Copy it to the
client project, for example `settings/partials/french-punctuation.php`, then
add it after `spaces` in the existing rule list:

```php
'grommasdietz.proofreader' => [
    'rules' => [
        'unicode', 'ellipsis', 'quotes', 'apostrophes', 'dashes', 'spaces',
        'regionalFrench' => require __DIR__ . '/partials/french-punctuation.php',
    ],
    'protect' => [
        'phone' => true,
        'url' => '~\bhttps?://[^\s<>]+~u',
    ],
],
```

Retain the project's other rules, exclusions and protect patterns when adding
this example. It runs only in French text with all three punctuation spacing
options explicitly configured. It inserts a missing space after a letter and
before a single punctuation mark followed by a text boundary. Numbers, times,
compact technical syntax and punctuation clusters are left alone. Protected
URLs and code-like HTML elements remain unchanged.

This is a small project extension, not a complete French punctuation parser.
It works within individual text nodes and does not infer punctuation across
inline element boundaries. Enable it only for the intended client fields and
review its suggestions before applying them. Installing or upgrading
Proofreader does not activate this example in any client project.

## Upgrade workflow

The scalar configuration keys remain compatible. Review these behavior fixes:

- Configured en thought dashes now use `space.emdash`; use matching values for
  both word and thought spacing when that is the intended style.
- Complete dimension chains are corrected in one pass.
- Inline formatting no longer hides numeric ranges or dimension separators.
- ISO dates are preserved even when a technical field is explicitly included.

For shared GD integrations, release the public resolver first, then the
GD Config and Blueprint Presets adapters. Update consumer Composer locks before
deploying language/configuration changes. GD integrations retain standalone
fallbacks when the public resolver is unavailable. Existing stored content is
changed only when reviewed corrections are applied.

---

Return to [usage](index.md#language-definitions).
