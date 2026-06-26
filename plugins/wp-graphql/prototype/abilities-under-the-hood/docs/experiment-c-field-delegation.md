# Experiment C — per-field delegation to the ability

> **TL;DR:** This is the best of the three, and it came straight from a good question: instead of the loader driving the ability, have each *lazy Model field callback* delegate to the ability — so `content` is only asked for when `content` is selected. It works: when `content` isn't requested it's byte-for-byte identical to native (zero overhead), and when it is, the content is correct with no over-processing. The cost is the opposite of Experiment B: an **ability-call N+1** — one `execute()` per node per delegated field (`get-post` ran 5× for 5 nodes), each repeating input/output validation and a permission check the loader already did.

## The hope

Experiment B over-processes because the *loader* can't see the selection set. But a Model field closure is itself lazy — it only runs when that field is selected. So if the `content` resolver delegates to the ability, content is only requested when the query asks for it. WPGraphQL keeps its Model and its laziness; the ability supplies just the field that was requested. (#739's ability already exposes a `fields` input property — this is the WPGraphQL-side way to actually use it.)

## What we changed

In `field` mode, the `contentRendered` closure in `Post` delegates to `wpgraphql/get-post` with `fields => ['content']` instead of rendering locally. The loader stays native.

```php
// Post::init() — contentRendered
if ( 'field' === wpgraphql_proto_resolve_mode() ) {
    return wpgraphql_proto_field_via_ability( (int) $this->data->ID, 'content' );
}
// ...native rendering otherwise
```

## Numbers (isolated processes, anonymous user; native loader)

| Query | Mode | dbQueries | theContent | abilityExec | content correct? |
|---|---|---|---|---|---|
| `posts(first:5){ id title date }` | native | 15 | 0 | — | n/a |
| same | **C (field)** | 15 | 0 | — | n/a (not selected) |
| `posts(first:5){ id title content }` | native | 15 | 4 | — | yes |
| same | **C (field)** | 15 | 5 | `get-post: 5` | yes |

## What we found

8. **This is the right shape — laziness is preserved.** When `content` isn't selected, `field` mode is byte-for-byte native: 0 ability calls, 0 `the_content`, same query count. No over-process, no over-fetch, correct output. It solves Experiment B.

9. **The cost is an ability-call N+1.** Selecting `content` on 5 nodes issues 5 `get-post` executions — one per node — because a Model field closure runs per node and the ability has no way to batch a field across nodes. Delegating F fields across N nodes is F×N executions. DB queries are unaffected (the loader already warmed the cache), so the cost is CPU: each `execute()` runs input validation + a permission check + output validation. **Each delegated field also re-checks permission per node**, redundant with the authorization the loader already did.

10. **A single-call ability can't satisfy both batching and laziness.** WPGraphQL is fast because it batches *across nodes* (dataloaders) **and** resolves lazily *across fields* (the Model) at the same time. The loader path (A/B) gives batching but not laziness → fixed-payload over-fetch. Per-field delegation (C) gives laziness but not batching → N+1 executions. A request/response ability is one call; it can sit on one axis but not both. To get both you'd wrap the ability in a dataloader that batches field-requests across nodes — at which point the ability is reduced to a thin data function and the framework's per-call permission/validation becomes per-batch overhead working against you.

## Takeaway

Per-field delegation is the most faithful "abilities under the hood" integration: it keeps everything WPGraphQL is good at and is correct. But it converts WPGraphQL's batched resolution into per-node ability calls — re-introducing the N+1 that dataloaders exist to remove, plus redundant authorization. It's not a disaster; it's just strictly more work than the Model doing the field itself.
