# PQC edge-cache benchmarking

This folder helps you reproduce **URL-keyed edge caching** (Varnish) in front of WordPress, plus **HTTP PURGE** invalidation for persisted query paths—matching how PQC is meant to run on hosts that cache GETs by URL.

## Prerequisites

- [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (or any WordPress at `http://localhost:8888`) with **WPGraphQL**, **WPGraphQL Smart Cache**, and **wp-graphql-pqc** active.
- Docker (for Varnish).
- Optional: [k6](https://k6.io/) for load tests.

## 1. Start Varnish

From `plugins/wp-graphql-pqc/benchmark/`:

```bash
docker compose up -d
```

- **Public URL (through cache):** `http://localhost:8081`
- **Origin (bypass cache):** `http://localhost:8888`

Varnish forwards to `host.docker.internal:8888` with `Host: localhost:8888`. If your WordPress listens on another host/port, edit [docker/varnish/default.vcl](./docker/varnish/default.vcl).

## 2. Enable HTTP purge from WordPress

When content changes, PQC must **PURGE** the edge, not only the WordPress origin. Define the edge base URL (reachable **from the PHP process**):

```php
// wp-config.php or a small must-use plugin.
define( 'WPGRAPHQL_PQC_HTTP_PURGE_ORIGIN', 'http://host.docker.internal:8081' );
```

- On **macOS/Windows** Docker Desktop, `host.docker.internal` from inside the **wp-env** PHP container reaches services published on the host (e.g. Varnish `:8081`).
- If PURGE fails, check `debug.log` for `[WPGraphQL PQC] HttpPurgeAdapter:` lines (with `WP_DEBUG`).

You can override request options with the filter `wpgraphql_pqc_http_purge_request_args` (for example `sslverify` or headers).

## 3. Smart Cache TTL

Set a **high** global max-age in WPGraphQL Smart Cache so the edge keeps responses until a purge (see [network cache](https://github.com/wp-graphql/wp-graphql/blob/develop/plugins/wp-graphql-smart-cache/docs/network-cache.md) docs in the monorepo).

## 4. Smoke test (curl)

After registering a persisted query (see [../TESTING.md](../TESTING.md)):

```bash
PERSISTED_PATH="/graphql/persisted/YOUR_QUERY_HASH"

curl -sI "http://localhost:8081${PERSISTED_PATH}" | grep -i x-cache
curl -sI "http://localhost:8081${PERSISTED_PATH}" | grep -i x-cache
```

Expect first **MISS**, second **HIT**.

## 5. k6 load test

1. Build a URL list (one persisted path per line) in `urls.txt`.
2. Run:

```bash
k6 run k6/pqc-persisted-get.js -e BASE_URL=http://localhost:8081 -e URLS_FILE=urls.txt
```

See [k6/run-manifest.example.json](./k6/run-manifest.example.json) for **scale knobs** to record with each run.

## Scale knobs

When publishing results, record at least:

| Knob | Notes |
|------|--------|
| `N_templates` | Distinct GraphQL documents |
| `N_variable_instances` | Registered persisted URLs (distinct variables hashes) |
| `N_posts` / `N_pages` | Seeded content |
| `s_maxage_s` | Smart Cache global max-age |
| `steady_vus` / `duration_s` | k6 settings |
| `churn_edits_per_min` | Content change rate (if any) |

---

For protocol details and adapters, see [../docs/INTEGRATIONS.md](../docs/INTEGRATIONS.md).
