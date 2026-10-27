# WPGraphQL Content Blocks: node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

11 queries. Against `main`: **node-context** (#4575 as it stands) has 8 identical, 3 changed only in test probe fields, 0 with changed extension output; **original-pr** (the first version of #4575) has 6 identical, 4 probe only, 1 with changed extension output.

## Findings

**What in the extension reads the current object.** Blocks render later, in child fields whose source is a plain block array:
- `renderedHtml` calls `WPGraphQLHelpers::get_rendered_block()` (`includes/Type/InterfaceType/EditorBlockInterface.php:144`, `includes/Utilities/WPGraphQLHelpers.php:65`), which calls `render_block()` (`:84`).
- Each `{Block}Attributes` field calls `render_block()` again (`includes/Blocks/Block.php:290`).

`render_block()` takes the block's `postId` context from the global `$post`. `CorePostTerms.terms` reads `get_the_ID()` (`includes/Blocks/CorePostTerms.php:79`). The `editorBlocks` resolver loads content by `$node->databaseId` (`includes/Data/ContentBlocksResolver.php:50`), and inlines `core/post-content` from `get_post()` with no argument (`:242`). (Version 4.8.9.)

**What was tested.** A post whose blocks are post title and post terms before and after a Query Loop, a synced pattern and Latest Posts. Shapes: single, a list of three, nested under a category, field order around `title`, query attributes, the default flat list, and probes at the root and inside the node.

**Before (`main`).** Every block value was right: Host P1's title and `Host Cat` terms before and after the loop, and `Second P2`/`Cat Two`, `Third P3`/`Cat Three` in the list. The research predicted that a Query Loop would leave the global post on its last post (Loop B) for later blocks. That doesn't happen on WordPress 7.0, because `WP_Block::render()` saves and restores `$post` around each dynamic block. On `main` the post did stay current after the node: `debugLateObject` at the root reported Host P1 (`"post":482`) in `cb-e`, and `Cat Two` as the queried object in `cb-g`.

**After (`node-context`).** Extension output is identical in all 11 queries. That's expected, since blocks were already rendering against their own post. Only the root probes changed (`cb-e`, `cb-e2`, `cb-g`), and they now report no current post. The predicted `after` regression in `cb-d` did not occur.

**First version of #4575 (`original-pr`).** In a list (`cb-b`), Host P1's and Second P2's blocks rendered the third post: `"[ext-matrix] Third P3"` and terms `"[ext-matrix] Cat Three"`. The post's state was restored after each model field, so the blocks rendered against whatever post was left current.

**Anything to do.** Nothing. See [IDEAS.md](../../IDEAS.md), "Faust and WPGraphQL Content Blocks: correct results with no changes".

## Results by query

Each build's responses were captured twice and matched. "Probe only" means the only values that changed are test-only probe fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), which report the current object at a point in the query. Otherwise the cell lists the extension values that changed, as the value on `main` → the value on that build. The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), the queries in [`queries/content-blocks/`](../../queries/content-blocks), and the fixture IDs they used in [`content.ids`](../content.ids).

| Query | What it checks | node-context vs `main` | original-pr vs `main` |
| --- | --- | --- | --- |
| `cb-a-single-loop-siblings` | one post, `editorBlocks(flat: false)` with title and terms before and after a Query Loop, a synced pattern and Latest Posts (`renderedHtml`, `terms`). | identical | identical |
| `cb-b-list-three` | three posts in a list (Host P1 with the loop, two plain posts), `title` before `editorBlocks`. | identical | `data.posts.nodes[0].editorBlocks[0].renderedHtml`: "<h2 class=\"wp-block-post-title\">[ext-matrix] Host P1</... → "<h2 class=\"wp-block-post-title\">[ext-matrix] Third P3<...<br>`data.posts.nodes[0].editorBlocks[1].renderedHtml`: "<div class=\"taxonomy-category wp-block-post-terms\"><a ... → "<div class=\"taxonomy-category wp-block-post-terms\"><a ...<br>`data.posts.nodes[0].editorBlocks[1].terms.nodes[0].name`: "[ext-matrix] Host Cat" → "[ext-matrix] Cat Three"<br>…and 8 more |
| `cb-c-nested-category-posts` | posts nested under a category, each with `editorBlocks`. | identical | identical |
| `cb-d-loop-then-terms` | the Query Loop rendered in one `editorBlocks` alias, then post-terms `terms` in aliases before and after `title`. | identical | identical |
| `cb-d2-title-first-then-loop` | the same with `title` first. | identical | identical |
| `cb-e-root-probe-after-loop-node` | root probes (`debugGlobals`, `debugGlobalsLate`, `debugLateObject`) after a post whose blocks ran a Query Loop. | probe only | probe only |
| `cb-e2-root-probe-after-plain-node` | the same root probes after a post with only `title` selected. | probe only | probe only |
| `cb-f-query-attributes-then-terms` | CoreQuery `attributes` (rendered per attribute), then post-terms `terms`. | identical | identical |
| `cb-g-sibling-node-after-loop-node` | a second post resolved after a post whose Query Loop rendered, plus root probes. | probe only | probe only |
| `cb-h-flat-default` | default flat `editorBlocks`, where the post template is also listed and rendered on its own. | identical | identical |
| `cb-i-node-probe-around-loop` | `debugNodeGlobals` inside the post before and after the Query Loop renders and after `title`. | identical | probe only |
