# Planning notes (wp-graphql-pqc)

Internal design and benchmark plans live here so contributors and agents do not need `.cursor/plans/` in the monorepo root.

| Document | Summary |
|----------|---------|
| [edge-benchmarking.md](./edge-benchmarking.md) | Local Varnish + k6 stack, purge adapter, hypothesis (edge HIT vs origin load) |
| [realistic-bench-data.md](./realistic-bench-data.md) | Seeded site, Faust-shaped GraphQL files, merged URL lists, realistic churn |

**Implementation status:** Most items in those plans are reflected under [benchmark/README.md](../../benchmark/README.md). Open work (e.g. Redis `StoreInterface`) stays tracked in [STATUS.md](../STATUS.md) and the bench docs.
