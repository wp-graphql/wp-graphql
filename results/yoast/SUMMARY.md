# WPGraphQL for Yoast SEO: node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

21 queries. Against `main`: **node-context** (#4575 as it stands) has 19 identical, 1 changed only in test probe fields, 1 with changed extension output; **original-pr** (the first version of #4575) has 13 identical, 0 probe only, 8 with changed extension output.

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

Each build's responses were captured twice and matched. "Probe only" means the only values that changed are test-only probe fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), which report the current object at a point in the query. Otherwise the cell lists the extension values that changed, as the value on `main` → the value on that build. The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), the queries in [`queries/yoast/`](../../queries/yoast), and the fixture IDs they used in [`yoast.ids`](../yoast.ids).

| Query | What it checks | node-context vs `main` | original-pr vs `main` |
| --- | --- | --- | --- |
| `yoast-a-single-post` | one post, all `seo` fields including `fullHead`, `breadcrumbs`, `schema`. | identical | `data.post.seo.breadcrumbs[1].text`: "[ext-matrix] Blog" → "[ext-matrix] Alice Post"<br>`data.post.seo.breadcrumbs[1].url`: "http://localhost:8888/ext-matrix-blog/" → "http://localhost:8888/ext-matrix-alice-post/"<br>`data.post.seo.breadcrumbs[2].text`: "[ext-matrix] Alice Post" → null<br>…and 3 more |
| `yoast-b-list-mixed-content` | five content nodes in a list (two posts, a book, a child page, the static front page). | identical | `data.contentNodes.nodes[0].seo.breadcrumbs[1].text`: "[ext-matrix] Blog" → "[ext-matrix] Alice Post"<br>`data.contentNodes.nodes[0].seo.breadcrumbs[2].text`: "[ext-matrix] Alice Post" → null<br>`data.contentNodes.nodes[0].seo.schema.raw`: "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... → "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ...<br>…and 6 more |
| `yoast-c1-user-posts-and-author-under-post` | a user's `seo` and their posts' `seo`, plus `author.node.seo` and primary-category `isPrimary` edges under a post. | identical | `data.user.posts.nodes[0].seo.schema.raw`: "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... → "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... |
| `yoast-c2-custom-tax-under-cpt` | a `genre` term's `seo` nested under a `book`. | identical | identical |
| `yoast-d1-seo-before-model-fields` | a post's `seo` before `databaseId`/`title`. | identical | `data.post.seo.schema.raw`: "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... → "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... |
| `yoast-d2-seo-after-model-fields` | the same with `seo` after them. | identical | `data.post.seo.schema.raw`: "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... → "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... |
| `yoast-d3-root-seo-before-node` | root `seo.meta.author` before a post. | identical | identical |
| `yoast-e1-root-seo-after-post-authordata` | root `seo.meta.author` after a post. | identical | identical |
| `yoast-e2-root-seo-after-other-cpt` | root book archive titles after a `movie`. | identical | identical |
| `yoast-e3-root-archive-schema-after-post` | root book archive `schema`/`fullHead` after a post with a featured image. | identical | identical |
| `yoast-e4-root-archive-after-front-page` | root book archive after the static front page. | identical | identical |
| `yoast-e5-root-after-mediaitem` | root book archive `fullHead` and a root `genres` list's `canonical`/`opengraphUrl` after a media item. | `data.genres.nodes[0].seo.canonical`: "http://localhost:8888/graphql/" → "http://localhost:8888/genre/extmx-fantasy/"<br>`data.genres.nodes[0].seo.opengraphUrl`: "http://localhost:8888/graphql/" → "http://localhost:8888/genre/extmx-fantasy/" | `data.genres.nodes[0].seo.canonical`: "http://localhost:8888/graphql/" → "http://localhost:8888/genre/extmx-fantasy/"<br>`data.genres.nodes[0].seo.opengraphUrl`: "http://localhost:8888/graphql/" → "http://localhost:8888/genre/extmx-fantasy/" |
| `yoast-e6-pageinfo-seo-nodes-first` | `posts { nodes pageInfo { seo } }`, nodes first. | identical | identical |
| `yoast-e7-pageinfo-seo-first` | the same with `pageInfo` first. | identical | identical |
| `yoast-f-root-seo-before-post-schema` | root book archive before a post. | identical | identical |
| `yoast-g-post-fullhead-image-and-probe` | two posts' `fullHead`/`schema` (one with `title` before `seo`, one after), plus `debugLateObject`. | probe only | `data.one.seo.fullHead`: "<!-- This site is optimized with the Yoast SEO plugin v2... → "<!-- This site is optimized with the Yoast SEO plugin v2...<br>`data.one.seo.schema.raw`: "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ... → "{\"@context\":\"https://schema.org\",\"@graph\":[{\"@typ...<br>`data.two.seo.fullHead`: "<!-- This site is optimized with the Yoast SEO plugin v2... → "<!-- This site is optimized with the Yoast SEO plugin v2...<br>…and 1 more |
| `yoast-h-product-like-list-fullhead` | two posts and a book in a list, `fullHead`. | identical | `data.contentNodes.nodes[0].seo.fullHead`: "<!-- This site is optimized with the Yoast SEO plugin v2... → "<!-- This site is optimized with the Yoast SEO plugin v2...<br>`data.contentNodes.nodes[1].seo.fullHead`: "<!-- This site is optimized with the Yoast SEO plugin v2... → "<!-- This site is optimized with the Yoast SEO plugin v2...<br>`data.contentNodes.nodes[2].seo.fullHead`: "<!-- This site is optimized with the Yoast SEO plugin v2... → "<!-- This site is optimized with the Yoast SEO plugin v2... |
| `yoast-i-batch-root-seo-after-post` | batch, a post in operation 1, root `seo` (author, book archive) and `debugGlobals` in operation 2. | identical | identical |
| `yoast-j-batch-root-seo-after-movie` | batch, a movie, then root book archive titles. | identical | identical |
| `yoast-k-batch-root-seo-after-front-page` | batch, the front page, then the root book archive. | identical | identical |
| `yoast-l-batch-root-seo-after-mediaitem` | batch, a media item, then root book archive `fullHead`. | identical | identical |
