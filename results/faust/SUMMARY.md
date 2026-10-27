# Faust: node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

11 queries. Compared with `main`, **#4575** (`node-context`) changed extension output in 7, changed only the probe in 0, and left 4 the same. **The first version of #4575** (`original-pr`) changed extension output in 9, only the probe in 0, and left 2 the same.

## Anything to do?

**No changes needed.** `templates` and `conditionalTags` now describe the node they're selected under, with no change to Faust. On `main` they described the previous node in lists, a page read after a post, and the site root. See [IDEAS.md](../../IDEAS.md), "Faust and WPGraphQL Content Blocks: correct results with no changes".

## Findings

**What in the extension reads the current object.** Both resolvers ignore `$root`, and both live in `includes/graphql/callbacks.php` (Faust 1.9.0):
- `templates`, `templates_resolver()` (`:172`): takes `global $wp_query`, calls `$wp_query->parse_query()` with no arguments (`:188`), then runs WordPress's conditional tags and template getters (`:192-206`). For an attachment it removes `prepend_attachment` from `the_content` (`:202-204`).
- the deprecated `conditionalTags`, `conditional_tags_resolver()` (`:299-307`): calls every tag in `get_conditional_tags()` (`includes/graphql/functions.php:29`) with no arguments (`:303`), including `is_sticky()`, which reads `get_the_ID()`.

**What was tested.** Posts, categories, a user and a page in single, list, nested and field-order shapes. Also `nodeByUri(uri: "/")` (a ContentType) after a post, content URL replacement, and the `generateAuthorizationCode` mutation.

**Before (`main`).** The main query only moved to a node when one of that node's own model fields was read, so `templates` and `conditionalTags` described whichever node had been set up last:
- **Posts in a list** (`templates` before `title`): each got the previous post's templates. Alpha got `single-post-extmx-gamma`, Beta got `single-post-extmx-alpha`, Gamma got `single-post-extmx-beta`.
- **Categories:** off by one the same way (Events got `category-extmx-news`).
- **Pages:** a page read after a post got the post's templates (`single-post-extmx-alpha`).
- **The site root:** `nodeByUri(uri: "/")` returned the post's templates and `isSingle: true`.
- **`isSticky`:** false for Beta, the sticky post.

Single posts (`faust-a`) and the user under a post (`faust-c`) were already right. The research predicted `faust-c` would be wrong. In `faust-e2` the post, not the media item, was current, so the attachment branch didn't run. `description` was identical on all builds.

**After (`node-context`).** Each node's own templates: `single-post-extmx-alpha/beta/gamma` in order, `category-extmx-events`/`guides`/`news`, `page-extmx-about`, and `isSticky: true` for Beta. A ContentType isn't a node, so `nodeByUri(uri: "/")` starts from the request's original state. Faust's `parse_query()` then makes it the home query: `["front-page","home","index"]`, and `isHome`/`isFrontPage` true (`templates` ran first). That is the right hierarchy for `/` with posts on the front page. Content replacement and the mutation are identical.

**First version of #4575 (`original-pr`).** Every node returned `["front-page","home","index"]` (posts, categories, the user, pages). In lists, `isCategory` and `isSingle` were false. The node's state had been restored before `templates` ran.

## Results by query

Each build's responses were captured twice and matched. Each cell compares that build's response with `main`'s for the same query:

- **Same as `main`**: every value in the response is identical.
- **Extension output**: values from the extension's own fields that differ, as the value on `main` → the value on that build. Long values show the part around the first difference.
- **Probe**: test-only fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), selected at a point in the query, that report what WordPress treats as current there: `post` is the global `$post`, `queried` the main query's queried object, `product` WooCommerce's global `$product`, `authordata` the global `$authordata`, and `singular`, `tax`, `isProduct` and the like are conditional tags. They aren't extension output; they show what an extension reading the current object would see. Inside a node the right answer is that node. At the root, after the nodes, it's whatever was current before the query, which on these requests is nothing (`none`).
- Fixture names such as `HOSTCAT`, `LOOPCAT`, `CAT2` are the ones in [`content.ids`](../content.ids), shown with their IDs.

The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), and the queries in [`queries/faust/`](../../queries/faust).

| Query | What it checks | #4575 (`node-context`) | First version of #4575 (`original-pr`) |
| --- | --- | --- | --- |
| `faust-a-single-templates-first` | one post, `templates` before `databaseId`. | Same as `main`. | **Extension output:** 4 values changed<br>• `post.templates[0]`: `"single-post-extmx-alpha"` → `"front-page"`<br>• `post.templates[1]`: `"single-post"` → `"home"`<br>• `post.templates[2]`: `"single"` → `"index"`<br>• …and 1 more |
| `faust-b-list-templates-before-title` | three posts in a list, `templates` selected before `title`. | **Extension output:** 3 values changed<br>• `posts.nodes[0].templates[0]`: `"single-post-extmx-gamma"` → `"single-post-extmx-alpha"`<br>• `posts.nodes[1].templates[0]`: `"single-post-extmx-alpha"` → `"single-post-extmx-beta"`<br>• `posts.nodes[2].templates[0]`: `"single-post-extmx-beta"` → `"single-post-extmx-gamma"` | **Extension output:** 12 values changed<br>• `posts.nodes[0].templates[0]`: `"single-post-extmx-gamma"` → `"front-page"`<br>• `posts.nodes[0].templates[1]`: `"single-post"` → `"home"`<br>• `posts.nodes[0].templates[2]`: `"single"` → `"index"`<br>• …and 9 more |
| `faust-b2-terms-templates-conditionaltags` | three categories in a list, `templates` and `conditionalTags { isCategory isSingle }` before `name`. | **Extension output:** 6 values changed<br>• `categories.nodes[0].templates[0]`: `"category-extmx-news"` → `"category-extmx-events"`<br>• `categories.nodes[0].templates[1]`: `"category-299"` → `"category-300"`<br>• `categories.nodes[1].templates[0]`: `"category-extmx-events"` → `"category-extmx-guides"`<br>• …and 3 more | **Extension output:** 18 values changed<br>• `categories.nodes[0].conditionalTags.isCategory`: `true` → `false`<br>• `categories.nodes[0].templates[0]`: `"category-extmx-news"` → `"front-page"`<br>• `categories.nodes[0].templates[1]`: `"category-299"` → `"home"`<br>• …and 15 more |
| `faust-c-user-under-post` | a post's author `templates`, then the post's own `templates`. | Same as `main`. | **Extension output:** 9 values changed<br>• `post.author.node.templates[0]`: `"author-extmx-fauthor"` → `"front-page"`<br>• `post.author.node.templates[1]`: `"author-25"` → `"home"`<br>• `post.author.node.templates[2]`: `"author"` → `"index"`<br>• …and 6 more |
| `faust-d-page-before-after-model-field` | a page read after a post, `templates` before and after `title`. | **Extension output:** 3 values changed<br>• `before.templates[0]`: `"single-post-extmx-alpha"` → `"page-extmx-about"`<br>• `before.templates[1]`: `"single-post"` → `"page-488"`<br>• `before.templates[2]`: `"single"` → `"page"` | **Extension output:** 8 values changed<br>• `after.templates[0]`: `"page-extmx-about"` → `"front-page"`<br>• `after.templates[1]`: `"page-488"` → `"home"`<br>• `after.templates[2]`: `"page"` → `"index"`<br>• …and 5 more |
| `faust-e-contenttype-root-after-post` | `nodeByUri(uri: "/")` (ContentType) `templates` and `conditionalTags` after a post with a featured image. | **Extension output:** 7 values changed<br>• `nodeByUri.conditionalTags.isFrontPage`: `false` → `true`<br>• `nodeByUri.conditionalTags.isHome`: `false` → `true`<br>• `nodeByUri.conditionalTags.isSingle`: `true` → `false`<br>• …and 4 more | **Extension output:** 7 values changed<br>• `nodeByUri.conditionalTags.isFrontPage`: `false` → `true`<br>• `nodeByUri.conditionalTags.isHome`: `false` → `true`<br>• `nodeByUri.conditionalTags.isSingle`: `true` → `false`<br>• …and 4 more |
| `faust-e2-prepend-attachment-side-effect` | ContentType `templates` after a featured image is read, then a media item's `description`. | **Extension output:** 4 values changed<br>• `nodeByUri.templates[0]`: `"single-post-extmx-alpha"` → `"front-page"`<br>• `nodeByUri.templates[1]`: `"single-post"` → `"home"`<br>• `nodeByUri.templates[2]`: `"single"` → `"index"`<br>• …and 1 more | **Extension output:** 4 values changed<br>• `nodeByUri.templates[0]`: `"single-post-extmx-alpha"` → `"front-page"`<br>• `nodeByUri.templates[1]`: `"single-post"` → `"home"`<br>• `nodeByUri.templates[2]`: `"single"` → `"index"`<br>• …and 1 more |
| `faust-f-content-replacement` | a post's `content` (front-end URL rewriting). | Same as `main`. | Same as `main`. |
| `faust-g-sticky-conditionaltags-list` | three posts in a list (Beta sticky), `conditionalTags { isSticky isSingle isSingular }` before `databaseId`. | **Extension output:** 1 value changed<br>• `posts.nodes[1].conditionalTags.isSticky`: `false` → `true` | **Extension output:** 6 values changed<br>• `posts.nodes[0].conditionalTags.isSingle`: `true` → `false`<br>• `posts.nodes[0].conditionalTags.isSingular`: `true` → `false`<br>• `posts.nodes[1].conditionalTags.isSingle`: `true` → `false`<br>• …and 3 more |
| `faust-h-mutation-authcode` | `generateAuthorizationCode` with a wrong password. | Same as `main`. | Same as `main`. |
| `faust-i-root-probe-after-nodes` | a page's `templates` after a post and its author, plus root `debugGlobalsLate` and `debugLateObject`. | **Extension output:** 3 values changed<br>• `page.templates[0]`: `"single-post-extmx-alpha"` → `"page-extmx-about"`<br>• `page.templates[1]`: `"single-post"` → `"page-488"`<br>• `page.templates[2]`: `"single"` → `"page"`<br>**Probe** `lateObj`: author true → false, authordata FU (25) → none, post FA (485) → none, queried FU (25) → none | **Extension output:** 4 values changed<br>• `page.templates[0]`: `"single-post-extmx-alpha"` → `"front-page"`<br>• `page.templates[1]`: `"single-post"` → `"home"`<br>• `page.templates[2]`: `"single"` → `"index"`<br>• …and 1 more<br>**Probe** `late`: author true → false, home false → true, queried FU (25) → none<br>**Probe** `lateObj`: author true → false, home false → true, queried FU (25) → none |
