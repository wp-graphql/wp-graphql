# PQC edge-cache benchmark plan (tracked copy)

The living Cursor plan may live under `.cursor/plans/pqc_edge-cache_benchmarking_6c094a0e.plan.md` (often gitignored). This file is the **repo-tracked** summary so the team shares the same “what’s done / what’s next.”

**Hypothesis:** URL-keyed edge caching of persisted GETs + Smart Cache–driven purges reduces origin work; bench is **directional**, not production SLA proof.

## Done (implementation)

- Varnish stack + README (`:8081` edge, `:8888` origin, `host.docker.internal` for purges from PHP).
- `HttpPurgeAdapter` + `WPGRAPHQL_PQC_HTTP_PURGE_ORIGIN`; purge clears all `url_keys` for purged paths; executions keep warm GET working.
- `wp graphql-pqc register` + **`wp graphql-pqc bulk-register`** → `urls.txt` + optional `run-manifest.json` (see [README.md](./README.md)).
- k6 [k6/pqc-persisted-get.js](./k6/pqc-persisted-get.js) (status 200 + `X-Cache` present); [scripts/pqc-churn-sample.sh](./scripts/pqc-churn-sample.sh).

## Next (measurement phase)

1. **HIT/MISS in k6** — Parse Varnish `X-Cache` into metrics (hit ratio), not only “header present.”
2. **Run scenarios** (3–5+ min after warm-up); record knobs in `run-manifest` / [run-manifest.example.json](./k6/run-manifest.example.json).

| Scenario | k6 `BASE_URL` | Purpose |
|----------|---------------|---------|
| Baseline A | `http://localhost:8888` | Origin only, no edge — latency/load floor. |
| Edge + high TTL | `http://localhost:8081` | High Smart Cache `s-maxage`; measure hit ratio. |
| Edge + churn | `:8081` + WP-CLI / churn script | Purge adapter on; MISS spike then HIT recovery. |
| Edge + churn, no purge (optional) | `:8081` + NullAdapter / no HTTP purge | Stale edge (narrative only). |

3. **Scale** — Seed many posts (`wp post generate`, etc.) + bulk-register for a large `urls.txt`.
4. **Optional** — DB row counts / slow log during churn; later **Redis `StoreInterface`** and repeat (see [../docs/INTEGRATIONS.md](../docs/INTEGRATIONS.md)).

## Operational docs

- [README.md](./README.md) — how to run the stack and k6.
- [../docs/INTEGRATIONS.md](../docs/INTEGRATIONS.md) — adapters and store contract.
- Smart Cache network cache: [plugins/wp-graphql-smart-cache/docs/network-cache.md](../../wp-graphql-smart-cache/docs/network-cache.md).
