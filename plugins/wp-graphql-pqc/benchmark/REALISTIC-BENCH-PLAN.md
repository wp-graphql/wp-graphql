# Realistic headless-style PQC benchmark (tracked plan)

This document mirrors the Cursor plan for **seed data + Faust-shaped queries + merged persisted URLs + multi-action churn** so edge hit rates reflect shared templates (lists, menu, category/tag archives) and taxonomy edits—not a trivial three-URL hot set.

## Why the old bench skewed “too hot”

- A tiny `urls.txt` keeps Varnish fully warm under uniform random selection.
- Churn that only updates **one** post rarely invalidates **blog index**, **category/tag archives**, or **menu** queries.

## Reference templates

Shapes are taken from [wpgraphql.com Faust templates](../../../websites/wpgraphql.com/src/wp-templates/) (reference only; queries live under `benchmark/graphql/`).

- **Front page + nav** — `front-page.js` + `NavMenu` (`menu(id: "Primary Nav", idType: NAME)`). Seed creates that menu.
- **Blog index** — `archive-post.js` (`posts(first: …)` + nav).
- **Category archive** — `category.js` (`category(id:)` + posts + nav).
- **Tag archive** — same pattern as category using root field **`tag(id:)`** for `post_tag` terms (not used on wpgraphql.com; included for realism).
- **Author archive** — `author.js` (`user(id:)` + posts + nav).
- **Single post by URI** — `singular.js` (`post(id:, idType: URI)` + nav).

Custom CPT **archive.js** on wpgraphql.com is out of scope for this generic blog seed.

## Operator flow

1. **[scripts/seed-headless-site.sh](scripts/seed-headless-site.sh)** — posts, categories, tags, authors, assignments, **Primary Nav** menu.
2. **Emit variables JSONL** — `wp eval-file` [scripts/php/emit-headless-variables.php](scripts/php/emit-headless-variables.php) → `k6/generated/*.jsonl`.
3. **[scripts/build-persisted-urls.sh](scripts/build-persisted-urls.sh)** — `wp graphql-pqc bulk-register` per `graphql/*.graphql`, merge/shuffle → `k6/urls-headless-day.txt`.
4. **Load + churn** — `run-k6-edge.sh` with `URLS_FILE=urls-headless-day.txt` + [scripts/k6-with-realistic-churn.sh](scripts/k6-with-realistic-churn.sh).

**Global IDs:** WPGraphQL uses `Relay::toGlobalId( 'term', $term_id )` for categories/tags and `Relay::toGlobalId( 'user', $user_id )` for users (see core `Model/Term.php`, `Model/User.php`).

## Hit rate expectations

Hit rate depends on **URL mix** (few hot shared queries + many long-tail singles) and **churn profile** (new posts, random updates, term flips, menu tweaks). It is not a single universal number.

## Optional follow-ups

- Extend `bulk-register` with taxonomy/user scans.
- Weighted k6 scenarios.
- Richer `RESULTS.template.md` churn fields.
