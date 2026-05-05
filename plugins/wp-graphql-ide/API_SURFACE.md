# IDE API Surface

Inventory of which IDE surfaces use WPGraphQL and which use REST. Update on every PR that adds, moves, or removes a server endpoint.

Source files:

- `wpgraphql-ide.php` — schema and REST registrations
- `src/api/` — client wrappers (`documents.js`, `history.js`, `preferences.js`, `graphql-client.js`)

## GraphQL

### Types

| Type | Source |
| --- | --- |
| `IdeQuery` / `IdeQueries` | `register_post_type('graphql_ide_query', show_in_graphql=true)` |
| `IdeHistoryEntry` / `IdeHistoryEntries` | `register_post_type('graphql_ide_history', show_in_graphql=true)` |
| `IdeCollection` / `IdeCollections` | `register_taxonomy('graphql_ide_collection', show_in_graphql=true)` |

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

### Authorization

| Filter | Hook |
| --- | --- |
| `scope_ide_queries_to_current_user` | `rest_graphql_ide_query_query`, `rest_graphql_ide_history_query` |
| `enforce_ide_rest_permissions` | `rest_pre_dispatch` |
| `restrict_document_to_author` | `rest_prepare_graphql_ide_query`, `rest_prepare_graphql_ide_history` |

## Gaps

| # | Gap | Blocks |
| --- | --- | --- |
| A | Meta inputs (`variables`, `headers`, `queryString`) missing on auto-generated `createIdeQuery` / `updateIdeQuery` / `createIdeHistoryEntry`. | All document/history write paths. |
| B | No GraphQL mutations for the six custom REST routes (`publish`, `cascade`, `export`, `import`, `reorder` × 2). | Publish, cascade-delete, bulk import/export, reorder. |
| C | Eight `wpgraphql_ide_*` user-preference metas not on the schema. | All `getPreferences` / `savePreference` callsites. |
