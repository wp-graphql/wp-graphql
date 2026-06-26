# Conclusions & recommendations

> **TL;DR:** Under WPGraphQL, abilities can't win on both axes that make WPGraphQL fast — batching across nodes *and* lazy resolution across fields — so every placement gives one up (or just adds overhead, or narrows reachable content). Above the resolver they're valuable as a typed, discoverable, identity-agnostic contract — but there they overlap almost entirely with a GraphQL **persisted query**, which derives its contract from the schema for free. The most useful output of this exercise isn't "abilities lose"; it's a set of **recommendations for the read-ability contract** so the GraphQL-less majority of WordPress (the API's real audience) doesn't have to rediscover, by hand, the problems WPGraphQL already solved. This is meant as constructive input, not criticism.

## What the experiments showed

| Placement | Batching | Laziness | Net |
|---|---|---|---|
| A — loader does fetch + authz | ✅ | n/a (output discarded) | parity + overhead, no gain; drops revisions / menu items / non-public types |
| B — ability is the data source | ✅ | ❌ fixed payload | over-fetch / over-process **or** under-fetch |
| C — per-field delegation | ❌ N+1 | ✅ | correct + lazy, but F×N executions + redundant authz |

WPGraphQL is fast because it batches across nodes **and** resolves lazily across fields **at the same time**. A single request/response ability sits on one of those axes but not both, so pushing it *under* WPGraphQL always surrenders one. The value of abilities is real, but it lives **above** the resolver.

## Abilities are, in essence, persisted queries

Once you're above the resolver, an ability that wraps a typed operation looks a lot like a persisted GraphQL query:

| Ability | Persisted GraphQL query |
|---|---|
| name / namespace | query id / alias |
| `input_schema` (hand-written JSON Schema, validated every call) | operation variables (typed by the schema, derived) |
| `output_schema` (hand-written JSON Schema, validated every call) | selection set (typed by the schema, derived) |
| `execute_callback` | the resolved operation |
| registry of named operations | the persisted-query store |
| **`permission_callback` — one coarse per-call gate** | **per-field / per-type / per-row authz, composed by resolvers** |

The one real divergence is authorization, and it favors GraphQL: a persisted query's authz is fine-grained and composed per resolver (the per-row visibility we kept having to port by hand in Experiments A–C), where the ability is a single coarse gate. The [POC](poc-persisted-query-to-ability.md) makes this concrete — an ability generated from a persisted query is validated and authz-correct for free, because the schema and resolvers already provide what the ability would otherwise hand-maintain.

So for a site running WPGraphQL, an ability that runs a GraphQL operation is a thin, mostly-redundant wrapper. If you want a named, typed, discoverable operation, a persisted query already is one.

## But WPGraphQL isn't the audience — and that's the important part

The Abilities API isn't aimed at WPGraphQL users. It's aimed at the **GraphQL-less majority of WordPress** and at agent/MCP interop — sites with no resolver layer, no dataloaders, no lazy field resolution. Those consumers are exactly the ones who will hit the walls from Experiments B and C — over-fetch, over-process, N+1 — **without** GraphQL's machinery to climb them.

That reframes everything we found from "abilities lose under WPGraphQL" (true but parochial) into a **design opportunity for the ability contract itself**. WPGraphQL spent years encoding solutions to these problems; that experience is worth contributing upstream.

## Recommendations for the read-ability contract

Offered constructively, from the seams this prototype hit:

1. **Make visibility publish/public-aware, not `read_post`-based.** A read ability that gates on `current_user_can('read_post', $id)` returns nothing to anonymous callers on published public posts (the cap maps to `read`, which logged-out users lack). Any public-facing read ability must encode "published + public = world-readable" itself.

   ```php
   // The trap: looks reasonable, silently breaks public reads.
   'permission_callback' => fn( $input ) => current_user_can( 'read_post', $input['id'] ),

   // Post 123 is published + public. A logged-out visitor:
   $ability->execute( [ 'id' => 123 ] ); // → WP_Error "no permission"
   // read_post maps to the `read` cap, which anonymous users don't have, so a
   // fully public post (visible to everyone on the front end) vanishes here.

   // The fix: encode "published + public = world-readable" before the cap check.
   'permission_callback' => function ( $input ) {
       $post = get_post( $input['id'] );
       $type = $post ? get_post_type_object( $post->post_type ) : null;
       if ( $post && 'publish' === $post->post_status && $type && ( $type->public || $type->publicly_queryable ) ) {
           return true;
       }
       return $post && current_user_can( 'read_post', $post->ID );
   },
   ```

2. **Split single vs list — and know that authz granularity follows.** `check_permissions()` is per-call, so single-entity abilities can gate declaratively, but list/batch abilities must filter row-by-row inside `execute()`. This is a structural reason for the split, beyond typing and pagination.

   ```php
   // permission_callback runs ONCE per call, with the args, not the matched rows:
   'permission_callback' => fn( $input ) => current_user_can( 'edit_posts' ), // all-or-nothing

   // so per-row visibility for a list has to move into execute() instead:
   'execute_callback' => function ( $input ) {
       return array_filter( array_map( function ( $post ) {
           return current_user_can( 'read_post', $post->ID ) ? format_post( $post ) : null;
       }, get_posts( $input ) ) );
   },
   ```

3. **Bake in batch-by-IDs.** A "load exactly these N ids in one call" mode is essential, or every composing consumer (a dataloader, REST `_embed`, an agent walking relations) is back to N+1.

4. **Lean default payload; expensive fields and raw content only on explicit request.** Rendering `content` runs `the_content`; returning it by default makes every caller pay for it. Raw content is pre-redaction (block markup, tokens, internal URLs) and should require explicit intent **and** capability — never the default shape.

5. **Make totals opt-in.** Computing `found_posts` is a cost a list caller often doesn't want; gate it behind a flag (and skip it with `no_found_rows` otherwise).

6. **Cover the full breadth of addressable content** (revisions, menu items, non-public types, all statuses) if "get many" is to back anything that resolves arbitrary nodes — `post_type => 'any'` silently drops them.

7. **Don't make developers hand-maintain parallel schemas.** Where a typed operation registry already exists (a GraphQL schema, a persisted query), **derive** the ability's input/output from it rather than asking for hand-written JSON Schemas that drift from behavior and are revalidated on every call. The POC shows this is straightforward.

## Where the value actually is

- **Under WPGraphQL:** net negative. Abilities give up batching or laziness, add overhead, and narrow reach. WPGraphQL's loader/Model already does this job well.
- **Above WPGraphQL:** valuable as an outward, identity-agnostic, discoverable surface for agents/MCP — but largely overlapping with persisted queries, which carry a schema-derived contract for free.
- **For the broader ecosystem:** the highest-leverage contribution is the contract itself. The recommendations above are WPGraphQL's hard-won lessons, offered so a read-ability ecosystem doesn't have to relearn them.

We came in hoping abilities could DRY up WPGraphQL or make it faster. They can't, under the hood — but the exercise turned into something more useful: concrete, tested input for the people designing the abilities the rest of WordPress will rely on.
