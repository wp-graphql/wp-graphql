=== WPGraphQL IDE ===
Contributors: jasonbahl, joefusco
Tags: headless, decoupled, graphql, devtools
Requires at least: 5.7
Tested up to: 6.8
Stable tag: 4.4.1
Requires PHP: 7.4
License: GPL-3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Modern GraphQL IDE for WPGraphQL — schema-aware editor, execution history, saved queries, and a public endpoint mode.

== Description ==

WPGraphQL IDE is a next-generation query editor for WPGraphQL, built on WordPress's component library and CodeMirror 6. It replaces the bundled legacy GraphiQL interface with a faster, more extensible IDE that feels native to wp-admin.

**Three ways to open the IDE:**

* **Dedicated page** at `wp-admin/admin.php?page=graphql-ide`
* **Drawer** — slide-up panel triggered from the admin bar, available on every wp-admin and front-end page
* **Public endpoint mode** (opt-in) — visiting the GraphQL endpoint URL in a browser renders the IDE shell instead of returning JSON. Anonymous visitors get a read-only schema browser; signed-in administrators get the full editor.

**Editor features:**

* Multi-tab editor with auto-save and overflow tab dropdown
* Schema-aware autocomplete, hover-doc tooltips, and inline lint warnings (powered by CodeMirror 6 + cm6-graphql)
* Cmd-click navigation from any field, type, or argument straight to the Docs Explorer
* Response panes: JSON viewer, table view, status code / duration / size / resolver-count badges, request tracing, query log, N+1 detection
* Variables and headers editors with their own autocomplete
* Per-document settings (description, alias names, max-age cache hint, allow/deny)
* Execution history persisted to localStorage, scoped per WordPress user and IDE context — available to anonymous public-endpoint visitors too
* Saved Queries panel with personal collections and share links (when WPGraphQL Smart Cache is also active)
* Full keyboard support: Cmd+Enter to execute, Ctrl+Shift+P to prettify, arrow keys to switch tabs

**What's new in 5.0:**

* **Rebuilt UI** on `@wordpress/components` and `@wordpress/data`. The legacy GraphiQL wrapper is gone — see the Upgrade Notice if you have customizations.
* **Smart Cache integration.** Saved documents now live in `wp-graphql-smart-cache`'s `graphql_document` post type — one canonical primitive for the WPGraphQL ecosystem. The IDE works standalone when Smart Cache isn't installed; Saved Queries / Document Settings / share links light up when it is.
* **Three render modes** (above), each individually configurable.
* **Schema-typed data layer.** Document and collection operations run through WPGraphQL itself instead of the WordPress REST API.
* **Full internationalization.** Every UI string passes through `@wordpress/i18n` under the `wpgraphql-ide` text domain.
* **Auto-upgrade from 4.x.** Open tabs and query history saved by the legacy GraphiQL UI migrate forward on first 5.0 load.

For the complete breaking-change list and 4.x → 5.0 migration guide, see `UPGRADE-5.0.md` in the plugin or the project's GitHub releases.

== Installation ==

1. Install and activate [WPGraphQL](https://wordpress.org/plugins/wp-graphql/) — required.
2. (Optional but recommended) Install and activate [WPGraphQL Smart Cache](https://wordpress.org/plugins/wpgraphql-smart-cache/) — unlocks Saved Queries, personal collections, share links, and the Document Settings drawer.
3. Install WPGraphQL IDE from the WordPress.org plugin directory, or upload the plugin zip and activate it.
4. Open the IDE from **GraphQL → GraphQL IDE** in the admin menu, or click the **GraphQL IDE** entry in the admin bar.

The IDE requires the `manage_graphql_ide` capability, which is granted to administrators by default. Hosts can override the capability requirement via the `wpgraphql_ide_capability_required` filter.

== Frequently Asked Questions ==

= How do I open the IDE? =

The plugin adds three entry points: a dedicated admin page under **GraphQL → GraphQL IDE**, a slide-up drawer triggered from the **GraphQL IDE** link in the admin bar (works on every wp-admin and front-end page), and an opt-in public endpoint mode that renders the IDE when you visit the GraphQL endpoint URL in a browser.

= Do I need WPGraphQL Smart Cache? =

No — the IDE works as a standalone GraphQL client without it. Smart Cache is optional but unlocks the saved-document features: the Saved Queries panel, personal collections, share links, and the Document Settings drawer. Install Smart Cache and the IDE detects it automatically; no configuration needed.

= What changed in 5.0? =

5.0 rebuilds the UI on `@wordpress/components` and CodeMirror 6, moves saved-document storage onto Smart Cache's `graphql_document` post type, and ships full internationalization. Extension authors should consult `UPGRADE-5.0.md` (bundled with the plugin) — several legacy hooks were renamed, and a few were briefly removed and then restored with improved behavior. Open tabs and query history saved by 4.x are migrated forward automatically on first 5.0 load.

= How do I enable the public endpoint? =

Under **GraphQL → IDE Settings**, check **Public IDE at GraphQL endpoint**. Once enabled, browser visits to the GraphQL endpoint URL (with an HTML `Accept` header) render the IDE shell instead of returning JSON. API clients (curl, fetch with `Content-Type: application/json`, GraphQL clients in general) keep getting JSON as before. Optionally enable **Allow sign-in on the public IDE** to surface a sign-in prompt to anonymous visitors.

= Where can I find the non-compressed JavaScript and CSS source code? =

The non-compressed source code for the JavaScript and CSS files is available in the following directories:

- **Scripts**: [src/ directory](https://github.com/wp-graphql/wp-graphql/tree/main/plugins/wp-graphql-ide/src)
- **Styles**: [styles/ directory](https://github.com/wp-graphql/wp-graphql/tree/main/plugins/wp-graphql-ide/styles)

You can view or download the source code directly from the GitHub repository.

= What are the major dependencies? =

* **CodeMirror 6** ([codemirror.net](https://codemirror.net/)) — the editor surface, with `cm6-graphql` for schema-aware GraphQL highlighting + autocomplete
* **`@wordpress/components`** and **`@wordpress/data`** — UI primitives and state management
* **`@graphiql/toolkit`** — fragment-merging utilities reused from the GraphiQL project
* **vaul** ([github.com/emilkowalski/vaul](https://github.com/emilkowalski/vaul)) — the slide-up drawer
* **graphql-js** ([github.com/graphql/graphql-js](https://github.com/graphql/graphql-js)) — the underlying GraphQL parser

= Where do I report bugs or request features? =

Open an issue at [github.com/wp-graphql/wp-graphql](https://github.com/wp-graphql/wp-graphql/issues). For security issues, please follow the [security policy](https://github.com/wp-graphql/wp-graphql/security/policy) instead of filing a public issue.

= How does WPGraphQL IDE handle privacy and telemetry? =

WPGraphQL IDE uses the [Appsero SDK](https://appsero.com/privacy-policy) to collect telemetry data **only after user consent**. This helps improve the plugin while respecting user privacy. When telemetry is enabled, the same payloads are also mirrored to WPGraphQL-operated infrastructure at https://telemetry.wpgraphql.com.

== Privacy Policy ==

WPGraphQL IDE uses [Appsero](https://appsero.com) SDK to collect some telemetry data upon user's confirmation. This helps us to troubleshoot problems faster and make product improvements.

Appsero SDK **does not gather any data by default.** The SDK only starts gathering basic telemetry data **when a user allows it via the admin notice**.

When you opt in, each telemetry request is sent to Appsero and a duplicate is sent in a non-blocking request to WPGraphQL-operated infrastructure at https://telemetry.wpgraphql.com (the same categories of data as described for Appsero below).

Learn more about how [Appsero collects and uses this data](https://appsero.com/privacy-policy/).

== Screenshots ==

1. Schema-aware autocomplete with inline hover documentation as you build a query.
2. Browse the schema in the Docs explorer — search types and fields, and Cmd-click from the editor to jump straight to a type.
3. Per-user execution history alongside clear, inline error reporting, with tracing, query log, and response-header tabs.

== Upgrade Notice ==

= 5.0.0 =
Major rebuild on `@wordpress/components` + CodeMirror 6. Saved-document storage moves to WPGraphQL Smart Cache's `graphql_document` post type (install Smart Cache to keep Saved Queries / Document Settings / share links). Three render modes (admin page / drawer / public endpoint). Full breaking-change list and 4.x → 5.0 migration guide in `UPGRADE-5.0.md` (bundled with the plugin) — most user data (open tabs, query history) auto-migrates on first load.

WPGraphQL IDE follows Semver versioning. Breaking changes will be documented in the Upgrade Notice section above.

== Changelog ==

= 5.0.0 =

**Breaking changes**

* IDE UI rebuilt on `@wordpress/components` + `@wordpress/data` + CodeMirror 6. Legacy GraphiQL wrapper removed.
* Saved-document storage moved to WPGraphQL Smart Cache's `graphql_document` post type. The IDE-owned `graphql_ide_query` post type, `graphql_ide_collection` taxonomy, and Document-Settings taxonomies are removed. Saved Queries / Document Settings / share links require Smart Cache to be active.
* GraphQL types `IdeQuery`, `IdeQueries`, `IdeCollection`, `IdeCollections` removed. Use `graphqlDocument` / `graphqlDocumentGroup` (from Smart Cache) instead.
* REST routes removed: `/wpgraphql-ide/v1/documents/:id/publish` and `/wpgraphql-ide/v1/documents/collections/:id`. The publish flow is now a standard `POST /wp/v2/graphql_document/:id`; cascade-delete is client-side.
* `wpgraphql_ide_capability_required` filter now consulted at every IDE permission check — REST callbacks, post-type / taxonomy capability maps, meta auth, admin menu, public-endpoint flag. Hosts overriding the cap to a different value will now find their override honored end-to-end (previously only the admin menu link was gated).
* Legacy `graphiql_*` hook aliases dropped (`enqueue_graphiql_extension`, `graphiql_external_fragments`, `graphiql_rendered`, `graphiql_toolbar_before_buttons`, `graphiql_toolbar_after_buttons`). Use the canonical `wpgraphql_ide_*` / `wpgraphql-ide.*` names.

**New features**

* **Three render modes** — dedicated admin page, slide-up drawer (from the admin bar, on any wp-admin or front-end page), and an opt-in public IDE at the GraphQL endpoint URL.
* **Smart Cache integration** via `SmartCacheBridge`. GraphQL fields `variables` and `headers` added to `GraphqlDocument` (read + Create/Update inputs) for execution context.
* **Schema-typed data layer.** Document and collection CRUD inside the IDE run through WPGraphQL via the bundled `gql()` client. REST remains for user preferences, the aggregated `documentSettings` readback field, and bulk import/export/reorder. Execution history is browser-local.
* **Full internationalization.** Every UI string passes through `@wordpress/i18n` with the `wpgraphql-ide` text domain.
* **Auto-upgrade from 4.x.** Open tabs (`graphiql:tabState`) and query history (`graphiql:queries`) saved by the legacy GraphiQL UI migrate forward on first 5.0 load; legacy localStorage keys are cleared.
* **Anonymous history on the public endpoint.** Visitors without a logged-in session get a browser-local history bucket (capped at 100, newest first), mirroring the 4.x GraphiQL behavior.
* **`wpgraphql_ide_external_fragments` filter** restored with smarter merge behavior — only fragments referenced by the outgoing query are prepended, with transitive resolution between external fragments.
* New extension API: `registerPreference` (typed device/user prefs), `executeRequest` / `executeResponse` filters, `wpgraphql-ide.afterExecute` action.
* New autocomplete + hover popups render reliably above the slide-up drawer overlay.

= 4.4.1 =

**Bug Fixes**

* **deps-dev:** bump dotenv from 16.6.1 to 17.4.1 ([#3807](https://github.com/wp-graphql/wp-graphql/issues/3807))
* **deps-dev:** bump phpstan/phpstan from 2.1.47 to 2.1.50 in /plugins/wp-graphql-ide in the wp-graphql-ide-composer-dev-minor-patch group ([#3790](https://github.com/wp-graphql/wp-graphql/issues/3790))
* **deps-dev:** bump phpstan/phpstan from 2.1.50 to 2.1.51 in /plugins/wp-graphql-ide in the wp-graphql-ide-composer-dev-minor-patch group ([#3803](https://github.com/wp-graphql/wp-graphql/issues/3803))
* **deps-dev:** bump the npm-dev-minor-patch group across 1 directory with 6 updates ([#3799](https://github.com/wp-graphql/wp-graphql/issues/3799))

= 4.4.0 =

**New Features**

* **telemetry:** mirror Appsero insights to telemetry.wpgraphql.com ([#3785](https://github.com/wp-graphql/wp-graphql/issues/3785))

= 4.3.0 =

**New Features**

* add Appsero telemetry tracking to WPGraphQL IDE ([#3765](https://github.com/wp-graphql/wp-graphql/issues/3765))

**Bug Fixes**

* sync readme.txt changelogs with releases ([#3744](https://github.com/wp-graphql/wp-graphql/issues/3744))

= 4.2.0 =

**New Features**

* **deps:** bump @babel/runtime from 7.24.1 to 7.29.2 in /plugins/wp-graphql-ide ([#3700](https://github.com/wp-graphql/wp-graphql/issues/3700))
* **deps:** bump axios from 1.7.6 to 1.14.0 in /plugins/wp-graphql-ide ([#3706](https://github.com/wp-graphql/wp-graphql/issues/3706))
* **deps:** bump basic-ftp from 5.0.5 to 5.2.0 in /plugins/wp-graphql-ide ([#3684](https://github.com/wp-graphql/wp-graphql/issues/3684))
* **deps:** bump flatted from 3.3.1 to 3.4.2 in /plugins/wp-graphql-ide ([#3689](https://github.com/wp-graphql/wp-graphql/issues/3689))
* **deps:** bump form-data from 4.0.0 to 4.0.5 in /plugins/wp-graphql-ide ([#3693](https://github.com/wp-graphql/wp-graphql/issues/3693))
* **deps:** bump http-proxy-middleware from 2.0.6 to 2.0.9 in /plugins/wp-graphql-ide ([#3702](https://github.com/wp-graphql/wp-graphql/issues/3702))
* **deps:** bump immutable from 4.3.5 to 4.3.8 in /plugins/wp-graphql-ide ([#3687](https://github.com/wp-graphql/wp-graphql/issues/3687))
* **deps:** bump lodash from 4.17.21 to 4.18.1 in /plugins/wp-graphql-ide ([#3691](https://github.com/wp-graphql/wp-graphql/issues/3691))
* **deps:** bump node-forge from 1.3.1 to 1.4.0 in /plugins/wp-graphql-ide ([#3686](https://github.com/wp-graphql/wp-graphql/issues/3686))
* **deps:** bump on-headers and compression in /plugins/wp-graphql-ide ([#3678](https://github.com/wp-graphql/wp-graphql/issues/3678))
* **deps:** bump picomatch from 2.3.1 to 2.3.2 in /plugins/wp-graphql-ide ([#3688](https://github.com/wp-graphql/wp-graphql/issues/3688))
* **deps:** bump qs and body-parser in /plugins/wp-graphql-ide ([#3696](https://github.com/wp-graphql/wp-graphql/issues/3696))
* **deps:** bump simple-git from 3.23.0 to 3.33.0 in /plugins/wp-graphql-ide ([#3675](https://github.com/wp-graphql/wp-graphql/issues/3675))
* **deps:** bump svgo from 3.2.0 to 3.3.3 in /plugins/wp-graphql-ide ([#3690](https://github.com/wp-graphql/wp-graphql/issues/3690))
* **deps:** bump the npm-prod-minor-patch group across 1 directory with 5 updates ([#3739](https://github.com/wp-graphql/wp-graphql/issues/3739))
* **deps:** bump webpack from 5.94.0 to 5.105.4 in /plugins/wp-graphql-ide ([#3677](https://github.com/wp-graphql/wp-graphql/issues/3677))
* **deps:** bump yaml from 1.10.2 to 1.10.3 in /plugins/wp-graphql-ide ([#3698](https://github.com/wp-graphql/wp-graphql/issues/3698))
* migrate WPGraphQL for ACF to monorepo ([#3581](https://github.com/wp-graphql/wp-graphql/issues/3581))

**Bug Fixes**

* **deps-dev:** bump rimraf from 5.0.10 to 6.1.3 ([#3666](https://github.com/wp-graphql/wp-graphql/issues/3666))
* resolve post by percent-encoded slug/URI when post_name is stored encoded ([#3582](https://github.com/wp-graphql/wp-graphql/issues/3582)) ([#3611](https://github.com/wp-graphql/wp-graphql/issues/3611))

= 4.1.0 =

**New Features**

* import WPGraphQL IDE into monorepo ([#3542](https://github.com/wp-graphql/wp-graphql/issues/3542))

**Bug Fixes**

* resolve all JavaScript linting errors in wp-graphql-ide ([#3548](https://github.com/wp-graphql/wp-graphql/issues/3548))

= 4.0.11 - 4.0.24 =

**Patch Changes**

* debugging release scripts

= 4.0.10 =

**Patch Changes**

* 74c832c: chore: add period to description in readme.txt

= 4.0.9 =

**Patch Changes**

* 2eab1a7: chore: update license format in readme.txt to GPL-3.0

= 4.0.8 =

**Patch Changes**

* f610132: fix: remove duplicate git tag creation in release workflow

= 4.0.7 =

**Patch Changes**

* bf627cc: Fixed an issue with plugin updates not appearing on WordPress.org
* b47b46d: ci: attempt to fix GitHub actions auto deploy to wp.org.

= 4.0.6 =

**Patch Changes**

* d1df1d4: chore: update tested WordPress version to 6.8

= 4.0.5 =

**Patch Changes**

* c6cfbc1: fix: linting tooltips are now visible when using the IDE in the drawer
* 6500ef3: fix: broken query composer by adding missing import statements for AbstractArgView and FieldView components. Props to @hacknug for the fix!

= 4.0.4 =

**Patch Changes**

* b4d7302: Test

= 4.0.3 =

**Patch Changes**

* fd9d099: chore: set workflow environment

= 4.0.2 =

**Patch Changes**

* a2b5fbd: - chore: Bump supported WordPress version

= 4.0.1 =

**Patch Changes**

* 477a555: ### Added

* Introduced `wp_localize_escaped_data()` function for recursively escaping data before localizing it in WordPress. This ensures safe output of strings, URLs, integers, and nested arrays when passing data to JavaScript, using native WordPress functions like `wp_kses_post()` and `esc_url()`.

**Improved**

* Enhanced security by ensuring all localized data is properly sanitized before being passed to `wp_localize_script()`, preventing potential XSS vulnerabilities and ensuring safe use of dynamic data in JavaScript.

* 4da3973: - chore: Bump the npm_and_yarn group across 1 directory with 7 updates

= 4.0.0 =

**Major Changes**

* eda911d: Updated the plugin's custom filter and action names to be consistent across the plugin

**Patch Changes**

* eda911d: Fixed bug where credentials were being sent in the headers unnecessarily under certain conditions
