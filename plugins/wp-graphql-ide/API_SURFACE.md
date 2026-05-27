# IDE API Surface

Inventory of which IDE surfaces use WPGraphQL and which use REST, plus the server-side registries (user-preference meta, WPGraphQL admin settings) that the IDE owns. Update on every PR that adds, moves, or removes a server endpoint or registered surface.

> **5.0 architecture.** The IDE is **progressive enhancement** on top of WPGraphQL Smart Cache. Smart Cache owns the saved-document primitive (`graphql_document` post type + `graphql_query_alias` / `graphql_document_grant` / `graphql_document_http_maxage` / `graphql_document_group` taxonomies); the IDE owns history, per-user preferences, personal collections, and the workspace UI. When Smart Cache isn't active the IDE still works as a standalone GraphQL client — editor, schema, execute, response panes, history, preferences — but Saved Queries, Save / Publish, share links, Document Settings drawer, and personal collections aren't surfaced.
>
> Three pre-5.0 surfaces were removed and replaced by Smart Cache equivalents: the `graphql_ide_query` post type (→ `graphql_document`), the `graphql_ide_collection` taxonomy (→ `graphql_document_group`), and the three internal `graphql_ide_query_alias|maxage|grant` taxonomies (→ Smart Cache's `graphql_query_alias` / `graphql_document_http_maxage` / `graphql_document_grant`). The `IdeQuery` / `IdeCollection` GraphQL types are also gone; the IDE-owned `IdeHistoryEntry` type stays. Full breaking-change list in `CHANGELOG.md` Unreleased.

Source files:

- `wpgraphql-ide.php` — schema and REST registrations
- `includes/AssetEnqueue.php` — bootstrap data + `wpgraphql_ide_localized_data` / `wpgraphql_ide_context` filters
- `includes/SmartCacheBridge.php` — filters that opt Smart Cache's primitives into the WP REST API and register IDE-specific meta on `graphql_document`
- `includes/UserMeta.php` — user-preference meta
- `includes/document-settings/` — Document Settings registry, REST field, taxonomies, localization
- `includes/settings.php` + `includes/SettingsPage.php` — WPGraphQL admin settings tab + `updateGraphqlSetting` mutation
- `includes/public-endpoint.php` — public IDE endpoint mode and its settings fields
- `src/api/` — client wrappers (`documents.js`, `history.js`, `preferences.js`, `graphql-client.js`)
- `src/bootstrap.js` — typed accessors for `WPGRAPHQL_IDE_DATA` boolean flags + `loginUrl` + `hasSmartCache`

## Bootstrap data (`window.WPGRAPHQL_IDE_DATA`)

Server-injected at script-enqueue time (`includes/AssetEnqueue.php::enqueue()`). Other modules contribute additional keys through the `wpgraphql_ide_localized_data` filter — the public-endpoint render adds five fields (`includes/public-endpoint.php::inject_public_endpoint_data()`), the Document Settings module adds `documentSettings`, and the Settings tab module adds `settingsRegistry` + `canManageSettings`.

| Field | Type | Where set | Purpose |
| --- | --- | --- | --- |
| `nonce` | string | every render | REST nonce for the current user. |
| `restUrl` | string | every render | REST root URL. |
| `graphqlEndpoint` | string | every render | GraphQL POST endpoint URL. |
| `rootElementId` | string | every render | DOM id the React root mounts to. |
| `context` | object | every render | App context — filtered through `wpgraphql_ide_context`. |
| `context.currentUserId` | int | every render | `0` for anonymous, post id otherwise. localStorage buckets (unsaved tabs, prefs, etc.) scope by this. |
| `isDedicatedIdePage` | bool | every render | Truthy on `/wp-admin/admin.php?page=graphql-ide`. |
| `documentSettings` | object | every render | `{ fields: [...], globalGrantMode }`. See **Document Settings module** below. |
| `settingsRegistry` | object | every render | Snapshot of WPGraphQL settings sections/fields, for the in-IDE Settings workspace tab. |
| `canManageSettings` | bool | every render | Whether the current user passes `graphql_manage_settings_cap` — gates the Settings topbar action. |
| `endpointMode` | bool | public endpoint render only | Truthy on `/?graphql`. Hides Save / Saved Queries / History / Document Settings / Share / topbar actions / (when anonymous) the auth toggle. |
| `renderStandalone` | bool | public endpoint render only | Render full-page (no slide-up drawer). Also true on the dedicated admin page via `isDedicatedIdePage`. |
| `isUserLoggedIn` | bool | public endpoint render only | Seeds the auth toggle's initial state. |
| `loginUrl` | string | public endpoint render, anonymous only | `wp_login_url()` with `redirect_to` set to the current page. |
| `hasSmartCache` | bool | every render | Whether WPGraphQL Smart Cache is active. Gates Save / Publish / Saved Queries panel / share dialog / Document Settings drawer in the React tree (`src/bootstrap.js`, `src/components/ide-layout/EditorPane.jsx`, `src/registry/index.js`). |
| `allowEndpointSignIn` | bool | public endpoint render only | Whether the public-endpoint IDE invites anonymous sign-in. Always `true` on the dedicated admin page. |

## GraphQL

### Types

| Type | Source | Status |
| --- | --- | --- |
| `IdeHistoryEntry` / `IdeHistoryEntries` | `register_post_type('graphql_ide_history', show_in_graphql=true)` | **Public, stable.** IDE-owned execution history. |
| `UpdateGraphqlSettingValueInput` | OneOf input — variant per WPGraphQL setting field type (`text`, `number`, `checkbox`, `select`, `radio`, `user_role_select`, `password`). See `includes/settings.php`. | Public. |
| `UpdateGraphqlSettingValue` | Output object — same variant shape as the input. | Public. |
| `UpdateGraphqlSettingPayload` | Mutation output. | Public. |
| ~~`IdeQuery` / `IdeQueries`~~ | ~~`register_post_type('graphql_ide_query', show_in_graphql=true)`~~ | **Removed in 5.0.** Consumers query Smart Cache's `graphqlDocument` directly. |
| ~~`IdeCollection` / `IdeCollections`~~ | ~~`register_taxonomy('graphql_ide_collection', show_in_graphql=true)`~~ | **Removed in 5.0.** Consumers query Smart Cache's `graphqlDocumentGroup` directly. |

### Custom fields

Registered in `GraphQLSchema::register()` (`IdeHistoryEntry`) and `SmartCacheBridge::register_ide_graphql_fields_on_smart_cache_document()` (`GraphqlDocument`).

| Type | Field | Backed by | Status |
| --- | --- | --- | --- |
| `IdeHistoryEntry` | `queryString: String` | `_graphql_ide_query` | Public. |
| `IdeHistoryEntry` | `variables: String` | `_graphql_ide_variables` | Public. |
| `IdeHistoryEntry` | `headers: String` | `_graphql_ide_headers` | Public. |
| `IdeHistoryEntry` | `durationMs: Int` | `_graphql_ide_duration_ms` | Public. |
| `IdeHistoryEntry` | `executionStatus: String` | `_graphql_ide_status` | Public. |
| `IdeHistoryEntry` | `documentId: Int` | `_graphql_ide_document_id` | Public. **In 5.0** this references a `graphqlDocument` (Smart Cache) database ID, not the legacy `IdeQuery` ID. |
| `IdeHistoryEntry` | `isAuthenticated: Boolean` | `_graphql_ide_is_authenticated` | Public. |
| `IdeHistoryEntry` | `httpMethod: String` | `_graphql_ide_http_method` | Public. |
| `GraphqlDocument` | `variables: String` | `_graphql_ide_variables` (Smart Cache post meta via `SmartCacheBridge`) | Public. **Added in 5.0.** Also exposed as inputs on `CreateGraphqlDocumentInput` and `UpdateGraphqlDocumentInput`. |
| `GraphqlDocument` | `headers: String` | `_graphql_ide_headers` (same bridge) | Public. **Added in 5.0.** Also exposed as Create/Update inputs. |
| ~~`IdeQuery`~~ fields | ~~queryString / variables / headers~~ | — | **Removed in 5.0** along with the `IdeQuery` type. |

### Mutations

| Mutation | Source | Purpose |
| --- | --- | --- |
| `updateGraphqlSetting(section!, field!, value: UpdateGraphqlSettingValueInput!)` | `includes/settings.php::register_graphql_mutation()` | Persist a single WPGraphQL setting through GraphQL, so the in-IDE Settings tab doesn't need a REST round-trip. Requires the capability returned by `graphql_manage_settings_cap` (default `manage_options`). The `value` input is a OneOf input — variant must match the registered field type. |

### Authorization

| Filter | Hook | Purpose |
| --- | --- | --- |
| `scope_graphql_connections` | `graphql_connection_query_args` | Adds `author = current_user_id()` to `IdeHistoryEntry` and `graphqlDocument` connections so users only see their own. |
| `restrict_post_visibility` | `graphql_data_is_private` | Marks `graphql_document` / `graphql_ide_history` posts private when the current user isn't the author. Gates `node(id)` / `graphqlDocument(id)` / `ideHistoryEntry(id)`. |

### Client callsites

| Callsite | File |
| --- | --- |
| User search (Share dialog) | `src/components/dialogs/ShareCollectionDialog.jsx` |
| GraphQL fetch wrapper | `src/api/graphql-client.js` |

## REST

### IDE-owned CPT routes (`/wp/v2/...`)

| Method | Path | Client function | Status |
| --- | --- | --- | --- |
| `GET` | `/wp/v2/graphql-ide-history` | `getHistory()` | Public. |
| `POST` | `/wp/v2/graphql-ide-history` | `createHistoryEntry()` | Public. |
| `DELETE` | `/wp/v2/graphql-ide-history/{id}` | `deleteHistoryEntry()` | Public. |
| `GET` | `/wp/v2/users/me?_fields=meta` | `getPreferences()` | Public. |
| `POST` | `/wp/v2/users/me` | `savePreference()` | Public. |

### Smart Cache primitives (REST routes — `/wp/v2/graphql_document...`)

When Smart Cache is active, the IDE reads and writes saved queries through Smart Cache's REST surface:

| Method | Path | Client function | Notes |
| --- | --- | --- | --- |
| `GET` / `POST` | `/wp/v2/graphql_document[/{id}]` | `getDocuments` / `createDocument` / `updateDocument` / `deleteDocument` | Standard WP REST `graphql_document` route. Smart Cache registers the CPT without a custom `rest_base`, so the URL uses the underscore form. The IDE's `SmartCacheBridge` filters `register_post_type_args` to add `show_in_rest=true`. |
| `GET` / `POST` | `/wp/v2/graphql_document_group[/{id}]` | `getCollections` / `createCollection` / `renameCollection` / `deleteCollection` | Smart Cache's collections taxonomy. Same bridge filter pattern. |
| `GET` / `POST` | `/wp/v2/graphql_query_alias[/{id}]` | (not directly called by the IDE today; written through `documentSettings`) | Aliases taxonomy. |
| `GET` / `POST` | `/wp/v2/graphql_document_grant[/{id}]` | (written through `documentSettings`) | Allow/deny taxonomy. |
| `GET` / `POST` | `/wp/v2/graphql_document_http_maxage[/{id}]` | (written through `documentSettings`) | Max-age taxonomy. |

#### `includes/SmartCacheBridge.php`

The bridge module is the seam between the IDE and Smart Cache. Three filter callbacks, all conditional on `class_exists('\WPGraphQL\SmartCache\Document')` so the IDE no-ops cleanly when Smart Cache isn't installed:

| Hook | Adds |
| --- | --- |
| `register_post_type_args` (filter, for `graphql_document`) | `show_in_rest=true` + supports `custom-fields` / `page-attributes` / `excerpt` (existing supports preserved). |
| `register_taxonomy_args` (filter, for the 4 doc taxonomies above) | `show_in_rest=true`. |
| `init` priority 11 (action) | Registers IDE-specific post meta on `graphql_document`: `_graphql_ide_variables` and `_graphql_ide_headers`, both JSON-string sanitized, auth-gated on `wpgraphql_ide_user_can()`. |

Preserves Smart Cache's existing args — if Smart Cache opts the primitives into REST upstream, the bridge becomes a redundant no-op rather than a clobber.

| Concept | Smart Cache surface |
| --- | --- |
| Saved query document | `graphql_document` — `post_content` carries the query (AST-validated + normalized + hashed on every save by Smart Cache's `save_document_cb`; `post_name` = SHA-256 of normalized content) |
| Alias / queryId | `graphql_query_alias` (one term per alias; the hash is also a term) |
| Allow / deny | `graphql_document_grant` (`allow` / `deny` / `''`) |
| Cache-Control max-age | `graphql_document_http_maxage` (term name = seconds as string) |
| Collection / group | `graphql_document_group` |
| Description | `post_excerpt` (Smart Cache's existing convention) |
| Variables JSON | post meta `_graphql_ide_variables` (registered by `SmartCacheBridge`) |
| Headers JSON | post meta `_graphql_ide_headers` (registered by `SmartCacheBridge`) |

### Custom routes (`/wpgraphql-ide/v1/...`)

Registered in `Rest::register()`. Surviving routes operate on `graphql_document` directly.

| Method | Path | Client | Status |
| --- | --- | --- | --- |
| `GET` | `/wpgraphql-ide/v1/documents/export` | `exportDocuments()` | Public. Exports the current user's `graphql_document` posts grouped by `graphql_document_group` term. |
| `POST` | `/wpgraphql-ide/v1/documents/import` | `importDocuments()` | Public. Imports a documents payload into `graphql_document` posts, creating taxonomy terms as needed. |
| `POST` | `/wpgraphql-ide/v1/documents/reorder` | `reorderDocuments()` | Public. Persists `menu_order` on `graphql_document` posts. |
| `POST` | `/wpgraphql-ide/v1/collections/reorder` | `reorderCollections()` | Public. Per-user collection order in `wpgraphql_ide_collection_order` user meta. |

**Removed in 5.0:**

- `POST /documents/{id}/publish` — Smart Cache's `save_document_cb` validates + normalizes + hashes on every save; the IDE flips status through the standard `/wp/v2/graphql_document/{id}` update route with `status=publish`. The old endpoint's `{ already_exists, id }` duplicate-collision response is also gone (`wp_unique_post_slug` deconflicts identical content into `<hash>` / `<hash>-2` instead).
- `DELETE /collections/{id}/cascade` — the IDE's `SavedQueriesPanel` performs the cascade client-side (delete each child document, then delete the term).

### Per-document settings REST field

The `documentSettings` REST field registers on `/wp/v2/graphql_document[/{id}]` (`includes/document-settings/rest.php`). Read returns a key/value map of all registered fields; write accepts the same shape and dispatches each value through the storage adapter at `includes/document-settings/storage.php`. Storage targets for the four built-in fields land in Smart Cache's taxonomies (see **Built-in fields** below). Errors from storage propagate as `WP_Error` so REST returns a structured 4xx.

`register_graphql_document_setting_field()` (global namespace, mirrors WPGraphQL core's `register_graphql_*` naming) stays public — plugins can add additional fields to the drawer beyond the built-ins.

### User-preference meta (via `/wp/v2/users/me`)

Registered in `includes/UserMeta.php`. All keys are `show_in_rest` with an `auth_callback` gated on `current_user_can('manage_graphql_ide')`. Clients read via `GET /wp/v2/users/me?_fields=meta` and write via `POST /wp/v2/users/me` (or the dedicated `savePreference()` helper).

| Meta key | Type | Default | Purpose |
| --- | --- | --- | --- |
| `wpgraphql_ide_theme` | string (`'' \| 'light' \| 'dark'`) | `''` | GraphiQL theme override. Empty string defers to system / GraphiQL default. |
| `wpgraphql_ide_persist_headers` | boolean | `false` | Whether HTTP headers persist between sessions / across docs. |
| `wpgraphql_ide_collection_order` | integer[] | `[]` | Manual ordering of the saved-queries panel — array of collection term IDs. |
| `wpgraphql_ide_collection_sort_modes` | object<string,enum> | `{}` | Per-collection sort mode. Values: `'manual' \| 'title_asc' \| 'modified_desc' \| 'status'`. |
| `wpgraphql_ide_section_states` | string (JSON-encoded object) | `'{}'` | Per-user UI state for collapsible sections (collections + Documents bucket + Unsaved + personal collections). Stored as a JSON string so we can add per-section fields client-side without server releases; UI owns the shape. |
| `wpgraphql_ide_seen_shared_collections` | string[] | `[]` | IDs of shared personal collections the user has already been notified about (suppresses the "X shared a collection with you" snackbar on subsequent loads). |
| `wpgraphql_ide_collapsed_notices` | string[] | `[]` | IDs of document notices the user has collapsed. Notices not present here render expanded. |
| `wpgraphql_ide_personal_collections` | object[] | `[]` | Per-user document groupings with optional sharing. Owner-writable; recipients see the aggregated read-only "Shared with me" view assembled by `UserMeta::aggregate_shared_collections()`. Shape: `{ id, name, document_ids: int[], shared_with: int[] }`. Sanitized server-side — entries with malformed ids, foreign-owned docs, or non-IDE-capable share targets are silently dropped. |

### Authorization

| Filter | Hook | Notes |
| --- | --- | --- |
| `scope_rest_queries` | `rest_graphql_document_query` + `rest_graphql_ide_history_query` | Author-scopes the WP REST list query to the current user. |
| `enforce_rest_permissions` | `rest_pre_dispatch` | Cap gate. Matches against `/wp/v2/graphql_document`, `/wp/v2/graphql_document_group`, `/wp/v2/graphql_query_alias`, `/wp/v2/graphql_document_grant`, `/wp/v2/graphql_document_http_maxage`, and `/wp/v2/graphql-ide-history`. Underscore on the document routes — Smart Cache registers with no custom `rest_base`. |
| `restrict_document_response` | `rest_prepare_graphql_document` | Per-post author check + shared-collection grant. Owners always see their own docs; recipients see shared ones; others get 403. |

## Capability helpers (PHP)

Two global-namespace helpers wrap the `wpgraphql_ide_capability_required` filter. Use them in extension code instead of hardcoding capability literals.

| Symbol | Source | Purpose |
| --- | --- | --- |
| `wpgraphql_ide_get_capability(): string` | `includes/access-functions.php` | Returns the filtered cap string (default `'manage_graphql_ide'`). Use at registration time — `register_post_type` capability maps, `add_submenu_page` cap arg, `get_users(['capability' => ...])`, etc. Falls back to default if the filter returns a non-string or empty value. |
| `wpgraphql_ide_user_can(): bool` | `includes/access-functions.php` | Equivalent to `current_user_can( wpgraphql_ide_get_capability() )`. Use at runtime — REST `permission_callback`, meta `auth_callback`, gate checks, etc. |

The namespaced wrapper `\WPGraphQLIDE\user_has_graphql_ide_capability()` is preserved for back-compat and now delegates to `wpgraphql_ide_user_can()`. New code should call the global-namespace helper.

## Document Settings module

In 5.0 the IDE's three internal taxonomies (`graphql_ide_query_alias` / `graphql_ide_query_maxage` / `graphql_ide_query_grant`) were removed; the drawer's built-in fields now bind directly to Smart Cache's `graphql_query_alias` / `graphql_document_http_maxage` / `graphql_document_grant`. The drawer is hidden in the UI when Smart Cache isn't active (no `graphql_document` to attach settings to).

`register_graphql_document_setting_field()` stays public — plugins can add additional fields to the drawer beyond Smart Cache's built-ins (team-specific tags, deprecation flags, custom directives metadata, etc.).

### What's still public

| Symbol | Source | Purpose |
| --- | --- | --- |
| Action `wpgraphql_ide_register_document_settings` | `includes/document-settings.php` | Single registration entry point — fires once on `init` priority 11. |
| `register_graphql_document_setting_field( string $name, array $config ): void` | `includes/document-settings/access-functions.php` | Register an additional field. |

### Field config shape

```php
register_graphql_document_setting_field( 'my_field', [
    'label'             => __( 'My Field', 'my-plugin' ),  // Human-readable label
    'desc'              => __( 'Help text shown below the field.', 'my-plugin' ),
    'type'              => 'text',                          // text|textarea|number|tag_list|radio_with_default
    'default'           => '',
    'options'           => [],                              // For radio_with_default: [{ value, label }, ...]
    'capability'        => 'edit_posts',                    // Required to read/write (default 'edit_posts')
    'sanitize_callback' => null,                            // Optional callable applied before storage
    'storage'           => [
        'kind'   => 'post_meta',                            // post_field|post_meta|taxonomy
        'key'    => '_my_field_meta_key',                   // Meta key, post field name, or taxonomy slug
        'multi'  => false,                                  // Taxonomy: multi-value flag
        'unique' => false,                                  // Taxonomy: enforce cross-document uniqueness
    ],
] );
```

### Built-in fields (storage targets)

| Field | Type | Storage | Owned by |
| --- | --- | --- | --- |
| `description` | textarea | `post_field` → `post_excerpt` | Smart Cache (its own existing convention). |
| `aliases` | tag_list | `taxonomy` → `graphql_query_alias` (multi, unique) | Smart Cache. |
| `maxAgeHeader` | number | `taxonomy` → `graphql_document_http_maxage` | Smart Cache. |
| `grant` | radio_with_default | `taxonomy` → `graphql_document_grant` | Smart Cache. |

The IDE's built-in field registrations are bridge code that maps the drawer UI to Smart Cache's storage. When Smart Cache isn't active the drawer toggle is hidden in `EditorPane` (gated on `hasSmartCache`).

### JS bootstrap surface

The Document Settings module hooks `wpgraphql_ide_localized_data` to publish the registered field descriptors at `window.WPGRAPHQL_IDE_DATA.documentSettings`:

```js
window.WPGRAPHQL_IDE_DATA.documentSettings = {
  fields: [
    { name, label, desc, type, default, options },
    // ...
  ],
  globalGrantMode: 'public' | 'only_allowed' | 'some_denied',
};
```

Fields the current user cannot read (per the field's `capability`) are filtered out before localization, so the React drawer can render whatever it receives without re-checking permissions.

## WPGraphQL admin Settings (IDE-registered)

The IDE registers an **IDE Settings** tab on the WPGraphQL admin settings page (`/wp-admin/admin.php?page=graphql-settings`), plus two endpoint-mode fields. Same UI is reachable from inside the IDE itself via the topbar Settings action when the current user passes `graphql_manage_settings_cap`.

### Section

| Slug | Source |
| --- | --- |
| `graphql_ide_settings` | `includes/SettingsPage.php::register()` |

### Fields

| Section | Field | Type | Default | Source |
| --- | --- | --- | --- | --- |
| `graphql_ide_settings` | `graphql_ide_link_behavior` | radio (`drawer` \| `dedicated_page` \| `disabled`) | `drawer` | `includes/SettingsPage.php` |
| `graphql_ide_settings` | `graphql_ide_show_legacy_editor` | checkbox | `false` | `includes/SettingsPage.php` |
| `graphql_ide_settings` | `graphql_ide_public_endpoint` | checkbox | `false` | `includes/public-endpoint.php` |
| `graphql_ide_settings` | `graphql_ide_public_endpoint_allow_sign_in` | checkbox | `false` | `includes/public-endpoint.php` |

### Capability

`graphql_manage_settings_cap` filter (default `manage_options`) — also used by the `updateGraphqlSetting` GraphQL mutation. Different from `wpgraphql_ide_capability_required` (default `manage_graphql_ide`), which gates **viewing** the IDE; settings management is an explicitly higher bar.

## Gaps

| # | Gap | Blocks |
| --- | --- | --- |
| A | Meta inputs (`variables`, `headers`, `queryString`) missing on auto-generated `createIdeHistoryEntry`. | History write paths. |
| B | No GraphQL mutations for the IDE's surviving custom REST routes (`export` / `import` / `reorder`). | Bulk import/export and reorder via GraphQL. |
| C | Eight `wpgraphql_ide_*` user-preference metas are not on the GraphQL schema (REST only). | Any GraphQL-only client reading or writing preferences. |
| D | Smart Cache doesn't yet expose `graphql_query_alias` / `graphql_document_grant` / `graphql_document_http_maxage` / `graphql_document_group` via REST. | The IDE's Document Settings drawer can't write these fields until either Smart Cache adds REST exposure or the IDE adds REST routes that operate on Smart Cache's taxonomies. Tracked as a Smart Cache upstream PR. |
