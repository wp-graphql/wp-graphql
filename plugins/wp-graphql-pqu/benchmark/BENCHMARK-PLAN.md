# PQU edge-cache benchmark plan (tracked copy)

The living Cursor plan may live under `.cursor/plans/pqc_edge-cache_benchmarking_6c094a0e.plan.md` (often gitignored). This file is the **repo-tracked** summary so the team shares the same “what’s done / what’s next.”

**Hypothesis:** URL-keyed edge caching of persisted GETs + Smart Cache–driven purges reduces origin work; bench is **directional**, not production SLA proof.

## Done (implementation)

### PQU plugin — normalized URL ↔ cache key map (MySQL)

The denormalized `wpgraphql_pqu_url_keys` table is **replaced** by a normalized layout (see [INTEGRATIONS.md](../docs/INTEGRATIONS.md), [Schema.php](../src/Database/Schema.php)):

- **`wpgraphql_pqu_urls`** — one row per persisted path; `last_seen_at` for GC.
- **`wpgraphql_pqu_cache_keys`** — deduplicated analyzer key strings.
- **`wpgraphql_pqu_key_urls`** — junction (cache key ↔ URL).

**Executions** (`wpgraphql_pqu_executions`) remain for warm GET resolution independent of the key map. Legacy installs migrate on init/activate; executions are backfilled before dropping old `url_keys` data.

**Behavior highlights:**

- **`store( …, $record_cache_tags )`** — can skip key-map writes while still storing document/execution; **`wpgraphql_pqu_should_record`** defaults to **`! is_user_logged_in()`** so the map tracks publicly cacheable edge URLs.
- **`get_urls_for_query_hash()`** — list URLs for a query hash (wildcard / prefix purge helpers).
- **Purge:** `PurgeHandler` **dedupes** URLs from `get_urls_for_key`, purges the edge per path, then **`delete_by_url`** each path so **all** key associations for that URL drop after invalidation (index matches edge reality until the next `store()`).

### Benchmark stack & measurement

- Varnish + README (`:8081` edge, `:8888` origin, `host.docker.internal` for purges from PHP).
- `HttpPurgeAdapter` + `WPGRAPHQL_PQU_HTTP_PURGE_ORIGIN`.
- `wp graphql-pqu register` + **`wp graphql-pqu bulk-register`** → `urls.txt` + optional `run-manifest.json` (see [README.md](./README.md)).
- k6 [k6/pqu-persisted-get.js](./k6/pqu-persisted-get.js): HIT/MISS metrics, optional `REQUIRE_X_CACHE` / `MIN_HIT_RATE`.
- Wrappers: [scripts/run-k6-edge.sh](./scripts/run-k6-edge.sh), [scripts/run-k6-origin.sh](./scripts/run-k6-origin.sh); churn + load: [scripts/k6-with-churn.sh](./scripts/k6-with-churn.sh), [scripts/k6-with-realistic-churn.sh](./scripts/k6-with-realistic-churn.sh); curl sample: [scripts/pqu-churn-sample.sh](./scripts/pqu-churn-sample.sh).
- **Realistic headless-day path:** [REALISTIC-BENCH-PLAN.md](./REALISTIC-BENCH-PLAN.md) + [scripts/seed-headless-site.sh](./scripts/seed-headless-site.sh) + [scripts/build-persisted-urls.sh](./scripts/build-persisted-urls.sh) + `k6/urls-headless-day.txt` (gitignored output).

**Empirical checks so far:** warm edge → very high `pqu_x_cache_hit_rate`; churn + purge → small `pqu_x_cache_misses` aligned with edit count; origin baseline → mostly `pqu_x_cache_unknown`, far lower iteration/s.

## Next (prove the hypothesis on paper)

1. **Paired runs (same knobs)** — Run **`run-k6-origin.sh`** and **`run-k6-edge.sh`** with identical `DURATION`, `VUS`, and `urls.txt`. Capture outputs using [RESULTS.template.md](./RESULTS.template.md) + [k6/run-manifest.example.json](./k6/run-manifest.example.json). Archive completed result files locally (gitignored under `results/`).
2. **Archive** — One folder or doc per scenario (gitignored local JSON ok); include git SHA and Smart Cache TTL.
3. **Scale (optional)** — Many posts + bulk-register → large `urls.txt`; re-run edge + short churn; optional row counts on **`wpgraphql_pqu_*`** tables during/after.
4. **Control (optional)** — Edge + churn with HTTP purge / adapter off → stale edge narrative.
5. **Redis `StoreInterface` (optional)** — Repeat a subset after a custom store exists ([INTEGRATIONS.md](../docs/INTEGRATIONS.md)).

### Scenario matrix (reference)

| Scenario | k6 `BASE_URL` | Purpose |
|----------|---------------|---------|
| Baseline A | `http://localhost:8888` | Origin only — latency / sustained iteration rate floor. |
| Edge + high TTL | `http://localhost:8081` | Hit ratio, edge latency. |
| Edge + churn | `:8081` + `k6-with-churn.sh` | MISS blips vs purge events, recovery. |
| Edge + realistic churn | `:8081` + `k6-with-realistic-churn.sh` + `urls-headless-day.txt` | Shared lists/archives/menu see MISS/HIT; lower steady hit rate than tiny `urls.txt`. |
| Edge + churn, no purge (optional) | `:8081` + NullAdapter | Stale edge (motivation only). |

## Operational docs

- [README.md](./README.md) — stack, k6, churn.
- [REALISTIC-BENCH-PLAN.md](./REALISTIC-BENCH-PLAN.md) — large seed + multi-template persisted URLs + realistic churn.
- [RESULTS.template.md](./RESULTS.template.md) — paste k6 summaries per run.
- [../docs/INTEGRATIONS.md](../docs/INTEGRATIONS.md) — store, filters, GC, Redis notes.
- [../docs/SPEC.md](../docs/SPEC.md) — cold/warm GET, invalidation flow.
- Smart Cache network cache: [plugins/wp-graphql-smart-cache/docs/network-cache.md](../../wp-graphql-smart-cache/docs/network-cache.md).
