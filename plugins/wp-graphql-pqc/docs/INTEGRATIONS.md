# Integrations: custom storage, purging, and filters

This guide is for **hosts**, **platform teams**, and **plugin authors** who need to replace default behavior: swap MySQL for **Redis** (or another KV store), plug in a **purge** integration, or tune URLs and security.

---

## Pluggable index store (`StoreInterface`)

The default implementation is `WPGraphQL\PQC\Store\DBStore`: two MySQL tables for **documents** (`query_hash` → normalized `query_document`) and **URL ↔ cache key** mappings.

You can replace it entirely by returning your own instance from the filter:

```php
add_filter( 'wpgraphql_pqc_store', function ( $store ) {
	return new MyRedisPQCStore();
}, 10, 1 );
```

Your class **must** implement `WPGraphQL\PQC\Store\StoreInterface`:

| Method | Contract |
|--------|----------|
| `store( … )` | Upsert document (if `$store_document` and not already present) and **for each** string in `$cache_keys`, record that the canonical `$url` is associated with that cache key. Same URL may appear with many keys. |
| `get_query( $query_hash, $variables_hash )` | Return `['query_document' => string, 'variables' => string]` or **`null`** if this execution is unknown. Backed by a stable **execution** record independent of cache-tag rows. |
| `touch_execution( $query_hash, $variables_hash )` | Optional freshness signal after a successful warm GET (default DB store updates `last_executed_at`). |
| `get_urls_for_key( $cache_key )` | Distinct list of **paths** like `/graphql/persisted/…` used for purge lookups. |
| `delete_by_key( $cache_key )` | Remove **tag** rows for that cache key (custom / manual invalidation). |
| `delete_by_url( $url )` | Remove all **tag** rows for that URL (default store: used after edge purge for each purged path; does not remove the execution record used for warm GET). |
| `document_exists( $query_hash )` | Whether the normalized document is already stored for this hash. |

**Reference implementation:** `src/Store/DBStore.php` (join between documents and url_keys for `get_query`).

### Redis (or KV) design notes

The default schema is relational; in Redis you typically maintain **multiple indexes**:

1. **Document** — Key: `pqc:doc:{query_hash}` → normalized query string (STRING).
2. **Execution** — Key: `pqc:exec:{query_hash}:{variables_hash}` → variables JSON (STRING); use with (1) for warm GET.
3. **Cache key → URLs** — Set or sorted set: `pqc:key:{cache_key}` → members = canonical URL paths (for `get_urls_for_key`).
4. **URL → metadata** — Hash or set per URL listing cache keys, for efficient `delete_by_url` (delete URL from every relevant `pqc:key:*` set).

Use **transactions** (`MULTI`/`EXEC`) or Lua where you need atomic updates across keys.

### Garbage collection caveat

The bundled cron (`wpgraphql_pqc_garbage_collection`) runs **SQL deletes** against the default MySQL table names in `GarbageCollection::run()`. It does **not** go through `StoreInterface`.

If you use a custom store only:

- Implement **TTL or cleanup inside your backend**, or
- Hook early on `wpgraphql_pqc_garbage_collection` with your own cleanup and accept that the default handler may still run against empty/default tables, or
- Propose/contribute a filter in core PQC to skip default GC when a custom store is registered.

---

## Pluggable purge adapter

When URLs are purged, PQC calls `AdapterFactory::get_adapter()`:

```php
add_filter( 'wpgraphql_pqc_purge_adapter', function ( $adapter ) {
	return new MyHostPurgeAdapter();
}, 10, 1 );
```

Implement `WPGraphQL\PQC\Purge\AdapterInterface`:

- `purge_url( string $url )` — Purge **one** path or full URL per your CDN/API.
- `purge_all()` — Optional global purge hook (rarely used).

Built-ins:

- **`VIPAdapter`** — When `wpvip_purge_edge_cache_for_url` (or legacy alias) exists.
- **`HttpPurgeAdapter`** — When `WPGRAPHQL_PQC_HTTP_PURGE_ORIGIN` is defined (non-empty string). Sends `PURGE` to `{origin}{path}` for each indexed URL (Varnish-style URL ban). Use for **local benchmarks** or any edge that accepts HTTP `PURGE`. See [benchmark/README.md](../benchmark/README.md).
- **`NullAdapter`** — No edge call; logs intent when `WP_DEBUG` is on.

Filters for `HttpPurgeAdapter`:

| Filter | Purpose |
|--------|---------|
| `wpgraphql_pqc_http_purge_method` | `( $method, $target, $url )` — default `PURGE`. |
| `wpgraphql_pqc_http_purge_request_args` | `( $args, $target, $url )` — passed to `wp_remote_request` (timeout, `sslverify`, headers, etc.). |

---

## Filters and actions (reference)

| Filter / action | Purpose |
|-----------------|--------|
| `wpgraphql_pqc_store` | Return `StoreInterface` to replace `DBStore`. |
| `wpgraphql_pqc_purge_adapter` | Return `AdapterInterface` for edge purging. |
| `wpgraphql_pqc_url_base` | Base path segment (default `graphql/persisted/`). Must stay consistent between Router and PostHandler. |
| `wpgraphql_pqc_cache_max_age` | Public warm GET `Cache-Control` max-age when Smart Cache does not set one (seconds). |
| `wpgraphql_pqc_require_nonce` | Set `false` to allow document persistence without cold GET nonce (e.g. trusted build pipelines). |
| `wpgraphql_pqc_delete_entries_on_purge` | `(bool, $key, $urls)` — Return `false` to skip `delete_by_url( $url )` for each purged path after edge purge (testing). |
| `wpgraphql_pqc_ttl_days` | Age for default MySQL GC of `url_keys` rows (days). |
| `wpgraphql_pqc_garbage_collection` | **Action** — Daily cron; default subscriber deletes old MySQL rows. |
| `wpgraphql_pqc_http_purge_method` | `(string, $target, $url)` — HTTP method for `HttpPurgeAdapter`. |
| `wpgraphql_pqc_http_purge_request_args` | `(array, $target, $url)` — `wp_remote_request` args for `HttpPurgeAdapter`. |

WordPress core / Smart Cache:

- `graphql_purge` — Fired by Smart Cache with `$cache_key`, `$event`, `$hostname`; PQC subscribes in `PurgeHandler`.

---

## Operational notes

- **Rewrite rules** — After changing `wpgraphql_pqc_url_base`, flush permalinks (or call `flush_rewrite_rules()` on deploy).
- **Query Analyzer** — Must be on for cache keys; without keys, PQC will not index operations.
- **Logging** — `WPGraphQL\PQC\Utils\Logger` writes purge lines to `error_log` when `WP_DEBUG` is true; PHP may log to `wp-content/debug.log` or the container’s PHP log depending on `WP_DEBUG_LOG` and hosting.
- **WP-CLI** — `wp graphql-pqc register` runs the same persistence path as an HTTP POST with a valid nonce (generates a nonce, calls `graphql()` with extensions, defines `WPGRAPHQL_PQC_INTERNAL_CALL`). See [TESTING.md](../TESTING.md).

---

## See also

- [SPEC.md](./SPEC.md) — Cold/warm GET, POST registration, hashes, HTTP semantics
- [../README.md](../README.md) — Installation and feature overview
