# Abilities under the hood of WPGraphQL — an experiment

> **TL;DR:** We genuinely hoped the new WordPress [Abilities API](https://developer.wordpress.org/apis/abilities-api/) could let WPGraphQL delete code and/or run more efficiently by resolving data through abilities instead of its own DataLoader + Model layer. We built three working experiments (plus a POC) on real WordPress 7.0. The honest result: **as a layer *underneath* WPGraphQL, abilities don't help** — they either lose batching, lose lazy field resolution, or just add overhead, and they narrow which content is reachable. They shine *above* the resolver as an outward-facing, typed, discoverable contract — but in that role they overlap almost entirely with a GraphQL **persisted query**, which already derives its types from the schema. This is **not** a knock on the Abilities API or the people building it; it's a real-world report on where the seams are, with the most value in the recommendations for the read-ability contract itself.

This is the write-up behind the [PR](https://github.com/WordPress/ai/pull/739) discussion: rather than just reasoning about whether abilities could DRY up or speed up WPGraphQL, we built it three different ways and measured each. It's a **throwaway experiment branch**, not intended for merge, shared as evidence rather than a proposal. Everything lives under `prototype/abilities-under-the-hood/` and is removed by deleting that directory and the one `require_once` in `wp-graphql.php`.

## Why we did this

On a call about the Abilities API and [WordPress/ai#739](https://github.com/WordPress/ai/pull/739), we hypothesized that abilities — a registry of named operations with typed input, typed output, and a permission gate — might let WPGraphQL hand off some of what its DataLoader and Model layers do today: fetching objects, running capability checks, shaping fields. If a central, reusable ability could "get a post" (and check permissions) for us, maybe we could DRY up the model layer and share that logic with REST, WP-CLI, and AI agents.

We were optimistic. We were also skeptical, because WPGraphQL's loaders and models exist precisely to solve problems (N+1, over-fetching, lazy per-field resolution, per-row authorization) that a single request/response call doesn't obviously solve. So we tested it.

## What we built

Two stand-in read abilities (`wpgraphql/get-post`, `wpgraphql/get-posts`) shaped around the recommendations we shared on the [PR](https://github.com/WordPress/ai/pull/739) (split single vs list, lean default payload, raw behind explicit intent, opt-in totals, batch-by-IDs). Then we refactored WPGraphQL to resolve through them in three different places, measuring each. See [methodology](docs/methodology.md) for how to run and measure.

## The experiments at a glance

| # | Where the ability is used | Batching | Laziness | Result |
|---|---|---|---|---|
| [A](docs/experiment-a-loader-fetch-permission.md) | DataLoader does fetch + permission | ✅ | n/a (output discarded) | Parity, but +overhead and no gain; silently drops revisions / menu items / non-public types |
| [B](docs/experiment-b-ability-as-data-source.md) | Ability is the data source | ✅ | ❌ fixed payload | Over-fetch / over-process (renders content nobody asked for) or under-fetch |
| [C](docs/experiment-c-field-delegation.md) | Each Model field delegates to the ability | ❌ N+1 | ✅ | Correct and lazy, but one `execute()` per field per node + redundant authz |
| [POC](docs/poc-persisted-query-to-ability.md) | Ability generated *from* a persisted query | — | — | Works great — and shows the ability is a thin, redundant wrapper over a persisted query |

## The headline

WPGraphQL is efficient because it batches **across nodes** *and* resolves lazily **across fields** at the same time. A single request/response ability can sit on one of those axes but not both — so every way of pushing it *under* WPGraphQL surrenders one of them.

Abilities are a strong **fetch + authorization primitive** and an excellent **outward-facing, identity-agnostic contract**. That value lives *above* the resolver. And once you're above the resolver, an ability that wraps a GraphQL operation is hard to distinguish from a **persisted query** — which already has a schema-derived contract for free.

Full reasoning and recommendations in [conclusions](docs/conclusions.md).

## Documents

- [Methodology & how to run](docs/methodology.md)
- [Experiment A — loader does fetch + permission](docs/experiment-a-loader-fetch-permission.md)
- [Experiment B — ability as the data source](docs/experiment-b-ability-as-data-source.md)
- [Experiment C — per-field delegation](docs/experiment-c-field-delegation.md)
- [POC — generate an ability from a persisted query](docs/poc-persisted-query-to-ability.md)
- [Conclusions & recommendations](docs/conclusions.md)
