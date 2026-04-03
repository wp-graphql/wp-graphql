# WPGraphQL Persisted Query Cache

## Experimental / beta

This plugin is **early and experimental** (v0.1.0-beta.1). Do not rely on it for production sites yet. Breaking changes to URLs, database tables, filters, and client flows are likely before 1.0.0.

**Requires**: WPGraphQL 2.0.0+, WPGraphQL Smart Cache

WPGraphQL Persisted Query Cache enables persisted GraphQL queries via **permalink-based URLs** instead of long query strings, allowing **surgical cache invalidation** on hosts that don’t support tag-based purging (WordPress VIP and similar). It extends WPGraphQL Smart Cache’s purge pipeline.

**Why it exists**

- Smart Cache’s ideal path is tag-based edge purge; many hosts only purge by URL path.
- Long `GET /graphql?query=…` URLs are often hard to purge independently; PQC uses **one path per operation (+ variables)**.
- Server-side index maps **Smart Cache analyzer keys** → persisted paths so `graphql_purge` can call URL purge adapters.
- **Not** a drop-in for Apollo APQ; clients need a PQC-aware flow (see [docs/SPEC.md](./docs/SPEC.md)).

**Working in code today:** cold/warm GET, POST registration + nonce, `DBStore`, purge adapters, WP-CLI register. **Still thin:** production VIP validation, alternative stores, automated test coverage (growing).

## Documentation

| Doc | Contents |
|-----|----------|
| **[docs/STATUS.md](./docs/STATUS.md)** | Experimental scope: what exists, what is untested, limitations |
| **[docs/SPEC.md](./docs/SPEC.md)** | Protocol spec: URLs, hashes, cold vs warm GET, POST registration, invalidation — similar in scope to [Apollo Automatic Persisted Queries](https://www.apollographql.com/docs/apollo-server/performance/apq) |
| **[docs/INTEGRATIONS.md](./docs/INTEGRATIONS.md)** | Custom **store** (Redis / KV), **purge adapters**, and filter reference |
| [PRD.md](./PRD.md) | Product requirements and default MySQL schema |
| [TESTING.md](./TESTING.md) | Manual testing notes |
| [benchmark/README.md](./benchmark/README.md) | **Edge cache + load testing** (Varnish, `HttpPurgeAdapter`, k6) |
| [docs/planning/](./docs/planning/README.md) | Contributor planning notes (bench hypotheses, data plans) |

## Overview

WPGraphQL Smart Cache works best when the host can purge by **cache tags**. Many platforms only support **URL-based purging**. This plugin:

1. Exposes operations at clean paths like `/graphql/persisted/{queryHash}` (optional `/variables/{variablesHash}`).
2. Maintains a server-side index: **persisted URL → Smart Cache keys** (from the query analyzer).
3. Listens for `graphql_purge` and purges the resolved URLs via a **host adapter** (VIP, custom, or null for local dev).

## Quick start

### Installation

1. Install and activate **WPGraphQL** and **WPGraphQL Smart Cache**
2. Install and activate this plugin
3. Flush rewrite rules: **Settings → Permalinks → Save Changes**

### What you get automatically

- **Cold GET** to a persisted URL before registration → JSON with `PERSISTED_QUERY_NOT_FOUND` and `extensions.persistedQueryNonce`
- **POST** to `/graphql` with the full query + extensions → document stored; response includes `extensions.persistedQueryUrl`
- **Warm GET** → WordPress runs `graphql()` and returns normal JSON (this is what runs on **CDN miss** when the request reaches origin)
- On Smart Cache purge events → URLs looked up and passed to the purge adapter
- **WP-CLI:** `wp graphql-pqc register` — persist a query without manual nonce/POST (see [TESTING.md](./TESTING.md))

Example extension on successful index write:

```json
{
  "data": { },
  "extensions": {
    "persistedQueryUrl": "/graphql/persisted/abc123…/variables/def456…"
  }
}
```

## Request flow (summary)

See **[docs/SPEC.md](./docs/SPEC.md)** for full detail.

1. **Cold:** `GET /graphql/persisted/{queryHash}` → HTTP 200, GraphQL error + nonce (document not in index yet).
2. **Register:** `POST /graphql` with `query`, optional `variables`, and `extensions.persistedQueryNonce` / hash fields → index updated, `persistedQueryUrl` returned.
3. **Warm:** `GET` the same persisted URL → HTTP 200, GraphQL `data` (and cache headers for anonymous users).

**Nonce:** Reduces unsolicited document registration. Build tools can disable via `wpgraphql_pqc_require_nonce` (see [INTEGRATIONS.md](./docs/INTEGRATIONS.md)).

**Query analyzer:** Must produce cache keys (`graphql_general_settings['query_analyzer_enabled']`); otherwise no index rows are written.

## Authentication and caching

- **Logged-in users:** warm GET responses use `Cache-Control: no-store`.
- **Anonymous users:** warm GET uses cacheable headers (Smart Cache global max-age when available, or filter `wpgraphql_pqc_cache_max_age`).

## Host purge adapters

- **WordPress VIP:** Auto-detected when VIP purge API is available
- **Null adapter:** Default elsewhere — no-op (no edge call)
- **Custom:** `wpgraphql_pqc_purge_adapter` filter — see [INTEGRATIONS.md](./docs/INTEGRATIONS.md)

## Custom storage (Redis, etc.)

The default index is **MySQL** (`DBStore`). To reduce database load or match platform standards, provide a class implementing `StoreInterface` and register it with **`wpgraphql_pqc_store`**.

Implementation notes, Redis key layout ideas, and garbage-collection caveats are in **[docs/INTEGRATIONS.md](./docs/INTEGRATIONS.md)**.

## Configuration snippets

### Change URL base

```php
add_filter( 'wpgraphql_pqc_url_base', function () {
	return 'api/persisted/';
} );
```

Flush permalinks after changing this.

### Document creation and Smart Cache

Execution data is stored when the document already exists. **New** documents follow Smart Cache **Saved Queries → Allow/Deny Mode** (`grant_mode`): public vs authenticated-only, combined with nonce rules above.

### Other filters

`wpgraphql_pqc_cache_max_age`, `wpgraphql_pqc_require_nonce`, `wpgraphql_pqc_delete_entries_on_purge`, `wpgraphql_pqc_ttl_days` — see [INTEGRATIONS.md](./docs/INTEGRATIONS.md).

## Development

### Requirements

- PHP 7.4+
- WordPress 6.0+
- WPGraphQL 2.0.0+
- WPGraphQL Smart Cache

### Monorepo

```bash
npm run wp-env start
npm run -w @wpgraphql/wp-graphql-pqc test:codecept:wpunit
npm run -w @wpgraphql/wp-graphql-pqc wp-env:cli -- composer run check-cs
```

Comprehensive local scenario script: `test-pqc-scenarios.sh` (from `plugins/wp-graphql-pqc/`; requires wp-env and `localhost:8888`).

## Status

Beta / experimental. Database schema is managed with `dbDelta` on load (`Schema::ensure_schema()`); there is no migration from older internal `url_keys` tables (see [PRD.md](./PRD.md)). For tested vs untested areas, see [docs/STATUS.md](./docs/STATUS.md).

## Related

- [WPGraphQL Smart Cache](../wp-graphql-smart-cache/)
- [WPGraphQL Core](../wp-graphql/)
- [Apollo APQ](https://www.apollographql.com/docs/apollo-server/performance/apq) (conceptual reference; PQC uses WordPress-specific URLs and invalidation)

## License

GPL-3.0-or-later
