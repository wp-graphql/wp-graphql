# Realistic PQC benchmark data (plan summary)

**Problem:** A tiny `urls.txt` and churn that only touches one post makes the edge look unrealistically hot and hides invalidation behavior for shared templates (lists, menus, archives).

**Goal:** Seed many posts, terms, authors, a “Primary Nav” menu; add Faust-style `.graphql` templates under [benchmark/graphql/](../../benchmark/graphql/); generate variables (jsonl) for URIs and global IDs; bulk-register persisted URLs; merge into a day-sized URL list; run k6 with churn that updates posts, terms, and menu items.

**What shipped in-repo:**

- [seed-headless-site.sh](../../benchmark/scripts/seed-headless-site.sh), PHP emit helpers, [build-persisted-urls.sh](../../benchmark/scripts/build-persisted-urls.sh), [k6-with-realistic-churn.sh](../../benchmark/scripts/k6-with-realistic-churn.sh).
- GraphQL templates: `front-page-nav`, blog, category/tag/author archives, singular-by-uri (see [benchmark/graphql/](../../benchmark/graphql/)).
- Documentation: [benchmark/BENCHMARK-PLAN.md](../../benchmark/BENCHMARK-PLAN.md), [benchmark/README.md](../../benchmark/README.md).

**Note:** Generated URL lists and large manifests should stay out of git (see benchmark `.gitignore` policy).
