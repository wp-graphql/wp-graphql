# PQU edge-cache benchmarking (plan summary)

**Goal:** Reproduce locally: edge cache in front of WordPress, persisted GET URLs, Smart Cache driven purges, and k6 load so we can compare origin load and latency with high edge TTLs vs content churn.

**Hypothesis:** URL-keyed edge caching of persisted GETs plus key-driven purges reduces origin GraphQL work; index read/write cost stays small vs saved executions.

**What shipped in-repo (see [benchmark/README.md](../../benchmark/README.md)):**

- Docker Compose Varnish → wp-env; [default.vcl](../../benchmark/docker/varnish/default.vcl).
- `HttpPurgeAdapter` + env for purge target; `delete_by_url` after edge eviction; executions kept for warm GET.
- k6 script with cache HIT/MISS metrics; shell helpers (`run-k6-edge.sh`, `run-k6-origin.sh`, churn scripts).
- Normalized MySQL key map; optional future: Redis-backed `StoreInterface` for index comparison.

**Operator workflow:** Run scenarios, record `run-manifest.json` + notes using [RESULTS.template.md](../../benchmark/RESULTS.template.md).

**Open:** Production WordPress.com/VIP numbers; Redis store adapter; CI integration for k6 (optional).
