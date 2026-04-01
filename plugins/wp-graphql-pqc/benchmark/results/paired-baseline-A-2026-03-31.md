# Paired Baseline A — 2026-03-31

Same knobs for both runs: **`DURATION=2m`**, **`VUS=10`**, **`benchmark/k6/urls.txt`** (3 persisted GET paths). Host: local wp-env origin + Docker Varnish edge (see [../README.md](../README.md)).

| Field | Origin | Edge |
|--------|--------|------|
| `k6_base_url` | `http://localhost:8888` | `http://localhost:8081` |
| Git | `f1f10ab23` | `f1f10ab23` |

Manifests: [manifest-origin-2026-03-31.json](./manifest-origin-2026-03-31.json), [manifest-edge-2026-03-31.json](./manifest-edge-2026-03-31.json).

## k6 summary (CUSTOM + HTTP)

**Origin**

- `iterations`: 8428 (~**70.18/s**)
- `http_req_duration` p(95): **161.72ms** (avg ~142ms)
- `pqc_x_cache_unknown`: **8428** (no `X-Cache` — expected)
- `http_req_failed`: **0%**

**Edge (Varnish)**

- `iterations`: 2712123 (~**22601/s**)
- `http_req_duration` p(95): **525µs** (avg ~378µs)
- `pqc_x_cache_hit_rate`: **100%** (`pqc_x_cache_hits` 2712123; fully warm on 3 URLs)
- `http_req_failed`: **0%**

## One-line conclusion

For this tiny URL set, a warm edge returns **~300×** more iterations per second and **~300×** lower p95 latency than hitting WordPress directly; origin traffic shows **all** requests as cache-unknown, as expected.

## Reproduce

```bash
DURATION=2m VUS=10 ./plugins/wp-graphql-pqc/benchmark/scripts/run-k6-origin.sh
DURATION=2m VUS=10 ./plugins/wp-graphql-pqc/benchmark/scripts/run-k6-edge.sh
```

(From repo root; requires wp-env on `:8888` and Varnish stack on `:8081`.)
