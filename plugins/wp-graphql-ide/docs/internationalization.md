---
title: "Internationalization"
description: "How the WPGraphQL IDE handles translations, what modern WordPress expects, and the gaps still to close."
---

# Internationalization

WPGraphQL IDE requires WordPress 6.1+ and takes advantage of the modern i18n stack introduced in 6.5 ("performant translations" via `.l10n.php` files) and 6.7 (just-in-time textdomain loading) when the host site provides it; older versions fall back to the classic `.mo` loader. This doc explains what the plugin does today, what WordPress expects of it, and what still needs wiring.

## Textdomain

The textdomain is `wpgraphql-ide`. It is declared in the plugin header (`wpgraphql-ide.php`):

```
Text Domain:  wpgraphql-ide
Domain Path:  /languages
```

Use it consistently in **every** translatable string, both PHP and JS:

```php
__( 'GraphQL IDE', 'wpgraphql-ide' );
```

```js
import { __ } from '@wordpress/i18n';
__( 'GraphQL IDE', 'wpgraphql-ide' );
```

If a string is missing the textdomain it falls through to the default `default` textdomain and can never be translated by the plugin's `.mo` / `.l10n.php` files.

## How WordPress loads translations

You do **not** call `load_plugin_textdomain()`. WordPress has auto-loaded it from the `Text Domain:` header since 4.6, and since 6.7 it uses **just-in-time loading** that races against any manual call you make. Calling it yourself triggers `_doing_it_wrong` warnings under WP-CLI and produces no benefit.

WordPress will look up translations in this order:

1. `WP_LANG_DIR/plugins/wpgraphql-ide-{locale}.l10n.php`  (performant — preferred, 6.5+)
2. `WP_LANG_DIR/plugins/wpgraphql-ide-{locale}.mo`
3. `{plugin}/languages/wpgraphql-ide-{locale}.l10n.php`
4. `{plugin}/languages/wpgraphql-ide-{locale}.mo`

For sites that pull translations from translate.wordpress.org, paths 1–2 are where they land automatically. Self-hosted overrides go in the plugin's `/languages/` directory.

`.l10n.php` is a plain PHP array. WordPress generates it from a `.po` at build/release time via `wp i18n make-php`. It is roughly twice as fast to load as a `.mo` because there's no binary parser.

## Current setup vs. what's still missing

| Piece | Status | Notes |
| --- | --- | --- |
| `Text Domain:` header | done | `wpgraphql-ide`, with matching `Domain Path: /languages`. |
| `load_plugin_textdomain()` call | intentionally absent | WordPress 4.6+ auto-loads; 6.7+ warns if you call it manually. |
| PHP strings wrapped in `__()` / `_x()` / `_n()` | done | Audit with `composer run check-cs` (PHPCS WPCS rules include i18n checks). |
| JS strings using `@wordpress/i18n` | done in source | But — see next row. |
| `wp_set_script_translations()` for `wpgraphql-ide` / `wpgraphql-ide-render` script handles | **missing** | Without this call in `AssetEnqueue::enqueue()`, every `__('foo', 'wpgraphql-ide')` in the React app is shipped as English regardless of locale. This is the single biggest gap. |
| `/languages/` directory + `.pot` | **missing** | Translators have nothing to start from. |
| `npm run i18n:pot` (or equivalent) | **missing** | No tooling wired to regenerate the `.pot` on string changes. |

The two rows marked **missing** are deliberate follow-ups. They block the IDE's React UI from ever being translated, but they do not block this release of the textdomain conventions documented above. When ready:

1. Add `wp_set_script_translations()` calls right after each `wp_enqueue_script()` in `includes/AssetEnqueue.php`, pointing at `WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'languages'`.
2. Create `languages/` and ship an empty `.pot` placeholder (or commit the result of step 3 once tooling is in place).
3. Wire `wp i18n make-pot . languages/wpgraphql-ide.pot --domain=wpgraphql-ide` into an `npm run i18n:pot` script, run via the wp-env CLI shim already used for `check-cs` / `phpstan`.

## For translators

Translations are intended to be contributed via [translate.wordpress.org](https://translate.wordpress.org/) once the plugin ships to the .org directory. Until then, drop a translated `wpgraphql-ide-{locale}.po` into `/languages/`, compile it to `.mo` (and optionally `.l10n.php`), and commit alongside.

## For extension authors

If you register your own activity-bar panel, toolbar button, or response panel against `window.WPGraphQLIDE`, use **your own** textdomain — not `wpgraphql-ide`. Your textdomain has its own auto-loaded translation files, and bundling translatable strings under the IDE's textdomain means your translators can't reach them.
