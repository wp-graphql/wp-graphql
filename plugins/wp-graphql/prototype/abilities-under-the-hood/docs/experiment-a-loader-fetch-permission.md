# Experiment A — the loader does fetch + permission via an ability

> **TL;DR:** We hoped the DataLoader could hand off both fetching posts *and* the per-row permission check to a `get-posts` ability, letting the Model stop doing `is_private()` itself — a real chance to DRY up the model layer and share that policy with REST/agents. It works and keeps behavior identical for ordinary posts, but it brings **no efficiency gain** (≈ +1 query of overhead), the ability's nicely-shaped output is **thrown away** (the Model still resolves every field), and it introduces a real regression: the generic ability **silently drops revisions, nav-menu items, and non-public post types** that WPGraphQL's loader includes.

## The hope

`PostObjectLoader` normally runs one `WP_Query(post__in)` to warm the cache, then each `Post` model decides visibility via `is_private()`. If a central "get these posts (that I'm allowed to see)" ability did the fetch *and* the authorization, WPGraphQL could delete its hand-rolled permission logic and reuse the same policy everywhere. That's the most appealing version of "abilities under the hood."

## What we changed

In `permission` mode, `PostObjectLoader::loadKeys()` calls `wpgraphql/get-posts` with `include => $ids`. The ability runs its own `WP_Query(post__in)` (warming the same cache), applies per-row visibility, and returns the survivors. The loader pulls those `WP_Post`s from the warmed cache, and the Model trusts the ability's filtering (the `graphql_pre_model_data_is_private` hook returns `false` while enabled). **Field resolution still happens in the Model.**

## Numbers (isolated processes, anonymous user)

| Query | Mode | dbQueries | wpQueryRuns | theContent |
|---|---|---|---|---|
| `posts(first:5){ id title date }` | native | 15 | 6 | 0 |
| same | ability (A) | 16 | 6 | 0 |
| `posts(first:5){ id title content }` | native | 15 | 6 | 4 |
| same | ability (A) | 16 | 6 | 4 |
| `post(id:N){ id title content }` | native | 14 | 5 | 1 |
| same | ability (A) | 15 | 5 | 1 |

## What we found

1. **Behavior parity for the common case.** Identical results for anonymous and admin; drafts correctly excluded. The catch: this only works because the ability **re-implements `Post::is_private()`** internally. A naive `read_post`-based ability returns nothing to anonymous users on published posts.

2. **No efficiency gain — parity plus ~1 query.** The ability's `execute()` (input + output schema validation) sits on top of a `WP_Query` that is essentially the one the loader already runs. (An early same-process measurement *looked* like a 33% query reduction; that was a `wp_template` memoization artifact — see [methodology](methodology.md).)

3. **A real breadth regression.** The native loader queries an explicit set — `get_allowed_post_types()` + `revision` + `nav_menu_item`, with `post_status => 'any'`. The generic ability uses `post_type => 'any'`, which *excludes* `revision`, `nav_menu_item`, auto-drafts, and non-public CPTs. Confirmed live:

   ```
   native  get_post(revision)            → post_type: revision  (works)
   ability get-posts include=[revision]  → 0 rows               (dropped)
   ability get-posts include=[nav_item]  → 0 rows               (dropped)
   ```

   Revisions being dropped is directly relevant to preview workflows. Teaching the ability about these types means leaking WPGraphQL-specific knowledge into a "core" ability — which raises the question of who owns that contract.

4. **The ability's output is wasted here.** This mode used the ability only for IDs + the visibility decision; the Model still resolves every field from the raw `WP_Post`. So all of the ability's careful payload shaping (lean fields, `fields` selection, raw gating) is computed and discarded. To actually *use* its output, you have to consume it for field resolution — which is [Experiment B](experiment-b-ability-as-data-source.md).

## Takeaway

The most attractive version of the idea — "let the ability own fetch + authz so the Model can stop" — is viable for plain posts but pays for itself in overhead and lost reach, and doesn't actually let the Model do less, because the Model still has to resolve every field. The DRY win we were hoping for doesn't materialize here.
