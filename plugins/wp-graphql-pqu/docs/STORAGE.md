# Storage: Smart Cache documents vs Persisted Query URLs tables

**WPGraphQL Persisted Query URLs** (plugin directory: `wp-graphql-pqu`; PHP namespace `WPGraphQL\PQU`) adds **permalink GET URLs** and a **URL ↔ Smart Cache key** index for edge purging. It depends on **WPGraphQL Smart Cache**, which already persists query **documents** in WordPress.

This document clarifies **two storage stories** and why they coexist today, and sketches **future alignment** so we do not duplicate document semantics unnecessarily.

---

## What WPGraphQL Smart Cache already stores

Smart Cache registers a private post type **`graphql_document`** (`WPGraphQL\SmartCache\Document::TYPE_NAME`). Persisted query documents are saved as posts:

- **Normalized query text** lives in `post_content` (parse + print, same general approach as core hashing).
- **Identity** is tied to a **normalized hash** (stored as `post_name` and as a term in the **`graphql_query_alias`** taxonomy) so lookups by hash or alias work.
- **Admin UI**, **allow/deny**, **grant mode**, and **GraphQL mutations** (`createGraphqlDocument`, etc.) all target this CPT.

See Smart Cache’s [persisted-queries.md](../../wp-graphql-smart-cache/docs/persisted-queries.md) and `plugins/wp-graphql-smart-cache/src/Document.php`.

---

## What Persisted Query URLs adds (custom tables)

The default store is **`WPGraphQL\PQU\Store\DBStore`**, backed by tables created in `Schema::ensure_schema()`:

| Concern | Role |
|--------|------|
| **Documents** (`wpgraphql_pqu_documents`) | `query_hash` → normalized `query_document` for warm GET and registration. |
| **Executions** (`wpgraphql_pqu_executions`) | Stable `(query_hash, variables_hash)` → `url`, `variables` JSON, `last_executed_at` (warm GET path; survives key-map purges). |
| **URL / key map** (`wpgraphql_pqu_urls`, `wpgraphql_pqu_cache_keys`, `wpgraphql_pqu_key_urls`) | Canonical persisted **paths**, deduplicated **analyzer keys**, and **many-to-many** links for `graphql_purge` → URL resolution. |

Reasons this layer uses **custom tables** today:

1. **Junction-heavy index** — Many cache keys per URL and many URLs per key map cleanly to relational tables and targeted `DELETE`s during purge/GC.
2. **Execution row** — A compact row keyed by `(query_hash, variables_hash)` is optimized for the warm GET path without loading post meta or CPT overhead on every request.
3. **Evolution** — Implemented and iterated while the permalink protocol was being defined; CPT integration was not a hard dependency for the first beta.

**Important:** Both stacks normalize the query document before hashing (parse + print). For a given operation text, the **same SHA-256 `query_hash`** should match Smart Cache’s document hash **if** normalization rules stay aligned. Any future “single source of truth” for the **bytes** of the document should preserve that invariant.

---

## Overlap and duplication today

When a client **registers** via POST with this plugin’s extensions, **Persisted Query URLs** can insert a row into **`wpgraphql_pqu_documents`** even if Smart Cache has **already** created a `graphql_document` post for the same normalized query (e.g. after a mutation or admin save). The two stores are **not** wired to deduplicate automatically.

Symptoms of duplication (future work):

- Two persistence paths for the “same” logical document.
- Admin/editing workflows live on the CPT; permalink index rows may not reflect CPT-only changes until something re-registers through this plugin’s POST path.

---

## Future directions (worth visiting)

These are **design options**, not commitments:

1. **CPT as source of truth for document text**  
   On registration or warm GET, **resolve `query_hash` → `graphql_document` post** (via Smart Cache helpers / taxonomy) and **stop writing duplicate document blobs** into `wpgraphql_pqu_documents`, or treat the table as a cache of CPT content.

2. **Dual-write on registration**  
   When this plugin stores a new document, call into Smart Cache’s document APIs (or `wp_insert_post` with the same shape `Document::save` uses) so **Saved Queries** UI and allow/deny stay authoritative.

3. **Read-through warm GET**  
   If `wpgraphql_pqu_documents` is empty but a CPT exists for the hash, load **`post_content`** from `graphql_document` and optionally backfill the execution/table row.

4. **Keep tables only for URL + key map**  
   Narrow custom tables to **executions + key map**; documents always loaded from Smart Cache. Requires clear rules when CPT is missing (cold path still needs registration).

Each option trades **complexity**, **compatibility with existing installs**, and **coupling** to Smart Cache internals. A spike should include: grant mode, nonce flow, and `graphql_document` GraphQL mutations vs HTTP POST registration.

---

## Related reading

- [SPEC.md](./SPEC.md) — Wire protocol (URLs, hashes, cold/warm, POST registration).
- [INTEGRATIONS.md](./INTEGRATIONS.md) — `StoreInterface`, custom stores, filters (`wpgraphql_pqu_*` prefix is historical; see protocol spec).
- [WPGraphQL Smart Cache persisted queries](../../wp-graphql-smart-cache/docs/persisted-queries.md)
