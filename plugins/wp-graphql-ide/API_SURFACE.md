# IDE API Surface

Running inventory of which parts of the IDE talk to the server through **WPGraphQL** versus **REST**, and the reasoning behind each choice. Keep this current as endpoints move — it's the artifact that prevents the codebase from sliding into a permanent half-migrated state.

> **TL;DR**: Read paths for IDE data (`IdeQuery`, `IdeHistoryEntry`, `IdeCollection`) are exposed in the public WPGraphQL schema and available to third parties. The IDE itself still uses REST internally for writes and bespoke workflow endpoints — those are migration candidates documented in [Gaps](#gaps-blocking-full-graphql-adoption).

## When to choose GraphQL

- The data is a first-class IDE entity (saved query, history entry, collection) that other clients might also want to read.
- The query benefits from selecting a subset of fields or following a connection (e.g. `IdeQuery.author { name }`).
- The work is a bespoke read against the WPGraphQL schema (e.g. user search, schema introspection).

## When to choose REST

- The endpoint is a **bespoke workflow** that doesn't map cleanly to a CRUD verb on a single entity (publish-and-rewrite-references, cascade-delete, bulk reorder, export, import).
- The data is a **JSON-blob user preference** that has no schema shape worth modeling (collapsed-section state, panel order, sort modes).
- The endpoint already exists, works, and migrating it would expand the public schema contract without a clear consumer benefit.

The bias is toward GraphQL for new public-shaped reads, REST for workflow + preference writes. Mixing is fine when the trade-off is honest — but document the choice here so reviewers can challenge it.

---

## GraphQL surface (today)

Exposed types and root fields are part of the **public WPGraphQL schema**. Renaming/removing them is a breaking change.

### Types

| Type | Source | Notes |
| --- | --- | --- |
| `IdeQuery` / `IdeQueries` | `register_post_type('graphql_ide_query', show_in_graphql=true)` | Saved query documents. |
| `IdeHistoryEntry` / `IdeHistoryEntries` | `register_post_type('graphql_ide_history', show_in_graphql=true)` | Execution history rows. |
| `IdeCollection` / `IdeCollections` | `register_taxonomy('graphql_ide_collection', show_in_graphql=true)` | Folder-style grouping for saved queries. |

### Custom fields registered via `register_graphql_field`

Defined in `register_ide_graphql_fields()` in `wpgraphql-ide.php`.

| Type | Field | Backed by | Reasoning |
| --- | --- | --- | --- |
| `IdeQuery` | `queryString: String` | `post_content` | The query body lives in `post_content` so revisions/autosave work, but `content` leaks the storage detail. `queryString` is the consumer-facing name. |
| `IdeQuery` | `variables: String` | `_graphql_ide_variables` meta | JSON-encoded variables. |
| `IdeQuery` | `headers: String` | `_graphql_ide_headers` meta | JSON-encoded HTTP headers. |
| `IdeHistoryEntry` | `queryString: String` | `_graphql_ide_query` meta | Same naming rationale as above. |
| `IdeHistoryEntry` | `variables: String` | `_graphql_ide_variables` meta | |
| `IdeHistoryEntry` | `headers: String` | `_graphql_ide_headers` meta | |
| `IdeHistoryEntry` | `durationMs: Int` | `_graphql_ide_duration_ms` meta | |
| `IdeHistoryEntry` | `status: String` | `_graphql_ide_status` meta | success/error/etc. |
| `IdeHistoryEntry` | `documentId: Int` | `_graphql_ide_document_id` meta | DB id of the `IdeQuery` this was executed against. |
| `IdeHistoryEntry` | `isAuthenticated: Boolean` | `_graphql_ide_is_authenticated` meta | |
| `IdeHistoryEntry` | `httpMethod: String` | `_graphql_ide_http_method` meta | |

### Connection scoping

`scope_ide_graphql_connections_to_current_user()` adds `author = get_current_user_id()` to every `IdeQuery` and `IdeHistoryEntry` connection so per-user isolation matches the REST endpoints. Without it, anyone with `manage_graphql_ide` could enumerate every other user's saved queries.

### Client-side GraphQL callsites

| Callsite | File | Reasoning |
| --- | --- | --- |
| User search for the share dialog | `src/components/dialogs/ShareCollectionDialog.jsx` | Reading `users` is already in the WPGraphQL schema; using GraphQL keeps the IDE on its own surface. Migrated from `apiFetch('/wp/v2/users')` in commit `0bf73cb5`. |
| Generic GraphQL client | `src/api/graphql-client.js` | Wraps `fetch` with nonce/credentials and returns a typed `GraphQLClientError` (HTTP status + `errors[]` preserved). Used by the share dialog today; intended as the migration path for read-only surfaces. |

---

## REST surface (today)

### Standard CPT REST endpoints (`/wp/v2/...`)

| Method | Path | Used by | Why still REST |
| --- | --- | --- | --- |
| `GET` | `/wp/v2/graphql-ide-queries` | `getDocuments()` | List saved queries. Could move to `query { ideQueries }` immediately — see [Gap A](#gap-a-meta-inputs-on-auto-generated-mutations). |
| `POST` | `/wp/v2/graphql-ide-queries` | `createDocument()` | Create. Auto-generated `createIdeQuery` mutation exists, but custom-meta inputs (`variables`, `headers`) aren't wired. |
| `POST` | `/wp/v2/graphql-ide-queries/{id}` | `updateDocument()` | Update. Same blocker as create. |
| `DELETE` | `/wp/v2/graphql-ide-queries/{id}` | `deleteDocument()` | Auto-generated `deleteIdeQuery` mutation exists — could migrate today, but bundling with create/update keeps the migration coherent. |
| `GET` | `/wp/v2/graphql-ide-collections` | `getCollections()` | Could move to `query { ideCollections }` immediately. |
| `POST` | `/wp/v2/graphql-ide-collections` | `createCollection()` | Same blocker as documents. |
| `POST` | `/wp/v2/graphql-ide-collections/{id}` | `updateCollection()` | Same. |
| `DELETE` | `/wp/v2/graphql-ide-collections/{id}` | `deleteCollection()` | See cascade-delete row below — most deletes go through the cascade route, not this one. |
| `GET` | `/wp/v2/graphql-ide-history` | `getHistory()` | Could move to `query { ideHistoryEntries }` immediately. |
| `POST` | `/wp/v2/graphql-ide-history` | `createHistoryEntry()` | Same meta-input blocker. |
| `DELETE` | `/wp/v2/graphql-ide-history/{id}` | `deleteHistoryEntry()` | Migratable today; bundle with create. |
| `GET` | `/wp/v2/users/me?_fields=meta` | `getPreferences()` | User-pref blob lookup. The individual meta keys aren't on the WPGraphQL `User` type — see [Gap C](#gap-c-user-preference-blobs-not-in-the-schema). |
| `POST` | `/wp/v2/users/me` | `savePreference()` | Same. |

### Custom IDE workflow routes (`/wpgraphql-ide/v1/...`)

These don't map cleanly to a CRUD verb on a single entity. They live in `register_ide_rest_routes()` in `wpgraphql-ide.php`.

| Method | Path | Used by | Why custom |
| --- | --- | --- | --- |
| `POST` | `/documents/{id}/publish` | `publishDocument()` | Publishing isn't a single update — it forks the working draft into an immutable published doc, rewrites references, and returns both ids. Workflow, not CRUD. |
| `DELETE` | `/collections/{id}/cascade` | `deleteCollectionWithContents()` | Cascade-delete a collection plus every saved query it contains, in one transaction. |
| `GET` | `/documents/export` | `exportDocuments()` | Bulk read that returns a single JSON blob shaped to match `seeds/example-documents.json` (so importer eats its own output). |
| `POST` | `/documents/import` | `importDocuments()` | Bulk write with validation, dedup against existing docs, and term creation. |
| `POST` | `/documents/reorder` | `reorderDocuments()` | Batch update of `menu_order` across N docs. |
| `POST` | `/collections/reorder` | `reorderCollections()` | Batch reorder of taxonomy terms. |

### Permission + scoping filters (REST)

- `scope_ide_queries_to_current_user()` on `rest_graphql_ide_query_query` and `rest_graphql_ide_history_query`.
- `enforce_ide_rest_permissions()` on `rest_pre_dispatch` — gates every IDE REST route on `manage_graphql_ide`.
- `restrict_document_to_author()` on `rest_prepare_graphql_ide_query` / `rest_prepare_graphql_ide_history` — single-doc author check.

The GraphQL surface mirrors these via `scope_ide_graphql_connections_to_current_user`, but **single-node access by global ID is not gated** — users with `manage_graphql_ide` can `node(id: "...")` an arbitrary `IdeQuery` if they know its global ID. This is a [known gap](#gap-d-single-node-id-access-not-author-gated).

---

## Gaps blocking full GraphQL adoption

These would all need to land before the IDE could remove `@wordpress/api-fetch` entirely.

### Gap A — Meta inputs on auto-generated mutations

WPGraphQL auto-generates `createIdeQuery` / `updateIdeQuery` / `createIdeHistoryEntry` from the CPT registration, but the input types only carry core post fields. To set `variables`, `headers`, or read `queryString` on create/update, the input types need additional fields registered (likely via `graphql_input_fields` filter or `register_graphql_field` against the input type).

### Gap B — Custom workflow mutations

Each of the six bespoke REST routes needs a corresponding GraphQL mutation:

- `publishIdeQuery(input: { id: ID! }): PublishIdeQueryPayload`
- `cascadeDeleteIdeCollection(input: { id: ID! }): DeleteIdeCollectionPayload`
- `exportIdeDocuments(input: {}): ExportIdeDocumentsPayload`
- `importIdeDocuments(input: { payload: String! }): ImportIdeDocumentsPayload`
- `reorderIdeQueries(input: { ids: [ID!]! }): ReorderIdeQueriesPayload`
- `reorderIdeCollections(input: { ids: [ID!]! }): ReorderIdeCollectionsPayload`

Each requires `register_graphql_mutation()` plumbing and matching payload types. This is the largest piece of work in a full migration.

### Gap C — User preference blobs not in the schema

The IDE persists eight JSON-blob user metas (`wpgraphql_ide_panel_order`, `wpgraphql_ide_section_states`, `wpgraphql_ide_collapsed_notices`, `wpgraphql_ide_personal_collections`, `wpgraphql_ide_seen_shared_collections`, `wpgraphql_ide_collection_order`, `wpgraphql_ide_collection_sort_modes`, `wpgraphql_ide_theme`).

Two viable shapes for migration:

1. Register each meta as a typed `User` field with a setter mutation (`updateIdePanelOrder`, etc.). Honest about the JSON shape but produces a wide and ugly schema.
2. Wrap them in an `IdeUserPreferences` object type with `getIdePreferences { ... }` query and a single `updateIdePreference(input: { key: String!, value: String! })` mutation. Cleaner schema, but the JSON-blob nature is still leaking through `value: String`.

Pick the second for any future migration unless one of the blobs grows into a structured shape worth modeling individually.

### Gap D — Single-node ID access not author-gated

`scope_ide_graphql_connections_to_current_user` only filters list connections. A `node(id: "<base64 of post:123>")` query still resolves an `IdeQuery` even if the requester isn't the author. Either add a `pre_resolve_node` style filter that rejects cross-author lookups, or shift the author check into the model layer (`WPGraphQL\Model\Post`) via the existing `is_private` mechanism.

This is the most urgent gap to close before the schema is widely used by third parties.

---

## How to update this file

When you add, move, or remove a server-side endpoint:

1. Find the row(s) in this doc and update them. Don't add a new row without removing the obsoleted one — staleness is the failure mode for this kind of doc.
2. If you migrated something off REST, update the **Gaps** section if your change closed one.
3. Note breaking-change implications in the commit message (any field/type rename in the GraphQL section is breaking).

Pair this file with the actual code in:

- `plugins/wp-graphql-ide/wpgraphql-ide.php` — server-side schema + REST registrations
- `plugins/wp-graphql-ide/src/api/` — client-side wrappers (`documents.js`, `history.js`, `preferences.js`, `graphql-client.js`)
