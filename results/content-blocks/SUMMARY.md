# WPGraphQL Content Blocks: node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

11 queries. Compared with `main`, **#4575** (`node-context`) changed extension output in 0, changed only the probe in 3, and left 8 the same. **The first version of #4575** (`original-pr`) changed extension output in 1, only the probe in 4, and left 6 the same.

## Anything to do?

**No changes needed.** Extension output is identical on `main` and with #4575. Blocks already rendered against their own post on `main` in every shape tested, and still do. See [IDEAS.md](../../IDEAS.md), "Faust and WPGraphQL Content Blocks: correct results with no changes".

## Findings

**What in the extension reads the current object.** Blocks render later, in child fields whose source is a plain block array:
- `renderedHtml` calls `WPGraphQLHelpers::get_rendered_block()` (`includes/Type/InterfaceType/EditorBlockInterface.php:144`, `includes/Utilities/WPGraphQLHelpers.php:65`), which calls `render_block()` (`:84`).
- Each `{Block}Attributes` field calls `render_block()` again (`includes/Blocks/Block.php:290`).

`render_block()` takes the block's `postId` context from the global `$post`. `CorePostTerms.terms` reads `get_the_ID()` (`includes/Blocks/CorePostTerms.php:79`). The `editorBlocks` resolver loads content by `$node->databaseId` (`includes/Data/ContentBlocksResolver.php:50`), and inlines `core/post-content` from `get_post()` with no argument (`:242`). (Version 4.8.9.)

**What was tested.** A post whose blocks are post title and post terms before and after a Query Loop, a synced pattern and Latest Posts. Shapes: single, a list of three, nested under a category, field order around `title`, query attributes, the default flat list, and probes at the root and inside the node.

**Before (`main`).** Every block value was right: Host P1's title and `Host Cat` terms before and after the loop, and `Second P2`/`Cat Two`, `Third P3`/`Cat Three` in the list. The research predicted that a Query Loop would leave the global post on its last post (Loop B) for later blocks. That doesn't happen on WordPress 7.0, because `WP_Block::render()` saves and restores `$post` around each dynamic block. On `main` the post did stay current after the node: `debugLateObject` at the root reported Host P1 (`"post":482`) in `cb-e`, and `Cat Two` as the queried object in `cb-g`.

**After (`node-context`).** Extension output is identical in all 11 queries. That's expected, since blocks were already rendering against their own post. Only the root probes changed (`cb-e`, `cb-e2`, `cb-g`), and they now report no current post. The predicted `after` regression in `cb-d` did not occur.

**First version of #4575 (`original-pr`).** In a list (`cb-b`), Host P1's and Second P2's blocks rendered the third post: `"[ext-matrix] Third P3"` and terms `"[ext-matrix] Cat Three"`. The post's state was restored after each model field, so the blocks rendered against whatever post was left current.

## Results by query

Each build's responses were captured twice and matched. Each cell compares that build's response with `main`'s for the same query:

- **Same as `main`**: every value in the response is identical.
- **Extension output**: values from the extension's own fields that differ, as the value on `main` → the value on that build. Long values show the part around the first difference.
- **Probe**: test-only fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), selected at a point in the query, that report what WordPress treats as current there: `post` is the global `$post`, `queried` the main query's queried object, `product` WooCommerce's global `$product`, `authordata` the global `$authordata`, and `singular`, `tax`, `isProduct` and the like are conditional tags. They aren't extension output; they show what an extension reading the current object would see. Inside a node the right answer is that node. At the root, after the nodes, it's whatever was current before the query, which on these requests is nothing (`none`).
- Fixture names such as `HOSTCAT`, `LOOPCAT`, `CAT2` are the ones in [`content.ids`](../content.ids), shown with their IDs.

The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), and the queries in [`queries/content-blocks/`](../../queries/content-blocks).

| Query | What it checks | #4575 (`node-context`) | First version of #4575 (`original-pr`) |
| --- | --- | --- | --- |
| `cb-a-single-loop-siblings` | one post, `editorBlocks(flat: false)` with title and terms before and after a Query Loop, a synced pattern and Latest Posts (`renderedHtml`, `terms`). | Same as `main`. | Same as `main`. |
| `cb-b-list-three` | three posts in a list (Host P1 with the loop, two plain posts), `title` before `editorBlocks`. | Same as `main`. | **Extension output:** 11 values changed<br>• `posts.nodes[0].editorBlocks[0].renderedHtml`: `"…title\">[ext-matrix] Host P1</h2>"` → `"…title\">[ext-matrix] Third P3</h2>"`<br>• `posts.nodes[0].editorBlocks[1].renderedHtml`: `"…8888/category/extmx-host-cat/\" rel=\"tag\">[ext-matrix] Ho…"` → `"…8888/category/extmx-cat-three/\" rel=\"tag\">[ext-matrix] C…"`<br>• `posts.nodes[0].editorBlocks[1].terms.nodes[0].name`: `"[ext-matrix] Host Cat"` → `"[ext-matrix] Cat Three"`<br>• …and 8 more |
| `cb-c-nested-category-posts` | posts nested under a category, each with `editorBlocks`. | Same as `main`. | Same as `main`. |
| `cb-d-loop-then-terms` | the Query Loop rendered in one `editorBlocks` alias, then post-terms `terms` in aliases before and after `title`. | Same as `main`. | Same as `main`. |
| `cb-d2-title-first-then-loop` | the same with `title` first. | Same as `main`. | Same as `main`. |
| `cb-e-root-probe-after-loop-node` | root probes (`debugGlobals`, `debugGlobalsLate`, `debugLateObject`) after a post whose blocks ran a Query Loop. | **Extension output:** same as `main`<br>**Probe** `lateObj`: post P1 (482) → none, queried P1 (482) → none, singular true → false | **Extension output:** same as `main`<br>**Probe** `late`: queried P1 (482) → none, singular true → false<br>**Probe** `lateObj`: queried P1 (482) → none, singular true → false |
| `cb-e2-root-probe-after-plain-node` | the same root probes after a post with only `title` selected. | **Extension output:** same as `main`<br>**Probe** `lateObj`: post P2 (483) → none, queried P2 (483) → none, singular true → false | **Extension output:** same as `main`<br>**Probe** `late`: queried P2 (483) → none, singular true → false<br>**Probe** `lateObj`: queried P2 (483) → none, singular true → false |
| `cb-f-query-attributes-then-terms` | CoreQuery `attributes` (rendered per attribute), then post-terms `terms`. | Same as `main`. | Same as `main`. |
| `cb-g-sibling-node-after-loop-node` | a second post resolved after a post whose Query Loop rendered, plus root probes. | **Extension output:** same as `main`<br>**Probe** `lateObj`: post P2 (483) → none, queried CAT2 (297) → none, tax true → false | **Extension output:** same as `main`<br>**Probe** `late`: queried CAT2 (297) → none, tax true → false<br>**Probe** `lateObj`: queried CAT2 (297) → none, tax true → false |
| `cb-h-flat-default` | default flat `editorBlocks`, where the post template is also listed and rendered on its own. | Same as `main`. | Same as `main`. |
| `cb-i-node-probe-around-loop` | `debugNodeGlobals` inside the post before and after the Query Loop renders and after `title`. | Same as `main`. | **Extension output:** same as `main`<br>**Probe** `post.s0`: queried P1 (482) → none, singular true → false<br>**Probe** `post.s1`: queried P1 (482) → none, singular true → false<br>**Probe** `post.s2`: queried P1 (482) → none, singular true → false |
