# PQC edge-cache benchmarking

This folder helps you reproduce **URL-keyed edge caching** (Varnish) in front of WordPress, plus **HTTP PURGE** invalidation for persisted query paths—matching how PQC is meant to run on hosts that cache GETs by URL.

## Prerequisites

- [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (or any WordPress at `http://localhost:8888`) with **WPGraphQL**, **WPGraphQL Smart Cache**, and **wp-graphql-pqc** active.
- Docker (for Varnish).
- Optional: [k6](https://k6.io/) for load tests.

## 1. Start Varnish (Docker Compose)

Varnish is **separate** from wp-env: a small Compose file in this folder talks to WordPress on the host.

**Option A — from this directory (simplest):**

```bash
cd plugins/wp-graphql-pqc/benchmark
docker compose up -d
```

**Option B — from the monorepo root (fixed path):**

```bash
docker compose -f plugins/wp-graphql-pqc/benchmark/docker-compose.yml up -d
```

- **Public URL (through cache):** `http://localhost:8081`
- **Origin (bypass cache):** `http://localhost:8888` (typical wp-env dev site)

The bundled [docker-compose.yml](./docker-compose.yml) sets `extra_hosts: host.docker.internal:host-gateway` so Linux can resolve `host.docker.internal` like Docker Desktop on macOS/Windows.

Varnish forwards to `host.docker.internal:8888` with `Host: localhost:8888`. If your WordPress uses another host/port, edit [docker/varnish/default.vcl](./docker/varnish/default.vcl).

Stop with `docker compose down` (from the same directory you used for `up`, or pass the same `-f` path).

## 2. Set `WPGRAPHQL_PQC_HTTP_PURGE_ORIGIN` (WordPress → edge)

PHP runs **inside** the wp-env container. Purges must hit the **Varnish** listener on the host (`:8081`), not `:8888`. Use a base URL that resolves from **inside** that container:

`http://host.docker.internal:8081`

Pick **one** of the following (best first for this monorepo).

### Recommended: `.wp-env.override.json` (gitignored)

At the **repository root** (same level as `.wp-env.json`), create `.wp-env.override.json` (see [Custom Environment Configuration](https://github.com/wp-graphql/wp-graphql/blob/develop/docs/DEVELOPMENT.md#custom-environment-configuration)):

```json
{
  "env": {
    "development": {
      "config": {
        "WPGRAPHQL_PQC_HTTP_PURGE_ORIGIN": "http://host.docker.internal:8081"
      }
    }
  }
}
```

wp-env merges this file and, when the merged config changes, re-runs WordPress setup steps that apply `config` entries via `wp config set`. After adding or editing the override, run:

```bash
npm run wp-env start
```

If the constant still does not appear, run `npm run wp-env clean development` once (resets the dev DB) or use the WP-CLI one-liner below.

### Quick one-off: WP-CLI

No new files; writes straight into `wp-config.php` inside the container:

```bash
npm run wp-env run cli -- wp config set WPGRAPHQL_PQC_HTTP_PURGE_ORIGIN http://host.docker.internal:8081 --type=constant
```

Use this for a fast smoke test. The line survives until the environment is recreated (`wp-env destroy` / clean).

### Alternative: must-use plugin

If you prefer not to touch wp-env config, add a tiny MU-plugin under `wp-content/mu-plugins/` that `define()`s the same constant (only on local). Good when WordPress is **not** wp-env.

### Verify

With `WP_DEBUG` enabled, failed purges log `[WPGraphQL PQC] HttpPurgeAdapter:` in `debug.log`. You can override `wp_remote_request` with the filter `wpgraphql_pqc_http_purge_request_args` (for example `sslverify` or headers).

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
