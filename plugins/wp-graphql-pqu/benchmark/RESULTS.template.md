# PQU bench run — copy per scenario

Use this as a checklist when comparing **origin vs edge** and **churn**. Keep a copy (or JSON manifest + pasted k6 summary) per run ID.

## Run metadata

| Field | Value |
|-------|--------|
| `run_id` | |
| `git_commit` | |
| `date` | |
| Scenario | Baseline A / Edge + TTL / Edge + churn / … |
| `k6_base_url` | e.g. `http://localhost:8888` or `:8081` |
| `DURATION` / `VUS` | |
| `urls.txt` line count (`N_variable_instances`) | |
| Smart Cache global max-age (`s_maxage_s`) | |
| `WPGRAPHQL_PQU_HTTP_PURGE_ORIGIN` (if any) | |
| Purge adapter | HttpPurgeAdapter / NullAdapter / … |

## k6 summary (paste end-of-run block)

```
(paste CUSTOM + HTTP + checks / thresholds from k6)
```

Notes:

- **Origin:** expect `pqu_x_cache_unknown` ≈ all iterations; low iteration/s vs edge.
- **Edge (warm):** expect `pqu_x_cache_hit_rate` very high; `pqu_x_cache_misses` bumps with churn/purge.

## Optional: DB snapshot (after run)

For default MySQL store, optional row counts (WP-CLI / `wp db query`):

- `wp_wpgraphql_pqu_documents`
- `wp_wpgraphql_pqu_executions`
- `wp_wpgraphql_pqu_urls` / `wpgraphql_pqu_cache_keys` / `wpgraphql_pqu_key_urls` (normalized key map)

## One-line conclusion

(e.g. “Edge: ~99.9% hit rate, 3 MISS aligned with 3 churn events; origin: ~X iters/s, ~Y ms p95.”)
