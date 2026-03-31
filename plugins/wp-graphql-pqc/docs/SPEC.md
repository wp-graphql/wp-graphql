# Persisted Query Cache (PQC) protocol

This document describes the **wire protocol and server behavior** implemented by **WPGraphQL Persisted Queries Cache** (`wp-graphql-pqc`). It is written in the same spirit as [Automatic Persisted Queries (APQ) in Apollo Server](https://www.apollographql.com/docs/apollo-server/performance/apq): clients and proxies can rely on stable URLs, deterministic hashes, and predictable HTTP semantics.

PQC is **not** byte-for-byte identical to Apollo APQ. It is tailored to **WordPress + WPGraphQL + WPGraphQL Smart Cache**: permalink-style URLs, Smart Cache **query analyzer** keys for invalidation, and an optional **nonce** gate for first-time document registration.

---

## Goals

1. **Short, cacheable GET URLs** — Persisted operations use paths like `/graphql/persisted/{queryHash}` (and optional `/variables/{variablesHash}`) so CDNs and browsers can cache **GET** responses where appropriate.
2. **Origin execution on cache miss** — When an edge cache does not have a response, the request reaches WordPress; the plugin loads the stored document and runs `graphql()` so behavior matches a normal GraphQL request.
3. **Surgical invalidation** — The server maintains an index from **Smart Cache keys** (e.g. list/post node ids, `list:post`) to **persisted URLs**. When Smart Cache fires `graphql_purge`, PQC resolves affected URLs and purges them via a host adapter (e.g. VIP) while optionally removing index rows.

---

## Prerequisites

- **WPGraphQL** and **WPGraphQL Smart Cache** must be active.
- The **Query Analyzer** must produce cache keys for operations you want indexed (Smart Cache setting `query_analyzer_enabled`). If an operation produces no keys, PQC does not store index entries or return `persistedQueryUrl`.

---

## URL shape

Default base path (filterable, see [INTEGRATIONS.md](./INTEGRATIONS.md)):

| Case | Path |
|------|------|
| No variables | `/{base}{queryHash}` |
| With variables | `/{base}{queryHash}/variables/{variablesHash}` |

- `{base}` defaults to `graphql/persisted/` (leading site path slash is implied in canonical URLs stored in the index, e.g. `/graphql/persisted/…`).
- `{queryHash}` and `{variablesHash}` are **64-character lowercase hex** SHA-256 digests.

Rewrites map these paths to WordPress; the plugin handles them on `template_redirect` (GET only).

---

## Hash algorithms

### Query document hash

1. Parse the query document with the GraphQL parser and **print** it canonically (`GraphQL\Language\Parser` + `Printer::doPrint`), same normalization idea as in WPGraphQL core.
2. `queryHash = sha256_hex( normalized_document )`.

The **stored document** in the index is this **normalized** string so warm GET execution always matches the hash.

### Variables hash

- If there are no variables (or empty): `variablesHash` is treated as **empty string** in URLs and lookups; no second path segment.
- If variables exist: recursively sort keys, `json_encode` with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, then `variablesHash = sha256_hex( json )`.

---

## Cold path: GET before the document is registered

**Request:** `GET` persisted URL for a `queryHash` (and `variablesHash` if applicable) **not** yet present in the store (no matching document + execution row).

**Response:** HTTP **200** with a GraphQL-style JSON body:

- `errors[]` with `extensions.code` **`PERSISTED_QUERY_NOT_FOUND`**
- Top-level `extensions.persistedQueryNonce`: opaque token the client must send back on POST to register the document (unless nonce is disabled via filter).

**Semantics:** Same idea as APQ’s first request: “hash unknown, here is how to register.” PQC uses **200** (not 404) so clients always receive JSON, consistent with GraphQL over HTTP practice for application-level errors.

---

## Warm path: GET after registration

**Request:** `GET` same URL when the store has a row linking `queryHash` + `variablesHash` to a stored document.

**Response:** HTTP **200** with a normal GraphQL payload (`data`, optional `errors`, optional `extensions` from WPGraphQL / Smart Cache). There is **no** `persistedQueryNonce` in this success path.

**Headers:**

- **Anonymous** users: public cache headers (by default aligned with Smart Cache global max-age or filter `wpgraphql_pqc_cache_max_age`).
- **Authenticated** users: `Cache-Control: no-store` to avoid caching user-specific responses at shared caches.

This is the path that runs when a **CDN miss** forwards to origin: WordPress executes the operation and returns fresh JSON.

---

## Registration: POST with full query + extensions

To register a missing document, the client sends a normal GraphQL **POST** to the standard WPGraphQL endpoint (`/graphql`) with:

- `query`: full operation text (should normalize to the same document as `queryHash`).
- Optional `variables`.
- `extensions` (on the operation params), for example:

```json
{
  "persistedQueryNonce": "<from cold GET>",
  "persistedQueryHash": "<64-hex query hash>",
  "persistedVariablesHash": "<64-hex variables hash or empty string>"
}
```

**Server behavior (summary):**

1. Computes `queryHash` / `variablesHash` from the request body; validates nonce when required (`wpgraphql_pqc_require_nonce`).
2. Enforces **Smart Cache “grant mode”** for **creating** new documents (public vs authenticated-only); existing documents can receive new execution/index rows when allowed by plugin logic.
3. Persists normalized document and, for each analyzer key, rows associating the **canonical persisted URL** with that **cache key**.
4. Adds `extensions.persistedQueryUrl` to the response when indexing occurred.

Nonce is stored in a **short-lived transient** and marked used after successful storage.

---

## Invalidation

Smart Cache (and the stack around it) dispatches:

```text
do_action( 'graphql_purge', $cache_key, $event, $hostname );
```

PQC’s purge handler:

1. Resolves **all persisted URLs** tagged with `$cache_key` via the store.
2. Logs purge intent (when `WP_DEBUG` is on).
3. Calls the **purge adapter** per URL (e.g. VIP edge purge).
4. By default, **removes `url_keys` rows for the purged cache key only** (`delete_by_key`), so the edge is invalidated but the persisted **execution** row (query + variables) remains and warm GET can re-execute at origin. Filter `wpgraphql_pqc_delete_entries_on_purge` can disable tag deletion for testing.

---

## Error codes (non-exhaustive)

| Code | When |
|------|------|
| `PERSISTED_QUERY_NOT_FOUND` | Cold GET; hash not in index |
| `INVALID_QUERY_HASH` / `INVALID_VARIABLES_HASH` | Malformed hash segments in URL |
| `PERSISTED_QUERY_DOCUMENT_INVALID` | Stored document missing or fails GraphQL parse |
| `PQC_INTERNAL_ERROR` | Unexpected failure; message may be generic when `WP_DEBUG` is off |

---

## Comparison to Apollo APQ

| Topic | Apollo APQ (typical) | WPGraphQL PQC |
|-------|----------------------|---------------|
| Transport for hash-only request | Often GET `/graphql?query=…` or APQ `extensions` | **GET** dedicated **permalink** path |
| Registration | Client sends hash + query (e.g. GET or POST per server) | **POST** `/graphql` + **nonce** from cold GET |
| Cache invalidation | Server/framework specific | **Smart Cache keys** → URL index → `graphql_purge` |
| Document identity | SHA-256 of query string | SHA-256 of **normalized** printed document |

For Apollo’s full client/server flow, see their [Automatic Persisted Queries](https://www.apollographql.com/docs/apollo-server/performance/apq) documentation.

---

## Related reading

- [INTEGRATIONS.md](./INTEGRATIONS.md) — Filters, custom stores (Redis, etc.), purge adapters
- [../README.md](../README.md) — Overview and quick start
- [../PRD.md](../PRD.md) — Product requirements and MySQL schema (default store)
