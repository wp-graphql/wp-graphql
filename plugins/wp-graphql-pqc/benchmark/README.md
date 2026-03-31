# PQC edge-cache benchmarking

This folder helps you reproduce **URL-keyed edge caching** (Varnish) in front of WordPress, plus **HTTP PURGE** invalidation for persisted query paths—matching how PQC is meant to run on hosts that cache GETs by URL.

For **what is implemented vs next measurement steps** (scenario matrix, HIT/MISS work), see [BENCHMARK-PLAN.md](./BENCHMARK-PLAN.md) (git-tracked). A fuller working copy may also exist under `.cursor/plans/` for Cursor.

## Prerequisites

- [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (or any WordPress at `http://localhost:8888`) with **WPGraphQL**, **WPGraphQL Smart Cache**, and **wp-graphql-pqc** active.
- Docker (for Varnish).
- **k6** for §6 load tests — install the binary ([installation](https://grafana.com/docs/k6/latest/set-up/install-k6/)), e.g. macOS: `brew install k6`. If you prefer not to install k6, run the same script via Docker (see §6).

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

- **Edge (through cache):** `http://localhost:8081` — what browsers and k6 hit; Varnish sits here.
- **Origin (WordPress, bypass edge):** `http://localhost:8888` — typical wp-env dev site; use this to compare “no edge” baseline.

**Two “hosts” to keep straight:** Clients and k6 talk to the **edge** port. PHP inside wp-env purges the edge using **`WPGRAPHQL_PQC_HTTP_PURGE_ORIGIN`** (see §2), which must point at Varnish from **inside** the WordPress container (`host.docker.internal:8081`), not at `:8888`.

The bundled [docker-compose.yml](./docker-compose.yml) sets `extra_hosts: host.docker.internal:host-gateway` so **Linux** resolves `host.docker.internal` the same way **Docker Desktop** does on macOS/Windows. If your Docker setup does not support `host-gateway`, set `extra_hosts` to your machine’s LAN IP or use a compose network that includes both Varnish and WordPress.

Varnish forwards to `host.docker.internal:8888` with `Host: localhost:8888`. If your WordPress uses another host/port, edit [docker/varnish/default.vcl](./docker/varnish/default.vcl).

Stop with `docker compose down` (from the same directory you used for `up`, or pass the same `-f` path).

## 2. Set `WPGRAPHQL_PQC_HTTP_PURGE_ORIGIN` (WordPress → edge)

PHP runs **inside** the wp-env container. Purges must reach the **Varnish** process (host port **8081** in this stack), not the WordPress port (**8888**). From inside the PHP container, `localhost:8081` is usually wrong (that loops back to the container itself). Use a hostname that reaches the host machine:

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

## 4. Register a persisted query, then smoke-test the edge

### Register (WP-CLI)

Avoid manual cold-GET + POST: use **`wp graphql-pqc register`** (ships with this plugin). From the monorepo root with wp-env running:

```bash
npm run wp-env -- run cli -- wp graphql-pqc register \
  --query='query { posts(first: 3) { nodes { id title } } }' \
  --edge-base=http://localhost:8081
```

Or pass a file:

```bash
npm run wp-env -- run cli -- wp graphql-pqc register path/to/query.graphql --variables-file=vars.json
```

On success you get the **`persistedQueryUrl`** path and a suggested `curl` against `:8081`. With variables:

```bash
npm run wp-env -- run cli -- wp graphql-pqc register \
  --query='query GetPost($id: ID!) { post(id: $id) { id title } }' \
  --variables='{"id":"cG9zdDox"}' \
  --edge-base=http://localhost:8081
```

See `wp graphql-pqc register --help` for options (`--user`, STDIN `-`, etc.). Manual flow remains in [../TESTING.md](../TESTING.md).

### Smoke test (curl)

```bash
PERSISTED_PATH="/graphql/persisted/YOUR_QUERY_HASH"

curl -sI "http://localhost:8081${PERSISTED_PATH}" | grep -i x-cache
curl -sI "http://localhost:8081${PERSISTED_PATH}" | grep -i x-cache
```

Expect first **MISS**, second **HIT**.

## 5. Bulk registration (many `variablesHash` URLs)

Use **`wp graphql-pqc bulk-register`** (hyphen; **`bulk_register`** with an underscore is an alias) to register the same document with many variable payloads (newspaper-style long tail). Paths are written for k6 (one per line, no host). **`--limit=200` only creates as many URLs as you have matching posts** (e.g. three posts → three paths).

**Inside wp-env**, use paths under `/var/www/html/wp-content/plugins/wp-graphql-pqc/benchmark/k6/` (or your mounted plugin path).

```bash
# Register one persisted URL per published post (default limit 100), write paths + manifest.
npm run wp-env -- run cli -- wp graphql-pqc bulk-register \
  /var/www/html/wp-content/plugins/wp-graphql-pqc/benchmark/k6/single-post.graphql \
  --limit=200 \
  --urls-out=/var/www/html/wp-content/plugins/wp-graphql-pqc/benchmark/k6/urls.txt \
  --manifest-out=/var/www/html/wp-content/plugins/wp-graphql-pqc/benchmark/k6/run-manifest.json \
  --manifest-template=/var/www/html/wp-content/plugins/wp-graphql-pqc/benchmark/k6/run-manifest.example.json \
  --edge-base=http://localhost:8081
```

- **`--variables-jsonl=<file>`** — alternative to post scan: one JSON object per line (GraphQL variables). Lines starting with `#` are skipped.
- **`--post-type`**, **`--offset`**, **`--relay-type`** (default `post`, use `page` for pages), **`--id-variable`** (default `id`).
- **`--dry-run`** — count variable sets without calling `graphql()`.

Then run k6 from the host against `urls.txt` (§6).

## 6. k6 load test

1. Ensure `benchmark/k6/urls.txt` exists (from §5 or hand-built).
2. From the directory that contains `urls.txt` (or pass an absolute path via `URLS_FILE`).

**Local k6** (after `brew install k6` or another [install](https://grafana.com/docs/k6/latest/set-up/install-k6/)):

```bash
cd plugins/wp-graphql-pqc/benchmark/k6
k6 run pqc-persisted-get.js -e BASE_URL=http://localhost:8081 -e URLS_FILE=urls.txt
```

**Edge-only checks:** to fail the run if `X-Cache` is missing (Varnish not in path), add `-e REQUIRE_X_CACHE=1`.

**Minimum hit rate** (after warm-up, edge only): `-e MIN_HIT_RATE=0.85` adds a threshold on `pqc_x_cache_hit_rate` (0–1). Omit on cold cache or origin runs.

**Origin baseline (no edge):** same script, `BASE_URL=http://localhost:8888`. Expect **`pqc_x_cache_unknown`** to dominate (no `X-Cache` from WordPress). Do **not** set `REQUIRE_X_CACHE` or `MIN_HIT_RATE`.

### Reading k6 output (custom metrics)

| Metric | Meaning |
|--------|---------|
| `pqc_x_cache_hits` / `pqc_x_cache_misses` | Count of responses whose `X-Cache` starts with `HIT` / `MISS` (bundled VCL: `HIT: N` or `MISS`). |
| `pqc_x_cache_unknown` | No header or unrecognized value (typical on **origin-only** runs). |
| `pqc_x_cache_hit_rate` | `Rate`: fraction of iterations classified as HIT; summary shows `rate=…` (use with `MIN_HIT_RATE`). |
| `pqc_get_duration` | Client-side request duration (trend). |

**Manual sanity check:** one `curl -sI http://localhost:8081/your-path \| grep -i x-cache` should show `HIT:` or `MISS` before you trust hit-rate numbers.

**Docker** (no k6 binary on the host): mount this folder and reach Varnish on the host. On macOS/Windows Docker Desktop, `host.docker.internal` resolves to the host; on Linux you may need `--add-host=host.docker.internal:host-gateway` (Docker 20.10+).

```bash
cd plugins/wp-graphql-pqc/benchmark/k6
docker run --rm -i \
  --add-host=host.docker.internal:host-gateway \
  -v "$PWD:/scripts" -w /scripts \
  grafana/k6 run \
  -e BASE_URL=http://host.docker.internal:8081 \
  -e URLS_FILE=urls.txt \
  pqc-persisted-get.js
```

See [k6/run-manifest.example.json](./k6/run-manifest.example.json) for **scale knobs**; merge your copy into output with `--manifest-template` during bulk-register, or edit the generated `run-manifest.json` after the fact.

## 7. Churn sample (purge + edge HIT/MISS)

[scripts/pqc-churn-sample.sh](./scripts/pqc-churn-sample.sh) updates a post on a timer and `curl -sI`s a persisted URL on the edge so you can watch **`X-Cache`** flip to MISS after purge and warm again.

```bash
chmod +x plugins/wp-graphql-pqc/benchmark/scripts/pqc-churn-sample.sh
export WP_BIN='npm run wp-env -- run cli -- wp'
./plugins/wp-graphql-pqc/benchmark/scripts/pqc-churn-sample.sh \
  http://localhost:8081 \
  '/graphql/persisted/YOUR_HASH/variables/VARS_HASH' \
  10 \
  20
```

For heavier scenarios, run k6 (§6) in one terminal and a WP-CLI loop (post updates / meta) in another; record **`churn_edits_per_min`** in the manifest.

## 8. Later: Redis `StoreInterface`

There is no bundled Redis store yet. See [../docs/INTEGRATIONS.md](../docs/INTEGRATIONS.md) for the contract and key layout; repeat edge + k6 scenarios after implementing a custom store.

## Scale knobs (manifest)

When publishing results, record at least:

| Knob | Notes |
|------|--------|
| `N_templates` | Distinct GraphQL documents |
| `N_variable_instances` | Registered persisted URLs (distinct variables hashes) |
| `N_posts` / `N_pages` | Seeded content |
| `s_maxage_s` | Smart Cache global max-age |
| `steady_vus` / `duration_s` | k6 settings |
| `k6_base_url` / `k6_require_x_cache` / `k6_min_hit_rate` | Edge vs origin URL; optional k6 env mirrors (see §6) |
| `churn_edits_per_min` | Content change rate (if any) |

---

For protocol details and adapters, see [../docs/INTEGRATIONS.md](../docs/INTEGRATIONS.md).
