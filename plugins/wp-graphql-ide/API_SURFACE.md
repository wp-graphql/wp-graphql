# IDE API Surface

Inventory of which IDE surfaces use WPGraphQL and which use REST, plus the server-side registries (user-preference meta, WPGraphQL admin settings) that the IDE owns. Update on every PR that adds, moves, or removes a server endpoint or registered surface.

> **5.0 alignment in progress.** The IDE is shifting to **progressive enhancement** on top of WPGraphQL Smart Cache: Smart Cache owns the saved-document primitive (`graphql_document` post type + `graphql_query_alias` / `graphql_document_grant` / `graphql_document_http_maxage` / `graphql_document_group` taxonomies); the IDE owns history, per-user preferences, personal collections, and the workspace UI. When Smart Cache isn't active, the IDE works standalone with local-only unsaved tabs (same model as GraphiQL).
>
> Some sections below reference the pre-5.0 `graphql_ide_query` post type, the `IdeQuery` / `IdeCollection` GraphQL types, and related REST routes. **Those are being removed.** This doc updates incrementally as the implementation lands; the table of contents below marks each section's status. The earlier Track A commits that documented `IdeQuery` / `IdeCollection` as public are being walked back — see `CHANGELOG.md` Unreleased for the breaking-change note.

Source files:

- `wpgraphql-ide.php` — schema and REST registrations
- `includes/AssetEnqueue.php` — bootstrap data + `wpgraphql_ide_localized_data` / `wpgraphql_ide_context` filters
- `includes/UserMeta.php` — user-preference meta
- `includes/document-settings/` — Document Settings registry, REST field, taxonomies, localization
- `includes/settings.php` + `includes/SettingsPage.php` — WPGraphQL admin settings tab + `updateGraphqlSetting` mutation
- `includes/public-endpoint.php` — public IDE endpoint mode and its settings fields
- `src/api/` — client wrappers (`documents.js`, `history.js`, `preferences.js`, `graphql-client.js`)
- `src/bootstrap.js` — typed accessors for `WPGRAPHQL_IDE_DATA` boolean flags + `loginUrl`

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

Registered in `GraphQLSchema::register()`.

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
| ~~`IdeQuery`~~ fields | ~~queryString / variables / headers~~ | — | **Removed in 5.0** along with the `IdeQuery` type. |

### Mutations

| Mutation | Source | Purpose |
| --- | --- | --- |
| `updateGraphqlSetting(section!, field!, value: UpdateGraphqlSettingValueInput!)` | `includes/settings.php::register_graphql_mutation()` | Persist a single WPGraphQL setting through GraphQL, so the in-IDE Settings tab doesn't need a REST round-trip. Requires the capability returned by `graphql_manage_settings_cap` (default `manage_options`). The `value` input is a OneOf input — variant must match the registered field type. |

### Authorization

| Filter | Hook | Purpose |
| --- | --- | --- |
| `scope_ide_graphql_connections_to_current_user` | `graphql_connection_query_args` | Adds `author = current_user_id()` to `IdeHistoryEntry` connections (and, in 5.0, to `graphqlDocument` connections when the request originates from the IDE). |
| `restrict_ide_post_visibility_to_author` | `graphql_data_is_private` | Marks an IDE history post private when the current user isn't the author. Gates `node(id)` and `ideHistoryEntry(id)`. |

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
| ~~`/wp/v2/graphql-ide-queries[/{id}]`~~ | ~~`getDocuments` / `createDocument` / `updateDocument` / `deleteDocument`~~ | **Removed in 5.0.** Clients use the `graphql_document` REST surface owned by Smart Cache (or fall back to no document persistence when Smart Cache isn't active). |
| ~~`/wp/v2/graphql-ide-collections[/{id}]`~~ | ~~`getCollections` / `createCollection` / ...~~ | **Removed in 5.0.** Clients use Smart Cache's `graphql_document_group` taxonomy REST surface. |

### Smart Cache integration (`graphql_document` post type)

When Smart Cache is active, the IDE reads and writes saved queries via Smart Cache's primitives:

| Concept | Smart Cache surface |
| --- | --- |
| Saved query document | `graphql_document` post type — `post_content` carries the query body (AST-validated, normalized, slug = SHA-256 hash) |
| Alias / queryId | `graphql_query_alias` taxonomy (one term per alias, plus the hash itself as a term) |
| Allow / deny | `graphql_document_grant` taxonomy (`allow` / `deny` / `''`) |
| Cache-Control max-age | `graphql_document_http_maxage` taxonomy (term name = seconds as string) |
| Collection / group | `graphql_document_group` taxonomy |
| Description | `post_excerpt` (Smart Cache's existing convention) |

Concrete REST exposure of these primitives is owned by Smart Cache. As of Smart Cache 0.x, `graphql_document` is registered with `show_in_rest=true`; collection / alias / grant / maxage taxonomies are not all REST-exposed today, which is a follow-up in Smart Cache itself.

### Custom routes (`/wpgraphql-ide/v1/...`)

Registered in `Rest::register()`. **All routes that operated on `graphql_ide_query` are being reworked in 5.0** to operate on `graphql_document` instead — or removed where Smart Cache already provides equivalent functionality.

| Method | Path | Status |
| --- | --- | --- |
| ~~`POST /documents/{id}/publish`~~ | **Removed in 5.0.** Smart Cache's `Document::save()` already validates + normalizes + hashes on every save; no separate "publish" step is needed. |
| ~~`DELETE /collections/{id}/cascade`~~ | **Removed in 5.0.** Operated on the `graphql_ide_collection` taxonomy, which doesn't exist anymore. Equivalent against `graphql_document_group` may return in a later release if there's demand. |
| `GET /documents/export` | **Being rewritten in 5.0** to export `graphql_document` posts the current user can access. |
| `POST /documents/import` | **Being rewritten in 5.0** to import into `graphql_document`. |
| `POST /documents/reorder` | **Being rewritten in 5.0** to set `menu_order` on `graphql_document` posts. |
| ~~`POST /collections/reorder`~~ | **Removed in 5.0.** Reorder UX moves to per-user `graphql_document_group` ordering in user-meta. |

### Per-document settings REST field

**Being reworked in 5.0.** The Document Settings module previously surfaced a `documentSettings` REST field on `/wp/v2/graphql-ide-queries[/{id}]` that read/wrote `description` / `aliases` / `maxAgeHeader` / `grant`. With the post type removed and the underlying taxonomies now owned by Smart Cache, the field re-emerges on `/wp/v2/graphql-document[/{id}]` (when Smart Cache exposes it) with the same shape but new storage targets. Field registration via `register_graphql_document_setting_field()` continues to work for plugins that want to add additional fields beyond Smart Cache's built-ins.

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
| `scope_ide_queries_to_current_user` | `rest_graphql_ide_history_query` (and, in 5.0, `rest_graphql_document_query` when filtering on behalf of the IDE) | Per-user scoping. |
| `enforce_ide_rest_permissions` | `rest_pre_dispatch` | Cap gate. |
| `restrict_document_to_author` | `rest_prepare_graphql_ide_history` (and, in 5.0, conditional behaviour on `rest_prepare_graphql_document`) | Per-post author check. |

## Capability helpers (PHP)

Two global-namespace helpers wrap the `wpgraphql_ide_capability_required` filter. Use them in extension code instead of hardcoding capability literals.

| Symbol | Source | Purpose |
| --- | --- | --- |
| `wpgraphql_ide_get_capability(): string` | `includes/access-functions.php` | Returns the filtered cap string (default `'manage_graphql_ide'`). Use at registration time — `register_post_type` capability maps, `add_submenu_page` cap arg, `get_users(['capability' => ...])`, etc. Falls back to default if the filter returns a non-string or empty value. |
| `wpgraphql_ide_user_can(): bool` | `includes/access-functions.php` | Equivalent to `current_user_can( wpgraphql_ide_get_capability() )`. Use at runtime — REST `permission_callback`, meta `auth_callback`, gate checks, etc. |

The namespaced wrapper `\WPGraphQLIDE\user_has_graphql_ide_capability()` is preserved for back-compat and now delegates to `wpgraphql_ide_user_can()`. New code should call the global-namespace helper.

## Document Settings module

> **Being simplified in 5.0.** The previous design registered three internal IDE taxonomies (`graphql_ide_query_alias` / `graphql_ide_query_maxage` / `graphql_ide_query_grant`) to back the drawer's built-in fields. Those taxonomies are removed in 5.0 because they duplicate Smart Cache's existing `graphql_query_alias` / `graphql_document_http_maxage` / `graphql_document_grant`. The IDE's built-in fields now bind to Smart Cache's taxonomies directly.

The `register_graphql_document_setting_field()` access function stays — it's still useful for plugins that want to add **additional** fields to the IDE's per-document Settings drawer beyond Smart Cache's built-ins (e.g. team-specific tags, deprecation flags, custom directives metadata, etc.).

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

### Built-in fields (post-5.0 storage targets)

| Field | Type | Storage | Owned by |
| --- | --- | --- | --- |
| `description` | textarea | `post_field` → `post_excerpt` | Smart Cache (its own existing convention). |
| `aliases` | tag_list | `taxonomy` → `graphql_query_alias` (multi, unique) | Smart Cache. |
| `maxAgeHeader` | number | `taxonomy` → `graphql_document_http_maxage` | Smart Cache. |
| `grant` | radio_with_default | `taxonomy` → `graphql_document_grant` | Smart Cache. |

The IDE's built-in field registrations become **bridge code** that maps the drawer UI to Smart Cache's storage. When Smart Cache isn't active the drawer is hidden entirely (no documents to attach settings to).

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
