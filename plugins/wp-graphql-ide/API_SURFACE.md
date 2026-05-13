# IDE API Surface

Inventory of which IDE surfaces use WPGraphQL and which use REST, plus the server-side registries (Document Settings, user-preference meta, WPGraphQL admin settings) that the IDE owns. Update on every PR that adds, moves, or removes a server endpoint or registered surface.

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

| Type | Source |
| --- | --- |
| `IdeQuery` / `IdeQueries` | `register_post_type('graphql_ide_query', show_in_graphql=true)` |
| `IdeHistoryEntry` / `IdeHistoryEntries` | `register_post_type('graphql_ide_history', show_in_graphql=true)` |
| `IdeCollection` / `IdeCollections` | `register_taxonomy('graphql_ide_collection', show_in_graphql=true)` |
| `UpdateGraphqlSettingValueInput` | OneOf input — variant per WPGraphQL setting field type (`text`, `number`, `checkbox`, `select`, `radio`, `user_role_select`, `password`). See `includes/settings.php`. |
| `UpdateGraphqlSettingValue` | Output object — same variant shape as the input. |
| `UpdateGraphqlSettingPayload` | Mutation output. |

### Custom fields

Registered in `register_ide_graphql_fields()`.

| Type | Field | Backed by |
| --- | --- | --- |
| `IdeQuery` | `queryString: String` | `post_content` |
| `IdeQuery` | `variables: String` | `_graphql_ide_variables` |
| `IdeQuery` | `headers: String` | `_graphql_ide_headers` |
| `IdeHistoryEntry` | `queryString: String` | `_graphql_ide_query` |
| `IdeHistoryEntry` | `variables: String` | `_graphql_ide_variables` |
| `IdeHistoryEntry` | `headers: String` | `_graphql_ide_headers` |
| `IdeHistoryEntry` | `durationMs: Int` | `_graphql_ide_duration_ms` |
| `IdeHistoryEntry` | `executionStatus: String` | `_graphql_ide_status` |
| `IdeHistoryEntry` | `documentId: Int` | `_graphql_ide_document_id` |
| `IdeHistoryEntry` | `isAuthenticated: Boolean` | `_graphql_ide_is_authenticated` |
| `IdeHistoryEntry` | `httpMethod: String` | `_graphql_ide_http_method` |

### Mutations

| Mutation | Source | Purpose |
| --- | --- | --- |
| `updateGraphqlSetting(section!, field!, value: UpdateGraphqlSettingValueInput!)` | `includes/settings.php::register_graphql_mutation()` | Persist a single WPGraphQL setting through GraphQL, so the in-IDE Settings tab doesn't need a REST round-trip. Requires the capability returned by `graphql_manage_settings_cap` (default `manage_options`). The `value` input is a OneOf input — variant must match the registered field type. |

### Authorization

| Filter | Hook | Purpose |
| --- | --- | --- |
| `scope_ide_graphql_connections_to_current_user` | `graphql_connection_query_args` | Adds `author = current_user_id()` to `IdeQuery` and `IdeHistoryEntry` connections. |
| `restrict_ide_post_visibility_to_author` | `graphql_data_is_private` | Marks an IDE post private when the current user isn't the author. Gates `node(id)` and `ideQuery(id)`. |

### Client callsites

| Callsite | File |
| --- | --- |
| User search (Share dialog) | `src/components/dialogs/ShareCollectionDialog.jsx` |
| GraphQL fetch wrapper | `src/api/graphql-client.js` |

## REST

### CPT routes (`/wp/v2/...`)

| Method | Path | Client function |
| --- | --- | --- |
| `GET` | `/wp/v2/graphql-ide-queries` | `getDocuments()` |
| `POST` | `/wp/v2/graphql-ide-queries` | `createDocument()` |
| `POST` | `/wp/v2/graphql-ide-queries/{id}` | `updateDocument()` |
| `DELETE` | `/wp/v2/graphql-ide-queries/{id}` | `deleteDocument()` |
| `GET` | `/wp/v2/graphql-ide-collections` | `getCollections()` |
| `POST` | `/wp/v2/graphql-ide-collections` | `createCollection()` |
| `POST` | `/wp/v2/graphql-ide-collections/{id}` | `updateCollection()` |
| `DELETE` | `/wp/v2/graphql-ide-collections/{id}` | `deleteCollection()` |
| `GET` | `/wp/v2/graphql-ide-history` | `getHistory()` |
| `POST` | `/wp/v2/graphql-ide-history` | `createHistoryEntry()` |
| `DELETE` | `/wp/v2/graphql-ide-history/{id}` | `deleteHistoryEntry()` |
| `GET` | `/wp/v2/users/me?_fields=meta` | `getPreferences()` |
| `POST` | `/wp/v2/users/me` | `savePreference()` |

### Custom routes (`/wpgraphql-ide/v1/...`)

Registered in `register_ide_rest_routes()`.

| Method | Path | Client function |
| --- | --- | --- |
| `POST` | `/documents/{id}/publish` | `publishDocument()` |
| `DELETE` | `/collections/{id}/cascade` | `deleteCollectionWithContents()` |
| `GET` | `/documents/export` | `exportDocuments()` |
| `POST` | `/documents/import` | `importDocuments()` |
| `POST` | `/documents/reorder` | `reorderDocuments()` |
| `POST` | `/collections/reorder` | `reorderCollections()` |

### Per-document settings REST field

Registered in `includes/document-settings/rest.php` and exposed via the CPT REST resource. See **Document Settings module** below for the registry that backs it.

| REST resource | Field | Shape |
| --- | --- | --- |
| `/wp/v2/graphql-ide-queries[/{id}]` | `documentSettings` | `object` — keyed by registered field name (e.g. `description`, `aliases`, `maxAgeHeader`, `grant`). Read returns a key/value map of every field the current user can read. Update accepts the same shape and dispatches each value through the storage adapter; unknown keys are silently ignored for forward compatibility. |

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

| Filter | Hook |
| --- | --- |
| `scope_ide_queries_to_current_user` | `rest_graphql_ide_query_query`, `rest_graphql_ide_history_query` |
| `enforce_ide_rest_permissions` | `rest_pre_dispatch` |
| `restrict_document_to_author` | `rest_prepare_graphql_ide_query`, `rest_prepare_graphql_ide_history` |

## Document Settings module

The IDE's per-document Settings drawer (description, alias names, max-age, allow/deny) is built on an extensible field registry. Plugins register additional fields with `register_graphql_document_setting_field()` and the drawer renders them automatically; values are persisted via the existing `/wp/v2/graphql-ide-queries` REST endpoints.

### Registration flow

1. `includes/document-settings.php` fires `do_action( 'wpgraphql_ide_register_document_settings' )` on `init` (priority 11), after taxonomies are registered and after WPGraphQL has finished its own registrations.
2. Callbacks call `register_graphql_document_setting_field( $name, $config )` to contribute fields. Built-in fields (`description`, `aliases`, `maxAgeHeader`, `grant`) register through this same path — see `includes/document-settings/built-in-fields.php`.
3. `Registry::instance()` holds the field descriptors. Other modules read from it to drive REST exposure, JS localization, and storage dispatch.

### Public PHP API

| Symbol | Source | Purpose |
| --- | --- | --- |
| Action `wpgraphql_ide_register_document_settings` | `includes/document-settings.php:34` | Single registration entry point — fires once on `init` priority 11. |
| `register_graphql_document_setting_field( string $name, array $config ): void` | `includes/document-settings/access-functions.php` | Register a field. See config shape below. |

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

### Built-in fields

| Field | Type | Storage | Purpose |
| --- | --- | --- | --- |
| `description` | textarea | `post_field` → `post_excerpt` | Free-form description of the query. |
| `aliases` | tag_list | `taxonomy` → `graphql_ide_query_alias` (multi, unique) | Alternate names that can execute this query in place of its hash. |
| `maxAgeHeader` | number | `taxonomy` → `graphql_ide_query_maxage` | `Cache-Control: max-age` value (seconds) sent with responses for this query. |
| `grant` | radio_with_default | `taxonomy` → `graphql_ide_query_grant` | Override the global default for whether this query may execute. Options: `allow`, `deny`, `''` (use global). |

### Internal taxonomies

Backing storage for the three taxonomy-kind built-in fields. All three are private (`show_in_rest=false`, `show_in_graphql=false`, `show_ui=false`) and attached only to `graphql_ide_query`. Term capabilities all require `manage_graphql_ide`.

| Taxonomy | Cardinality | Term shape |
| --- | --- | --- |
| `graphql_ide_query_alias` | multi | Term name is an alias string; cross-document unique. |
| `graphql_ide_query_maxage` | single | Term name is a non-negative integer string. |
| `graphql_ide_query_grant` | single | Term name is one of `allow`, `deny`, or `''`. |

> **Stability note:** These taxonomy slugs are part of the on-disk schema. Plugins targeting them directly (e.g. via `wp_get_object_terms`) can rely on them — renaming would be a breaking change.

### REST exposure

Surfaced as the `documentSettings` object field on `/wp/v2/graphql-ide-queries[/{id}]`. See **REST → Per-document settings REST field** above.

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
| A | Meta inputs (`variables`, `headers`, `queryString`) missing on auto-generated `createIdeQuery` / `updateIdeQuery` / `createIdeHistoryEntry`. | All document/history write paths. |
| B | No GraphQL mutations for the six custom REST routes (`publish`, `cascade`, `export`, `import`, `reorder` × 2). | Publish, cascade-delete, bulk import/export, reorder. |
| C | Eight `wpgraphql_ide_*` user-preference metas are not on the GraphQL schema (REST only). | Any GraphQL-only client reading or writing preferences. |
| D | No GraphQL mutation for the `documentSettings` REST field on `IdeQuery`. | Per-document settings can only be written via REST. |
