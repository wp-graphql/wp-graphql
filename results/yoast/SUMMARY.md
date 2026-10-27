# WPGraphQL for Yoast SEO: node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

21 queries. Compared with `main`, **#4575** (`node-context`) changed extension output in 1, changed only the probe in 1, and left 19 the same. **The first version of #4575** (`original-pr`) changed extension output in 8, only the probe in 0, and left 13 the same.

## Anything to do?

**No changes needed.** One value is corrected with no change to WPGraphQL for Yoast SEO: after a media item, a custom taxonomy term's `canonical` and `opengraphUrl` read `/graphql/` on `main`, and now read the term's link. Everything else is identical.

## Findings

**What in the extension reads the current object.** The resolvers call Yoast's surfaces with explicit objects (WPGraphQL for Yoast SEO 5.1.0, wp.org build):
- `for_post( $post->ID )` (`includes/resolvers/post-type.php:32`);
- `for_term( $term->term_id )` (`includes/resolvers/taxonomy.php:40`);
- `for_author( $user->userId )` (`includes/resolvers/user.php:23`);
- `for_post_type_archive()` (`includes/helpers/functions.php:356`).

Inside Yoast SEO 28.7, though, parts of the output come from conditional tags, so they depend on the main query:
- `Indexable_Presentation::generate_permalink()` returns `home_url( $wp->request )` when `is_attachment()` (`src/presentations/indexable-presentation.php:244-247`);
- the main image depends on `is_singular()` (`src/context/meta-tags-context.php:634-643`);
- the Blog breadcrumb needs `is_singular( 'post' )` (`src/generators/breadcrumbs-generator.php:371`).

**What was tested.** Post, list, nested user/post, custom-taxonomy-under-CPT and field-order shapes. Root `seo` (author meta, CPT archives) before and after posts, a CPT, the static front page and a media item, both in one operation and in batches. Also connection `pageInfo.seo`.

**Before (`main`).** Every node-level `seo` value was right, including breadcrumbs `Home > Blog > Alice Post`, each post's own `thumbnailUrl`, and the five mixed nodes in a list. One value was wrong: in `yoast-e5`, after a media item, the `genre` term's `canonical` and `opengraphUrl` were `"http://localhost:8888/graphql/"`. The media item was the current object, so `is_attachment()` was true. Core's Term setup only re-parses the main query for categories and tags, so that flag survived for `genre`.

The research predicted root `seo` would pick up leaked author, post type, image or front-page state (`e1` to `e4`, `i` to `l`). That didn't happen on any build. Single root nodes load through a deferred loader, so root `seo` resolved before their fields did. Batched operations each started clean (`debugGlobals` all null in `yoast-i`). The predicted bogus image on a genre under a book (`c2`) didn't occur either.

**After (`node-context`).** The genre term link is `http://localhost:8888/genre/extmx-fantasy/` for both fields. The term's part of the query starts from the request's state, not the media item's. Everything else is identical, apart from the probe in `yoast-g`.

**First version of #4575 (`original-pr`).** Every post's `seo` (8 queries) lost its primary image: `thumbnailUrl`, `primaryImageOfPage` and the `ImageObject` were gone from `schema.raw` and `fullHead`. The Blog crumb was dropped too (`"[ext-matrix] Alice Post"` as the second crumb). `seo` isn't a model field, so the post was no longer set up when Yoast ran.

## Results by query

Each build's responses were captured twice and matched. Each cell compares that build's response with `main`'s for the same query:

- **Same as `main`**: every value in the response is identical.
- **Extension output**: values from the extension's own fields that differ, as the value on `main` → the value on that build. Long values show the part around the first difference.
- **Probe**: test-only fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), selected at a point in the query, that report what WordPress treats as current there: `post` is the global `$post`, `queried` the main query's queried object, `product` WooCommerce's global `$product`, `authordata` the global `$authordata`, and `singular`, `tax`, `isProduct` and the like are conditional tags. They aren't extension output; they show what an extension reading the current object would see. Inside a node the right answer is that node. At the root, after the nodes, it's whatever was current before the query, which on these requests is nothing (`none`).
- Fixture names such as `ALICE`, `BOB`, `IMG1` are the ones in [`yoast.ids`](../yoast.ids), shown with their IDs.

The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), and the queries in [`queries/yoast/`](../../queries/yoast).

| Query | What it checks | #4575 (`node-context`) | First version of #4575 (`original-pr`) |
| --- | --- | --- | --- |
| `yoast-a-single-post` | one post, all `seo` fields including `fullHead`, `breadcrumbs`, `schema`. | Same as `main`. | **Extension output:** 75 values changed<br>• `post.seo.breadcrumbs[1].text`: `"[ext-matrix] Blog"` → `"[ext-matrix] Alice Post"`<br>• `post.seo.breadcrumbs[1].url`: `"http://localhost:8888/ext-matrix-blog/"` → `"http://localhost:8888/ext-matrix-alice-post/"`<br>• `post.seo.breadcrumbs[2].text`: `"[ext-matrix] Alice Post"` → *absent*<br>• …and 72 more |
| `yoast-b-list-mixed-content` | five content nodes in a list (two posts, a book, a child page, the static front page). | Same as `main`. | **Extension output:** 205 values changed<br>• `contentNodes.nodes[0].seo.breadcrumbs[1].text`: `"[ext-matrix] Blog"` → `"[ext-matrix] Alice Post"`<br>• `contentNodes.nodes[0].seo.breadcrumbs[2].text`: `"[ext-matrix] Alice Post"` → *absent*<br>• `contentNodes.nodes[0].seo.schema.raw.@graph[0].image.@id`: `"http://localhost:8888/ext-matrix-alice-post/#primaryimag…"` → *absent*<br>• …and 202 more |
| `yoast-c1-user-posts-and-author-under-post` | a user's `seo` and their posts' `seo`, plus `author.node.seo` and primary-category `isPrimary` edges under a post. | Same as `main`. | **Extension output:** 70 values changed<br>• `user.posts.nodes[0].seo.schema.raw.@graph[0].image.@id`: `"http://localhost:8888/ext-matrix-alice-post/#primaryimag…"` → *absent*<br>• `user.posts.nodes[0].seo.schema.raw.@graph[0].thumbnailUrl`: `"http://localhost:8888/wp-content/uploads/2026/10/extmx.p…"` → *absent*<br>• `user.posts.nodes[0].seo.schema.raw.@graph[1].image.@id`: `"http://localhost:8888/ext-matrix-alice-post/#primaryimag…"` → *absent*<br>• …and 67 more |
| `yoast-c2-custom-tax-under-cpt` | a `genre` term's `seo` nested under a `book`. | Same as `main`. | Same as `main`. |
| `yoast-d1-seo-before-model-fields` | a post's `seo` before `databaseId`/`title`. | Same as `main`. | **Extension output:** 70 values changed<br>• `post.seo.schema.raw.@graph[0].image.@id`: `"http://localhost:8888/ext-matrix-alice-post/#primaryimag…"` → *absent*<br>• `post.seo.schema.raw.@graph[0].thumbnailUrl`: `"http://localhost:8888/wp-content/uploads/2026/10/extmx.p…"` → *absent*<br>• `post.seo.schema.raw.@graph[1].image.@id`: `"http://localhost:8888/ext-matrix-alice-post/#primaryimag…"` → *absent*<br>• …and 67 more |
| `yoast-d2-seo-after-model-fields` | the same with `seo` after them. | Same as `main`. | **Extension output:** 70 values changed<br>• `post.seo.schema.raw.@graph[0].image.@id`: `"http://localhost:8888/ext-matrix-alice-post/#primaryimag…"` → *absent*<br>• `post.seo.schema.raw.@graph[0].thumbnailUrl`: `"http://localhost:8888/wp-content/uploads/2026/10/extmx.p…"` → *absent*<br>• `post.seo.schema.raw.@graph[1].image.@id`: `"http://localhost:8888/ext-matrix-alice-post/#primaryimag…"` → *absent*<br>• …and 67 more |
| `yoast-d3-root-seo-before-node` | root `seo.meta.author` before a post. | Same as `main`. | Same as `main`. |
| `yoast-e1-root-seo-after-post-authordata` | root `seo.meta.author` after a post. | Same as `main`. | Same as `main`. |
| `yoast-e2-root-seo-after-other-cpt` | root book archive titles after a `movie`. | Same as `main`. | Same as `main`. |
| `yoast-e3-root-archive-schema-after-post` | root book archive `schema`/`fullHead` after a post with a featured image. | Same as `main`. | Same as `main`. |
| `yoast-e4-root-archive-after-front-page` | root book archive after the static front page. | Same as `main`. | Same as `main`. |
| `yoast-e5-root-after-mediaitem` | root book archive `fullHead` and a root `genres` list's `canonical`/`opengraphUrl` after a media item. | **Extension output:** 2 values changed<br>• `genres.nodes[0].seo.canonical`: `"http://localhost:8888/graphql/"` → `"http://localhost:8888/genre/extmx-fantasy/"`<br>• `genres.nodes[0].seo.opengraphUrl`: `"http://localhost:8888/graphql/"` → `"http://localhost:8888/genre/extmx-fantasy/"` | **Extension output:** 2 values changed<br>• `genres.nodes[0].seo.canonical`: `"http://localhost:8888/graphql/"` → `"http://localhost:8888/genre/extmx-fantasy/"`<br>• `genres.nodes[0].seo.opengraphUrl`: `"http://localhost:8888/graphql/"` → `"http://localhost:8888/genre/extmx-fantasy/"` |
| `yoast-e6-pageinfo-seo-nodes-first` | `posts { nodes pageInfo { seo } }`, nodes first. | Same as `main`. | Same as `main`. |
| `yoast-e7-pageinfo-seo-first` | the same with `pageInfo` first. | Same as `main`. | Same as `main`. |
| `yoast-f-root-seo-before-post-schema` | root book archive before a post. | Same as `main`. | Same as `main`. |
| `yoast-g-post-fullhead-image-and-probe` | two posts' `fullHead`/`schema` (one with `title` before `seo`, one after), plus `debugLateObject`. | **Extension output:** same as `main`<br>**Probe** `lateObj`: authordata BOB (28) → none, post P2 (501) → none, queried P2 (501) → none, singular true → false | **Extension output:** 142 values changed<br>• `one.seo.fullHead`: `"…\": 0,\n\t            \"image\": {\n\t                \"@id\": \"h…"` → `"…\": 0,\n\t            \"articleSection\": [\n\t                …"`<br>• `one.seo.schema.raw.@graph[0].image.@id`: `"http://localhost:8888/ext-matrix-alice-post/#primaryimag…"` → *absent*<br>• `one.seo.schema.raw.@graph[0].thumbnailUrl`: `"http://localhost:8888/wp-content/uploads/2026/10/extmx.p…"` → *absent*<br>• …and 139 more<br>**Probe** `lateObj`: queried P2 (501) → none, singular true → false |
| `yoast-h-product-like-list-fullhead` | two posts and a book in a list, `fullHead`. | Same as `main`. | **Extension output:** 3 values changed<br>• `contentNodes.nodes[0].seo.fullHead`: `"…\": 0,\n\t            \"image\": {\n\t                \"@id\": \"h…"` → `"…\": 0,\n\t            \"articleSection\": [\n\t                …"`<br>• `contentNodes.nodes[1].seo.fullHead`: `"…: 0,\n\t            \"image\": {\n\t                \"@id\": \"ht…"` → `"…: 0,\n\t            \"inLanguage\": \"en-US\",\n\t            \"p…"`<br>• `contentNodes.nodes[2].seo.fullHead`: `"…   },\n\t            \"primaryImageOfPage\": {\n\t            …"` → `"…   },\n\t            \"datePublished\": \"2026-10-26T22:01:21…"` |
| `yoast-i-batch-root-seo-after-post` | batch, a post in operation 1, root `seo` (author, book archive) and `debugGlobals` in operation 2. | Same as `main`. | Same as `main`. |
| `yoast-j-batch-root-seo-after-movie` | batch, a movie, then root book archive titles. | Same as `main`. | Same as `main`. |
| `yoast-k-batch-root-seo-after-front-page` | batch, the front page, then the root book archive. | Same as `main`. | Same as `main`. |
| `yoast-l-batch-root-seo-after-mediaitem` | batch, a media item, then root book archive `fullHead`. | Same as `main`. | Same as `main`. |
