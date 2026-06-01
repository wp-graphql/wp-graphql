# Upgrading from WPGraphQL IDE 4.x to 5.0

5.0 is a major version. The UI is rebuilt on `@wordpress/components` + CodeMirror 6, and saved-document storage moves onto [WPGraphQL Smart Cache](https://wordpress.org/plugins/wpgraphql-smart-cache/)'s `graphql_document` post type — one canonical document primitive for the WPGraphQL ecosystem.

This document covers everything you need to know to upgrade safely. For the full release notes (every commit, every internal change), see `CHANGELOG.md`.

## TL;DR for end users

Open tabs and query history saved by the 4.x GraphiQL UI are migrated forward automatically on first 5.0 load. No action required.

If you don't have WPGraphQL Smart Cache installed, you'll lose access to **Saved Queries**, **personal collections**, **share links**, and the **Document Settings** drawer. Install Smart Cache to get them back — it's a free plugin from the same project and the IDE detects it automatically. The standalone IDE (editor, schema, execute, history, preferences) keeps working without it.

## TL;DR for extension authors

If you registered custom toolbar buttons, sidebar panels, or hooked any IDE filter in 4.x, **read the "Hooks that changed" section below** before upgrading a site you care about.

## What auto-migrates (zero action required)

The 4.x GraphiQL UI persisted its working state in browser localStorage. On first 5.0 load, the IDE checks for those legacy keys and ports the data forward into 5.0's storage backends, then deletes the legacy keys so subsequent loads are no-ops.

- **Open tabs** — `graphiql:tabState` is read, the open tabs are carried as drafts in the new preference store (`open_tabs` + `active_tab`), and the previously-active tab is restored as focused.
- **Query history** — `graphiql:queries` is read and each entry is replayed into the 5.0 history backend (`wpgraphql-ide:local-history:v1:user-{userId}:ctx-{context}` in localStorage, scoped per WordPress user and IDE context). The same model is used for both signed-in admins and anonymous public-endpoint visitors.

The migration is one-shot, tolerates partial failure, and is idempotent — repeat loads are no-ops once the legacy keys are gone.

## Hooks that changed

### Removed (no replacement) — silently drops; was already documented as "not ported" in 4.x

- `graphiql_toolbar_before_buttons` PHP action — toolbar buttons are now added through the JS registry. Use [`registerDocumentEditorToolbarButton()`](./docs/access-functions.md) instead.
- `graphiql_toolbar_after_buttons` PHP action — same migration.

### Removed (legacy aliases dropped)

These were aliases that re-fired the canonical hook by a legacy name. Hook the canonical name directly.

| Legacy 4.x name | 5.0 replacement |
| --- | --- |
| `enqueue_graphiql_extension` (PHP) | `wpgraphql_ide_enqueue_script` |
| `graphiql_external_fragments` (PHP) | `wpgraphql_ide_external_fragments` (see below — restored with improvements) |
| `graphiql_rendered` (JS) | `wpgraphql-ide.rendered` (use `wp.hooks.addAction`) |

### Restored with improved behavior

`wpgraphql_ide_external_fragments` (PHP filter) — returns an array of GraphQL fragment definition strings. In 5.0 the IDE no longer blindly prepends every registered fragment to every query. Instead it parses the outgoing query, finds unresolved fragment spreads, and prepends only matching definitions (with transitive resolution between fragments). Inline definitions in the user's query win over external ones with the same name. The filter signature is unchanged from 4.x.

### Removed (internal *Error JS actions)

Ten paired `wpgraphql-ide.register*Error` actions were dropped — they were never useful to extensions (a consumer can't recover from a registration error from outside the IDE). Registration failures still log to `console.error`. The matching `wpgraphql-ide.afterRegister*` success actions are unchanged.

## GraphQL schema changes

### Removed types

- `IdeQuery`, `IdeQueries` — replaced by `graphqlDocument` / `graphqlDocuments` (provided by WPGraphQL Smart Cache).
- `IdeCollection`, `IdeCollections` — replaced by `graphqlDocumentGroup` / `graphqlDocumentGroups`.

### New on `GraphqlDocument`

`variables` and `headers` are now exposed as `String` fields on `GraphqlDocument`, with matching inputs on `CreateGraphqlDocumentInput` and `UpdateGraphqlDocumentInput`. These carry the IDE's per-document execution context (JSON-encoded variables and HTTP headers) so extensions can read and write the IDE's full saved-query state through the GraphQL schema instead of dropping to REST.

## REST routes removed

- `POST /wpgraphql-ide/v1/documents/:id/publish` — was a bespoke publish-with-hash route. Use the standard `POST /wp/v2/graphql_document/:id` with `status=publish`; Smart Cache's `save_document_cb` hashes the content and writes the sha256 to `post_name` server-side.
- `DELETE /wpgraphql-ide/v1/documents/collections/:id` — was a cascade-delete that removed a term plus every document tagged with it. The IDE now does this client-side (delete each child document, then delete the term).

## Capability filter — behavior change

The `wpgraphql_ide_capability_required` filter is now consulted at **every** IDE permission check — REST permission callbacks, post-type / taxonomy capability maps, post-meta and user-meta auth callbacks, admin submenu capability, public-endpoint trimming flag.

In 4.x this filter was only honored at admin-menu render. Hosts that filtered the cap to a custom role would see the IDE link appear in their admin menu, but every actual operation was still gated by `manage_graphql_ide` directly — a 403 trap.

If you override this filter, **verify your custom capability has the permissions it needs** for the operations users can reach inside the IDE before upgrading production. The 5.0 behavior is what the filter always claimed to do; 4.x just under-honored it.

## Public endpoint mode

If you previously enabled the **Public IDE at GraphQL endpoint** setting in 4.x:

- The setting carries over unchanged.
- Anonymous visitors get a browser-local history bucket (capped at 50 entries) — the same model signed-in admins use.
- The IDE shell ships a feature-trimmed UI for anonymous visitors (no Save / saved queries / share / topbar actions). Toggle **Allow sign-in on the public IDE** to surface a sign-in prompt.

## What to do if your site stored saved queries in 4.x

The 4.x `graphql_ide_query` post type and `graphql_ide_collection` taxonomy are removed. **There is no automatic migration** — that data was always developer-preview only and the 4.x IDE was the only consumer.

If you have data in those tables that you need to preserve, export it through 4.x's REST routes before upgrading and re-import as `graphql_document` posts via Smart Cache (which is the canonical home for saved GraphQL documents going forward).

## Where to get help

- **Bug reports / feature requests:** [github.com/wp-graphql/wp-graphql/issues](https://github.com/wp-graphql/wp-graphql/issues)
- **Security:** see the project [security policy](https://github.com/wp-graphql/wp-graphql/security/policy)
- **API reference:** [`API Surface`](./docs/api-surface.md) (canonical surface inventory), [`Actions & Filters`](./docs/actions-and-filters.md) (PHP/JS hooks), [`Access Functions`](./docs/access-functions.md) (JS extension API). Full docs index: [`docs/`](./docs/README.md).
