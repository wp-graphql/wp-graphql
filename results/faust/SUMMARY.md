# Faust: node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

11 queries. Against `main`: **node-context** (#4575 as it stands) has 4 identical, 0 changed only in test probe fields, 7 with changed extension output; **original-pr** (the first version of #4575) has 2 identical, 0 probe only, 9 with changed extension output.

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

Each build's responses were captured twice and matched. "Probe only" means the only values that changed are test-only probe fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), which report the current object at a point in the query. Otherwise the cell lists the extension values that changed, as the value on `main` → the value on that build. The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), the queries in [`queries/faust/`](../../queries/faust), and the fixture IDs they used in [`content.ids`](../content.ids).

| Query | What it checks | node-context vs `main` | original-pr vs `main` |
| --- | --- | --- | --- |
| `faust-a-single-templates-first` | one post, `templates` before `databaseId`. | identical | `data.post.templates[0]`: "single-post-extmx-alpha" → "front-page"<br>`data.post.templates[1]`: "single-post" → "home"<br>`data.post.templates[2]`: "single" → "index"<br>…and 1 more |
| `faust-b-list-templates-before-title` | three posts in a list, `templates` selected before `title`. | `data.posts.nodes[0].templates[0]`: "single-post-extmx-gamma" → "single-post-extmx-alpha"<br>`data.posts.nodes[1].templates[0]`: "single-post-extmx-alpha" → "single-post-extmx-beta"<br>`data.posts.nodes[2].templates[0]`: "single-post-extmx-beta" → "single-post-extmx-gamma" | `data.posts.nodes[0].templates[0]`: "single-post-extmx-gamma" → "front-page"<br>`data.posts.nodes[0].templates[1]`: "single-post" → "home"<br>`data.posts.nodes[0].templates[2]`: "single" → "index"<br>…and 9 more |
| `faust-b2-terms-templates-conditionaltags` | three categories in a list, `templates` and `conditionalTags { isCategory isSingle }` before `name`. | `data.categories.nodes[0].templates[0]`: "category-extmx-news" → "category-extmx-events"<br>`data.categories.nodes[0].templates[1]`: "category-299" → "category-300"<br>`data.categories.nodes[1].templates[0]`: "category-extmx-events" → "category-extmx-guides"<br>…and 3 more | `data.categories.nodes[0].conditionalTags.isCategory`: true → false<br>`data.categories.nodes[0].templates[0]`: "category-extmx-news" → "front-page"<br>`data.categories.nodes[0].templates[1]`: "category-299" → "home"<br>…and 15 more |
| `faust-c-user-under-post` | a post's author `templates`, then the post's own `templates`. | identical | `data.post.author.node.templates[0]`: "author-extmx-fauthor" → "front-page"<br>`data.post.author.node.templates[1]`: "author-25" → "home"<br>`data.post.author.node.templates[2]`: "author" → "index"<br>…and 6 more |
| `faust-d-page-before-after-model-field` | a page read after a post, `templates` before and after `title`. | `data.before.templates[0]`: "single-post-extmx-alpha" → "page-extmx-about"<br>`data.before.templates[1]`: "single-post" → "page-488"<br>`data.before.templates[2]`: "single" → "page" | `data.after.templates[0]`: "page-extmx-about" → "front-page"<br>`data.after.templates[1]`: "page-488" → "home"<br>`data.after.templates[2]`: "page" → "index"<br>…and 5 more |
| `faust-e-contenttype-root-after-post` | `nodeByUri(uri: "/")` (ContentType) `templates` and `conditionalTags` after a post with a featured image. | `data.nodeByUri.conditionalTags.isFrontPage`: false → true<br>`data.nodeByUri.conditionalTags.isHome`: false → true<br>`data.nodeByUri.conditionalTags.isSingle`: true → false<br>…and 4 more | `data.nodeByUri.conditionalTags.isFrontPage`: false → true<br>`data.nodeByUri.conditionalTags.isHome`: false → true<br>`data.nodeByUri.conditionalTags.isSingle`: true → false<br>…and 4 more |
| `faust-e2-prepend-attachment-side-effect` | ContentType `templates` after a featured image is read, then a media item's `description`. | `data.nodeByUri.templates[0]`: "single-post-extmx-alpha" → "front-page"<br>`data.nodeByUri.templates[1]`: "single-post" → "home"<br>`data.nodeByUri.templates[2]`: "single" → "index"<br>…and 1 more | `data.nodeByUri.templates[0]`: "single-post-extmx-alpha" → "front-page"<br>`data.nodeByUri.templates[1]`: "single-post" → "home"<br>`data.nodeByUri.templates[2]`: "single" → "index"<br>…and 1 more |
| `faust-f-content-replacement` | a post's `content` (front-end URL rewriting). | identical | identical |
| `faust-g-sticky-conditionaltags-list` | three posts in a list (Beta sticky), `conditionalTags { isSticky isSingle isSingular }` before `databaseId`. | `data.posts.nodes[1].conditionalTags.isSticky`: false → true | `data.posts.nodes[0].conditionalTags.isSingle`: true → false<br>`data.posts.nodes[0].conditionalTags.isSingular`: true → false<br>`data.posts.nodes[1].conditionalTags.isSingle`: true → false<br>…and 3 more |
| `faust-h-mutation-authcode` | `generateAuthorizationCode` with a wrong password. | identical | identical |
| `faust-i-root-probe-after-nodes` | a page's `templates` after a post and its author, plus root `debugGlobalsLate` and `debugLateObject`. | `data.page.templates[0]`: "single-post-extmx-alpha" → "page-extmx-about"<br>`data.page.templates[1]`: "single-post" → "page-488"<br>`data.page.templates[2]`: "single" → "page" | `data.page.templates[0]`: "single-post-extmx-alpha" → "front-page"<br>`data.page.templates[1]`: "single-post" → "home"<br>`data.page.templates[2]`: "single" → "index"<br>…and 1 more |
