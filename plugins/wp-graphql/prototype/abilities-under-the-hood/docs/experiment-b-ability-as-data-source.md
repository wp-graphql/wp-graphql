# Experiment B — the ability as the data source

> **TL;DR:** If the ability is going to earn its keep, it should actually *provide the data*, not just IDs. So we had the loader request a full payload from the ability and resolve fields from it. The problem is structural: the DataLoader batches by **ID** and never sees the GraphQL selection set, so it has to ask for a **fixed payload** — which means the ability renders `the_content` for **every node even when the query only asked for `title`** (`theContent` went 0 → 6). Trim the payload to avoid that, and a query that *does* ask for `content` gets nothing. There's no fixed payload that's right for every query — which is exactly why GraphQL resolves lazily.

## The hope

Experiment A proved the ability's output gets discarded. The natural fix: make the ability the source of truth for the post's fields, so WPGraphQL stops re-deriving them. That's where "DRY up the model" would really pay off — the ability shapes the data once, for GraphQL and REST and agents alike.

## What we changed

In `output` mode, `PostObjectLoader::loadKeys()` asks `get-posts` for a fixed field set (`wpgraphql_proto_output_fields()`, including `content`). This models "the ability owns the post's fields." Crucially, the loader **cannot** pass the GraphQL selection set: `loadKeys( array $keys )` is keyed by ID and runs inside the dataloader with no `ResolveInfo`. The selection simply isn't available at that layer.

## Numbers (isolated processes, anonymous user)

| Query | Mode | theContent | Note |
|---|---|---|---|
| `posts(first:5){ id title date }` | native | 0 | content not selected → not rendered |
| same | A (permission) | 0 | ability output discarded |
| same | **B (output)** | **6** | **ability renders content for every node — nobody asked** |
| `posts(first:5){ id title content }` | native | 4 | rendered once, lazily |
| same | **B (output)** | **10** | see caveat below |

## What we found

5. **Over-processing is unavoidable once the ability is the data source.** A title-only query renders `the_content` for the whole batch (0 → 6). The ability *has* a `fields` parameter that could prevent this — but the dataloader can't supply it, because it batches by ID and is selection-agnostic. So the loader requests a fixed superset and the ability does the expensive work for fields nobody selected. This is the exact cost WPGraphQL's lazy, selection-driven Model avoids.

6. **Under-fetch is the only alternative, and it's worse.** Drop `content` from the requested payload and a query selecting `content` gets nothing back. There is no single fixed payload that's correct for all queries — which is the whole reason GraphQL resolves fields lazily per selection.

7. **The two patterns don't compose — naive integration double-works.** The `10` above is the ability rendering content (6) *and* the Model re-rendering it (4), because this mode is additive (it still returns `WP_Post`s and lets the Model resolve). A "true" replacement that served content straight from the ability payload would render ~6 (always, regardless of selection) and drop the double-count — but only by abandoning the Model, and with it lazy resolution, per-field capability gating, and the post's many non-payload fields. Either way the ability can't reproduce what the Model does; you trade double-work for lost features.

## Takeaway

Making the ability the data source gives you batching but not laziness. Because the batch point (the loader) can't see the selection set, you're forced to choose between over-fetch/over-process and under-fetch. The fix for *that* is to delegate per field instead — which preserves laziness but gives up batching. That's [Experiment C](experiment-c-field-delegation.md).
