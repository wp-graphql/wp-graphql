# WPGraphQL Content Blocks: node-scoped context risk matrix

## 1. Versions, sources, install

| Item | Value |
| --- | --- |
| Extension | WPGraphQL Content Blocks **v4.8.9** (`wp-graphql-content-blocks.php:11`, `readme.txt:6` Stable tag 4.8.9), github `wpengine/wp-graphql-content-blocks`, wp.org slug `wp-graphql-content-blocks`. Source read from `dl/wpengine_wp-graphql-content-blocks/wp-graphql-content-blocks-HEAD` (no `.git`, so no commit hash; the header version is the reference) |
| Base plugin | None. The "base" is WordPress core's block library. WP core **7.1.3** (`wordpress.org/latest.zip`, `wp-includes/version.php:19`) downloaded to `dl/wordpress/wordpress` |
| Core WPGraphQL reference | `origin/main` `src/Model/Model.php` (`__get` :140-155 memoizes each field after its first read; `prepare_field` :406-427 runs `setup()`/`tear_down()` only on that first read), `src/Model/Post.php` `setup()` :182-256, `src/Router.php:78` (request handled on `parse_request`, so the main `WP_Query` never runs and `$wp_query->post` stays empty) |
| NEW behavior reference | Working tree, branch `fix/model-restore-global-query`, `src/Model/NodeContext.php`. Entry points: `InstrumentSchema.php:109` (`enter()` before every field), `Model.php:457` (`run_as()` for model field reads), `Request.php:697` (`end_request()`). See "NEW behavior mechanics" below |

### NEW behavior mechanics (as implemented in `NodeContext.php`)

- **Set up once per node subtree.** `enter()` (:111-155) builds the chain of owning models from the response path. It keeps every stack entry whose `key` + `model` match (:138-145), **without re-checking the global state**, and only pushes (`setup()`) or pops (`tear_down()` + restore) the parts that differ. The docblock says so (:20-21): "set up once when resolution enters its part of the query ... not around each field".
- **Model field reads inside the node don't re-setup.** `run_as()` (:171-174) runs the callback directly when `$active_model === $model`. So reading `title` or `databaseId` inside the node no longer re-runs `Post::setup()`, unlike today's first-read setup.
- **Restore on leave.** `pop()` (:222-235) restores the snapshot taken at push. The snapshot covers all public `$wp_query` properties (`get_object_vars`, :296; written back :312-316) and the globals `post, authordata, id, currentday, currentmonth, page, pages, multipage, more, numpages` (:37, :318-324). Root fields get an empty chain, so the whole stack is popped back to the request's original state.
- **`$wp_query->post` is never set to the node.** `Post::setup()` calls `$wp_query->parse_query(...)` (`Post.php:223-243`; `setup_postdata` :252). That calls `WP_Query::init()` (WP `class-wp-query.php:811-814`), which runs **`unset( $this->post )`** (:550). `setup_postdata()` doesn't write it either. `$this->post` is only assigned by `get_posts`, `next_post` and `rewind_posts` (:3664, :3754, :3884), which never run on the main query in a WPGraphQL request. So **core/post-template's `wp_reset_postdata()` is still a no-op under the new behavior.** The working-tree `Post::tear_down()` (`Post.php:262-265`, restores `global_post` and calls `wp_reset_postdata()`) only runs on pop or `run_as`, not between sibling blocks.

```bash
wp plugin install wp-graphql --activate
wp plugin install wp-graphql-content-blocks --activate
# or the exact release:
wp plugin install https://github.com/wpengine/wp-graphql-content-blocks/releases/download/v4.8.9/wp-graphql-content-blocks.zip --activate
```

## 2. How the extension registers fields

| Registration | File:line |
| --- | --- |
| Bootstrap: `graphql_register_types` -> `init_block_editor_registry` | `includes/WPGraphQLContentBlocks.php:112` |
| `NodeWithEditorBlocks` interface + `editorBlocks(flat: Boolean)` field | `includes/Type/InterfaceType/EditorBlockInterface.php:44-70` (resolver :63-65) |
| `NodeWithEditorBlocks` applied to every GraphQL + REST + block-editor post type (by `graphql_single_name`, e.g. `Post`, `Page`). It is **not** applied to the `ContentNode` interface, so in a `contentNodes` query you reach it with `... on NodeWithEditorBlocks` | `includes/Registry/Registry.php:215-230`, eligibility in `includes/Utilities/WPHelpers.php:23-56` |
| `{Type}EditorBlock` + `NodeWith{Type}EditorBlocks.editorBlocks` (per post type, filtered by allowed blocks) | `includes/Type/InterfaceType/PostTypeBlockInterface.php:25-77` (resolver :70-72), applied at `Registry.php:252-255` |
| `EditorBlock` interface: `clientId`, `parentClientId`, `name`, `blockEditorCategoryName`, `isDynamic`, `apiVersion`, `innerBlocks`, `cssClassNames`, `renderedHtml`, `type` | `EditorBlockInterface.php:73-160` (`renderedHtml` :140-145) |
| One object type per registered block (`CorePostTitle`, `CoreQuery`, `CorePostTemplate`, `CorePostTerms`, `CoreLatestPosts`, ...) | `Registry.php:272-307` -> `includes/Blocks/Block.php:420-441` |
| `{Block}.attributes` -> `{Block}Attributes` with one field per block.json attribute | `Block.php:131-163`; per-attribute resolver `Block.php:286-293` |
| `BlockWithSupportsAnchor.anchor` (blocks with `supports.anchor`, which includes core/query, core/post-template, core/post-title, core/post-terms, core/latest-posts per their block.json) | `includes/Field/BlockSupports/Anchor.php:22-46` (resolver :32-41) |
| `CorePostTerms.prefix`, `.suffix` | `includes/Blocks/CorePostTerms.php:35-55` |
| `CorePostTerms.terms` (connection to `TermNode`) | `CorePostTerms.php:66-91` (resolver :73-88) |
| `CorePostTerms.taxonomy` (one-to-one connection to `Taxonomy`) | `CorePostTerms.php:94-115` |
| `CoreImage.mediaDetails` | `includes/Blocks/CoreImage.php:51-75` |

No root fields, no mutations, no Model subclasses (`grep "extends .*Model"` finds nothing in `includes/`), no `Deferred`/`load_deferred` in the extension.

### How block data is loaded (`ContentBlocksResolver::resolve_content_blocks`)

1. `wpgraphql_content_blocks_pre_resolve_blocks` short-circuit filter (`includes/Data/ContentBlocksResolver.php:34-38`).
2. If `$node instanceof WPGraphQL\Model\Post`: **reads model field `$node->databaseId`** (`:50`), then `get_post( $databaseId )` and `post_password_required()` (`:50-56`), raw `post_content` (`:58`). It does not use `contentRaw`/`contentRendered`, `do_blocks`, or the `the_content` filter. The `$node->databaseId` read is the first read of that field on a fresh model unless the query already selected `databaseId`. A first read goes through `Model::prepare_field` -> `Post::setup()`, so **today the resolver itself sets global `$post` to the node before any block is processed**, and the tear_down no-op leaves it set. `:75` reads `databaseId` again (memoized, no setup).
3. `wpgraphql_content_blocks_resolver_content` filter (`:68`), then `parse_blocks()` plus its own `do_blocks`-like hydration with **no rendering** (`:112-176`):
   - `core/template-part`: `get_block_templates(slug)`, explicit (`:207-228`)
   - `core/post-content`: **`get_post()` with no argument** (`:242`), which inlines the current global post's content
   - `core/block` (synced pattern): `get_post( $attrs['ref'] )`, explicit. The `wp_block` content is inlined in place of the `core/block`, with `array_merge(...$parsed_blocks)` (`:294-312`). The `core/block` is **replaced** by its inner blocks, so `render_block_core_block` never runs for it
   - `core/pattern`: `resolve_pattern_blocks` (`:320-338`). `core/navigation`: `get_post(ref)`, explicit (`:266-285`)
4. Flatten (default `flat` true, `:84-86`), allow-list filter, `wpgraphql_content_blocks_resolve_blocks` filter.

**Rendering happens later, in child fields whose source is a plain block array.** These fields read no model field, so they never trigger `setup()`:
- `renderedHtml` -> `WPGraphQLHelpers::get_rendered_block()` -> `render_block()`, memoized in a **static per-process cache keyed by clientId** (`includes/Utilities/WPGraphQLHelpers.php:21,65-88`). clientIds are seeded with the node's `databaseId` (`ContentBlocksResolver.php:75,392-398`).
- every `{Block}Attributes.*` field -> `render_block( $block )` **uncached, once per attribute field** (`Block.php:290`), then DOM-parses the HTML (`includes/Data/BlockAttributeResolver.php:28-89`).
- `anchor` -> `get_rendered_block()` (cached) when `attrs.anchor` is empty (`Anchor.php:38`).

WP core `render_block()` derives block context from **global `$post`**: `global $post` (`wp-includes/blocks.php:2413`), `$context['postId'] = $post->ID` (`blocks.php:2478-2479`). So every context-using dynamic block renders against whatever global `$post` is when the child field resolves.

## 3. Risky fields

Here "WP" means `dl/wordpress/wordpress/wp-includes/`.

| `Type.field` | Resolver file:line | Global(s) read (downstream file:line) | Cat. | Why, under the NEW behavior |
| --- | --- | --- | --- | --- |
| `EditorBlock.renderedHtml` on **CoreQuery / CorePostTemplate** blocks | `EditorBlockInterface.php:140-145` -> `WPGraphQLHelpers.php:84` | `render_block` -> `render_block_core_post_template` (WP `blocks/post-template.php:49`): `new WP_Query` (:72), `$query->the_post()` in the loop (:105; WP `class-wp-query.php:3768-3813` assigns global `$post` + `setup_postdata`), `get_the_ID()` (:114), then **`wp_reset_postdata()`** (:142) -> `WP_Query::reset_postdata()` (WP `class-wp-query.php:5112-5117`), which is a **no-op when `$wp_query->post` is empty**. It is always empty in a WPGraphQL request because the main query never runs (`Router.php:78`). `query.excludeCurrent` reads `get_the_ID()` (WP `blocks.php:2859-2863`). `query.inherit:true` loops the **main** `$wp_query` (WP `post-template.php:55-69`) | **5** (leak source) | **Today:** after the loop, global `$post` (and the postdata globals) = the **last looped post**, which leaks to every later sibling block in the same post and to everything resolved after the node, until some model field's first read re-runs `Post::setup()`. **New (NodeContext as implemented):** leaving the node (`pop()`, or the root field's empty chain) restores the pre-node snapshot, which **fixes the cross-node and root leak**. **Inside the node the leak persists:** the stack entry for the post is kept without re-checking (`NodeContext.php:138-145`), and `$wp_query->post` is unset by `parse_query` (see mechanics), so `wp_reset_postdata()` stays a no-op. Later sibling blocks still render the loop post. It is now also **not reset by a later model-field read** on the node (`run_as` short-circuit, `NodeContext.php:172-174`), which today's first-read `Post::setup()` did (see the next row and query (d)). `inherit:true` is unchanged: `parse_query` doesn't run the main query, so it still renders `''`. `excludeCurrent` (`get_the_ID()`) excludes the node, or the loop post once a loop has leaked |
| `{CoreQuery,CorePostTemplate}Attributes.*` (e.g. `CoreQueryAttributes.queryId`, `.tagName`) | `Block.php:286-293` | `render_block($block)` at `Block.php:290`, **uncached, on every attribute field**, so it re-runs the loop above each time | **5** | Same leak as the row above, repeated per selected attribute, even when the caller only wanted a static attribute. A flattened `CorePostTemplate` rendered on its own has no `query` context, so it runs a default query (`post`, DESC by date, default posts_per_page; WP `blocks.php:2820-2828`) and leaks that query's last post (the oldest) |
| `BlockWithSupportsAnchor.anchor` on CoreQuery / CorePostTemplate | `Anchor.php:32-41` (render at :38) | `get_rendered_block` -> same loop | **5** | Same as `renderedHtml` (shares its cache). Triggered whenever `attrs.anchor` is empty |
| `EditorBlock.renderedHtml` on context-reading dynamic blocks: CorePostTitle, CorePostTerms, CorePostDate, CorePostExcerpt, CorePostFeaturedImage, CorePostAuthor(Name/Biography), CorePostContent, CorePostNavigationLink | `EditorBlockInterface.php:140-145` -> `WPGraphQLHelpers.php:65-88` | `blocks.php:2413,2478-2479` (context `postId` from global `$post`). Then `post-title.php:19-20,41`, `post-terms.php:18-19,50`, `post-date.php:19-40` (binding `block-bindings/post-data.php:48`), `post-excerpt.php:18-19,55`, `post-featured-image.php:18-22`, `post-author.php:18-22`, `post-content.php:18-25` plus **`get_the_content()` with no ID** (:43; reads `$pages`/`$page`/`$more` postdata globals, WP `post-template.php:279-282`) and `the_content` filter (:50). `post-navigation-link.php:19` `is_singular()` + adjacent post of global `$post` | **2** (victim) | **Today:** correct only if no earlier sibling in the same `editorBlocks` list (or an earlier aliased `editorBlocks` on the same node) leaked a different `$post` (row 1). Otherwise it renders the **loop post's** title/terms/date/content. A first read of a model field between them (e.g. `title`) repairs it. **New:** the post is set up on entering the node, so these render the node post **unless** an earlier block in the same node ran a query loop. In that case they render the loop post, the same as today. Unlike today, a later model-field read on the node no longer repairs it (`run_as` short-circuit). **Cache caveat:** a wrong render is memoized by clientId for the rest of the process (`WPGraphQLHelpers.php:80-85`), so a later correct context does not repair it today. Note that a core/post-title inside a post-template, flattened and rendered on its own, never gets its loop-item post in either mode (leaked loop post today, node post under the new behavior) |
| `CorePostTerms.terms` | `CorePostTerms.php:73-88` | **`get_the_ID()`** (:79; WP `post-template.php:27-28` -> `get_post()` -> global `$post`) | **2** | **Today:** returns the terms of whatever global `$post` is, which is the node after `editorBlocks` triggered `Post::setup`, but the **loop post** if a CoreQuery was rendered earlier in the list, or if `editorBlocks` is aliased again after one (memoized `databaseId` means no re-setup). **New:** node post, except after an in-node loop render, where it is the loop post. In that case a `title` read in between no longer repairs it (query (d)). The id is captured synchronously before `get_connection()` returns its Deferred (:84-87), so it is **not** cat. 3. A flattened post-terms block inside a post-template gets the node's terms, not the loop item's (both modes) |
| `{Dynamic}Attributes.<sourced attr>` (attributes with a `source` that are parsed from rendered HTML of a dynamic block) | `Block.php:290` -> `BlockAttributeResolver.php:39-67` | `render_block` -> same as above | 2 (rare) | Most core dynamic-block attributes have no `source`, so values come from `attrs` (`BlockAttributeResolver.php:32-33`), not from HTML. Only third-party dynamic blocks with HTML-sourced attributes are affected. **unverified** for any specific block |
| `NodeWithEditorBlocks.editorBlocks` / `NodeWith{Type}EditorBlocks.editorBlocks` (Post source) | `EditorBlockInterface.php:63-65`, `PostTypeBlockInterface.php:70-72` -> `ContentBlocksResolver.php:50,242` | Model read `$node->databaseId` (:50; triggers `Post::setup` on first read); **`get_post()` no-arg** for `core/post-content` (:242) | SAFE (minor 2) | Content comes from `get_post( databaseId )`, explicit. `:242` reads global `$post`: today it is the node because of :50's setup, except when `databaseId` was memoized earlier and something leaked in between. New: always the node. **Pre-existing:** a node whose content contains `core/post-content` (e.g. a `wp_template` exposed to GraphQL) re-parses itself with no recursion guard (`:237-257` vs WP core's `$seen_ids` at `post-content.php:19,27`), in both modes |
| `editorBlocks` resolved from **non-Post** sources via `wpgraphql_content_blocks_pre_resolve_blocks` / `wpgraphql_content_blocks_resolver_content` (`ContentBlocksResolver.php:34,68`) | same | render path as above | 1 (**unverified**) | Only if a third party exposes `NodeWithEditorBlocks` on a non-Post type (menu, settings, custom type). Under the new behavior, blocks rendered there see the nearest Post/Term/User above, or the **original state** (global `$post` null, so `postId`-context blocks render `''`). Today they see the leaked last post. Also these get clientId seed `''` (`:75`), so the static render cache can serve one source's HTML to another with identical content |
| CoreLatestPosts `renderedHtml` / attributes | `WPGraphQLHelpers.php:84`, `Block.php:290` | WP `latest-posts.php:45-46` `global $post`; snapshot `$previous_post = $post` (:78), `the_post()` loop (:82), restores `$post = $previous_post; setup_postdata()` (:223-229) | SAFE | Self-restoring. It restores whatever was current: the node under the new behavior, or a leaked post today |
| Synced pattern (`core/block` ref -> different `wp_block` post) | `ContentBlocksResolver.php:294-312` | `get_post( ref )` explicit; inner blocks then render with the **host** post's global `$post` | SAFE | The nearest node is the host post, so e.g. a post-title inside the pattern renders the host's title. This matches WP front-end behavior. Today it is the same unless a sibling leaked (row 1) |
| CoreTemplatePart | `ContentBlocksResolver.php:207-228` | `get_block_templates` explicit; render WP `template-part.php:19-20` | SAFE | Inner dynamic blocks behave like the context-reading row above |
| CoreQueryTitle `renderedHtml` | `WPGraphQLHelpers.php:84` | WP `query-title.php:23-24` `is_archive()`, `is_search()` | SAFE | `Post::setup` `parse_query(p=)` makes `is_archive` false in both modes, so it renders `''` |
| `{Block}Attributes.<meta-sourced attr>` | `BlockAttributeResolver.php:61-63,191-198` | **`global $post_id`** | SAFE (unchanged) | Neither `setup_postdata` (WP `class-wp-query.php:4857` sets `$id, $authordata, $currentday, $currentmonth, $page, $pages, $multipage, $more, $numpages`) nor WPGraphQL sets `$post_id`, so this is null and the default is returned in both modes. It would change only if the new scoping started setting `$post_id` |
| `CorePostTerms.taxonomy` / `.prefix` / `.suffix`, `CoreImage.mediaDetails`, `EditorBlock.clientId/parentClientId/name/type/isDynamic/apiVersion/innerBlocks/cssClassNames/blockEditorCategoryName` | `CorePostTerms.php:43,51,102-112`; `CoreImage.php:63-74`; `EditorBlockInterface.php:81-153` | none (attrs / explicit ids) | SAFE | No global reads |

Categories 3, 4, 6: none found (no Deferred closures that read globals, no mutations, no Model subclasses).

**Leak to other extensions (cat. 1, caused by this extension):** today, a node whose blocks include a rendered CoreQuery/CorePostTemplate leaves global `$post` = the last looped post, **not the node**. Any later root or sibling resolver (from any extension) that reads globals sees a post that is not even in the query. Under the new behavior, leaving the node restores the original state.

## 4. Fixture (wp-cli)

Any theme works (the core blocks used here are registered regardless of theme).

```bash
HOSTCAT=$(wp term create category "Host Cat"  --slug=host-cat  --porcelain)
LOOPCAT=$(wp term create category "Loop Cat"  --slug=loop-cat  --porcelain)
CAT2=$(wp term create category "Cat Two"      --slug=cat-two   --porcelain)
CAT3=$(wp term create category "Cat Three"    --slug=cat-three --porcelain)

# Two oldest posts: the query loop (ASC by date, perPage 2) iterates exactly these. Last looped = "Loop B".
LA=$(wp post create --post_title="Loop A" --post_status=publish --post_date="2000-01-01 00:00:00" --post_category=$LOOPCAT --porcelain)
LB=$(wp post create --post_title="Loop B" --post_status=publish --post_date="2000-01-02 00:00:00" --post_category=$LOOPCAT --porcelain)

# Synced pattern (different wp_block post) containing a context-reading dynamic block.
SYN=$(wp post create --post_type=wp_block --post_title="Synced Title Pattern" --post_status=publish \
  --post_content='<!-- wp:post-title {"level":5} /-->' --porcelain)

# Host post: title + terms BEFORE a query loop, then title/terms/synced/latest-posts/title AFTER it.
cat > /tmp/p1.html <<EOF
<!-- wp:post-title {"level":2} /-->
<!-- wp:post-terms {"term":"category"} /-->
<!-- wp:query {"queryId":1,"query":{"perPage":2,"pages":0,"offset":0,"postType":"post","order":"asc","orderBy":"date","inherit":false}} -->
<div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --></div>
<!-- /wp:query -->
<!-- wp:post-title {"level":3} /-->
<!-- wp:post-terms {"term":"category"} /-->
<!-- wp:block {"ref":$SYN} /-->
<!-- wp:latest-posts {"postsToShow":2} /-->
<!-- wp:post-title {"level":4} /-->
EOF
P1=$(wp post create /tmp/p1.html --post_title="Host P1" --post_status=publish --post_category=$HOSTCAT --porcelain)

# Control posts with no query loop (detect cross-node bleed in lists).
P2=$(wp post create --post_title="Second P2" --post_status=publish --post_category=$CAT2 \
  --post_content='<!-- wp:post-title {"level":2} /--><!-- wp:post-terms {"term":"category"} /-->' --porcelain)
P3=$(wp post create --post_title="Third P3" --post_status=publish --post_category=$CAT3 \
  --post_content='<!-- wp:post-title {"level":2} /--><!-- wp:post-terms {"term":"category"} /-->' --porcelain)

# OPTIONAL test-only probe for query (e): a root field that reports global $post. Shared harness, not part of the extension.
cat > "$(wp eval 'echo WPMU_PLUGIN_DIR;')/debug-global-post.php" <<'PHP'
<?php
add_action( 'graphql_register_types', function () {
	register_graphql_field( 'RootQuery', 'debugGlobalPostId', [
		'type'    => 'Int',
		'resolve' => function () { $id = get_the_ID(); return $id ? (int) $id : null; },
	] );
} );
PHP

echo "{\"p1\":$P1,\"p2\":$P2,\"p3\":$P3,\"hostCat\":$HOSTCAT,\"la\":$LA,\"lb\":$LB}"
```

No plugin settings are needed. Keep `flat:false` where shown: the default flat list also emits the post-template on its own, which re-runs a default (DESC) query and changes which post leaks (`Block.php:290`, WP `blocks.php:2820-2828`).

Variables (fill with the echoed IDs):

```json
{ "p1": "<P1>", "p2": "<P2>", "p3": "<P3>", "hostCat": "<HOSTCAT>", "ids": ["<P1>", "<P2>", "<P3>"] }
```

## 5. Queries

(a) Single node: dynamic blocks rendered before and after a query loop in one `editorBlocks` list.
```graphql
query A($p1: ID!) {
  post(id: $p1, idType: DATABASE_ID) {
    editorBlocks(flat: false) {
      name
      renderedHtml
      ... on CorePostTerms { terms { nodes { name } } }
    }
  }
}
```
Compare items in order [0] post-title L2, [1] post-terms, [2] core/query, [3] post-title L3, [4] post-terms, [5] post-title L5 (from the synced pattern), [6] latest-posts, [7] post-title L4.
- **main:** [0] "Host P1", [1] terms `Host Cat`, [2] lists Loop A and Loop B. Then [3], [5], [7] render **"Loop B"** and [4] terms = **`Loop Cat`**.
- **new (NodeContext):** **identical to main.** [0]/[1] = Host P1/`Host Cat` (set up on node entry), [3], [5], [7] = "Loop B", [4] = `Loop Cat`. The in-node leak survives (no per-field re-check, `wp_reset_postdata` no-op). If [3]-[7] show Host P1, the implementation changed. The difference is outside the node, see (e).

(b) List of 3 differing nodes via a connection (tests cross-node bleed, plus the P1 in-node leak).
```graphql
query B($ids: [ID]) {
  posts(where: { in: $ids, orderby: { field: IN, order: ASC } }) {
    nodes {
      databaseId
      title
      editorBlocks(flat: false) {
        name
        renderedHtml
        ... on CorePostTerms { terms { nodes { name } } }
      }
    }
  }
}
```
- **main:** P1 behaves as in (a) (Loop B after the query). P2/P3 are correct ("Second P2"/`Cat Two`, "Third P3"/`Cat Three`): each node's `databaseId`/`title` first read re-runs `Post::setup`, so P1's leak does not reach P2.
- **new:** P1 is the same as (a) (Loop B after the query). P2/P3 are correct: entering `nodes/1` pops P1, restoring the pre-P1 snapshot, then pushes P2. Any P2/P3 value showing "Loop B" or "Host P1" is a regression.

(c) Nested under another node (category > posts > post), so post setup stacks on the term.
```graphql
query C($hostCat: ID!) {
  category(id: $hostCat, idType: DATABASE_ID) {
    name
    posts {
      nodes {
        title
        editorBlocks(flat: false) {
          name
          renderedHtml
          ... on CorePostTerms { terms { nodes { name } } }
        }
      }
    }
  }
}
```
- **main:** same as (a) for Host P1. The term context is overwritten by `Post::setup` when `title` is read.
- **new:** same as (a), including Loop B after the query. The chain is [category Term, post Post] with the Post on top. A post-title rendering "" or the category name means the stacking order is wrong.

(d) Field placed before vs after a model field (aliased `editorBlocks` on the same node; the second alias reads a memoized `databaseId`, so it does not re-setup).
```graphql
query D($p1: ID!) {
  post(id: $p1, idType: DATABASE_ID) {
    q: editorBlocks(flat: false) { name ... on CoreQuery { renderedHtml } }
    before: editorBlocks(flat: false) { name ... on CorePostTerms { terms { nodes { name } } } }
    title
    after: editorBlocks(flat: false) { name ... on CorePostTerms { terms { nodes { name } } } }
  }
}
```
- **main:** `q` renders the loop and leaks Loop B. In `before`, **both** post-terms blocks return `Loop Cat`. `title` (first read) re-runs `Post::setup`, so in `after` both return `Host Cat`. Moving `title` above `q` makes `before` also return `Loop Cat` (the leak happens after `title`).
- **new (NodeContext):** `before` **and `after`** both return **`Loop Cat`**. The post is set up once on node entry, so `title` runs through `run_as`'s short-circuit (`NodeContext.php:172-174`) and does not re-setup. **This is a regression for `after`** (main: `Host Cat`): field order no longer matters, and once a block in the node has run a query loop, the rest of the node sees the loop post. With `title` above `q`, both modes return `Loop Cat`/`Loop Cat`. (`terms` is used rather than `renderedHtml` because `renderedHtml` is cached by clientId and would hide the change.)

(e) "#4572 shape": a root field after a node whose blocks ran a query loop (uses the optional `debugGlobalPostId` probe; any other extension's global-reading root field shows the same thing).
```graphql
query E($p1: ID!) {
  post(id: $p1, idType: DATABASE_ID) {
    title
    editorBlocks(flat: false) { name renderedHtml }
  }
  debugGlobalPostId
}
```
- **main:** `debugGlobalPostId` = **Loop B's ID** (`$LB`). That is neither P1 nor null: the leak comes from `post-template.php:105/142`.
- **new (NodeContext):** `null`. The root field's empty chain pops the post entry, and `pop()` restores the pre-node snapshot, including `post` (`NodeContext.php:147-149, 222-235, 318-324`). So the extension's loop leak is contained to the node. Also run it with `editorBlocks` removed: main = P1's ID (`Post::setup` leak), new = null.

(f) Mutations: none registered by this extension.

## Notes for the core implementation

- This extension **mutates global `$post` from inside a node** (through core's `post-template` loop), and `wp_reset_postdata()` cannot undo it, because `parse_query` unsets `$wp_query->post`. NodeContext as written contains the leak at the node boundary (fixes (e)). It does not catch it inside the node, and it removes today's incidental repair (query (d) `after`). Possible fixes:
  - when the shared stack prefix is kept, verify the top entry, e.g. `$GLOBALS['post'] !== $model->data` triggers a re-setup;
  - or, after `setup()` in `push()`, set `$wp_query->post = $data` so core blocks' own `wp_reset_postdata()` lands on the node. That is restored on pop, because `$wp_query` props are snapshotted, but `is_singular && ! empty($this->post)` also changes `get_queried_object()` paths (WP `class-wp-query.php:4053-4054`). Verify that.
- `inherit:true` is safe as implemented: `parse_query` only, the main query's posts are never populated.
- Performance: every `{Block}Attributes.*` field calls `render_block` uncached (`Block.php:290`), so CoreQuery attributes run a `WP_Query` per field. A per-field context re-apply adds a `Post::setup()` (reset_postdata + parse_query + setup_postdata) for each block field, which can be hundreds per post.
