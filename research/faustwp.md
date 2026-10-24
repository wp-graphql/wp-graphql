# Faust.js WordPress plugin (faustwp) — node-scoped context risk matrix

## 1. Versions, sources, install

| Item | Value |
| --- | --- |
| Extension | FaustWP **1.9.0** (`faustwp.php:12`, `readme.txt` Stable tag 1.9.0) |
| Sources read | `dl/wporg_faustwp/faustwp` (wp.org zip) and `dl/wpengine_faustjs/faustjs-HEAD/plugins/faustwp` (GitHub HEAD). PHP under `includes/` is identical; GitHub adds only dev files plus `includes/updates/{check-for-updates,class-plugin-updater}.php` (self-updater, not in the wp.org build) |
| Faust client cross-check | `faustjs-HEAD/packages/faustwp-core/src/queries/seedQuery.ts:27-113`: the current client seed query uses only core fields (`isFrontPage`, `isPostsPage`, `template { templateName }`, ...) and works out templates on the client (`getTemplate.ts`). The PHP `templates` / `conditionalTags` fields below are legacy, so their real-world blast radius is mostly older `@faustjs/next` 0.x sites and custom queries |
| WP core used for downstream cites | WordPress 6.7.1 (`wp-includes/template.php`, `wp-includes/class-wp-query.php`) |
| Core WPGraphQL ref | origin/main `62b43019f` |

```bash
wp plugin install wp-graphql --activate
wp plugin install faustwp --activate
```

## 2. How it registers schema / hooks (all in `includes/graphql/callbacks.php` unless noted)

| What | Where |
| --- | --- |
| `UniformResourceIdentifiable.templates: [String]` | register `:19`, `:37-46`; resolver `templates_resolver` `:172-226` |
| `UniformResourceIdentifiable.conditionalTags: ConditionalTags` (deprecated) + `ConditionalTags` object type | register `:241`, `:254-287`; resolver `:299-307`; tag list `includes/graphql/functions.php:29-115` |
| `User.shouldShowFaustToolbar: Boolean` | `:78`, `:90-104` |
| `RootQuery.globalStylesheet(types:)` + `GlobalStylesheetTypesEnum` | `:106`, `:119-160` |
| Mutation `generateAuthorizationCode` | `:309`, `:315-374` |
| Filter `graphql_get_setting_section_field_value` (public introspection when the Faust secret header is sent) | `:48`, `:59-76` |
| `the_content` and `wpgraphql_content_blocks_resolver_content` → `content_replacement` | `includes/replacement/callbacks.php:27-28`, `:40-98` |
| `wp_calculate_image_srcset` | `replacement/callbacks.php:124`, `:134-175` |
| `preview_post_link` | `replacement/callbacks.php:177`, `:188-267` |
| `post_link` / `page_link` / `post_type_link` / `term_link` | `replacement/callbacks.php:269-271`, `:281-320`; `:322`, `:330-339` (both return early when `is_graphql_request()`, `:307`, `:333`) |
| `url_replacement()` for `graphql_request_results` | `replacement/graphql-callbacks.php:28-42`. The file is required (`faustwp.php:89`) but **nothing hooks it**: the only `graphql_request_results` string is the docblock at `:18`. Dead code in 1.9.0 |

Not present in 1.9.0 (checked by grep): no `isFrontPage`/`isPostsPage` overrides, no `asPreview` or preview fields, no `contentTypes`/`nodeByUri` filters, no menu or term fields (`includes/menus/callbacks.php:16-59` only registers or unregisters nav menu locations on `after_setup_theme`), no Deferred or dataloader use, no `setup_postdata`, `wp_reset_postdata`, `global $post` or `get_queried_object` in plugin code. The only global-state readers are the two `$wp_query`-driven resolvers.

## 3. Risk table

| `Type.field` | Resolver | Global(s) read (downstream) | Cat. | Why, under the NEW behavior |
| --- | --- | --- | --- | --- |
| `Post`/`Page`/`MediaItem`/CPT (ContentNode).`templates` | `graphql/callbacks.php:172-226` | `global $wp_query` (`:173`), then calls `$wp_query->parse_query()` with **no args** (`:188`), which re-parses the query vars already on the global (`class-wp-query.php:791-798`). After that, `is_*()` conditional tags (`:197`) and template getters read `get_queried_object()` / `get_queried_object_id()` (`template.php:549-555` single, `:467-477` page, `:659-660` attachment) and global `$post` through `get_page_template_slug()` with no arg (`template.php:469`) | **2** (fixed by new) | The resolver never reads `$root`, so on main nothing sets up this node before it runs. It reports the templates of whichever Post/Term/User last had a model field read. On concrete-type lists (`posts{nodes{templates title}}`), node N gets node N‑1's templates; `{title templates}` gets the right ones (title's `setup()` just ran). Interface-typed paths (`nodeByUri`, `contentNodes`) are right on main only by accident: `UniformResourceIdentifiable` resolveType reads `$node->post_type` (core `UniformResourceIdentifiable.php:91-93`), which fires `Post::setup()`. With the new behavior the node is set up before the field runs, so every position is correct |
| Term node (`Category`/`Tag`/custom).`templates` | same | same, via `get_category_template` / `get_taxonomy_template` reading `get_queried_object()` (`template.php:254-255`, `:346-347`) | **2** (fixed) | Same pattern. On main, `categories{nodes{templates name}}` is off by one node. New: correct |
| `User.templates` | same | `get_author_template` → `get_queried_object()` (`template.php:214-215`) | **2** (fixed) | Nested case `post{ author{ node{ templates } } }`: on main the user's `templates` runs before any User field is read, so it returns the **post's** single templates. New: User context stacks on top of the post, so it returns `author-*` |
| `ContentType.templates` (e.g. `nodeByUri(uri:"/")` when `show_on_front=posts`, or a CPT archive URI) | same | same | **1** | ContentType (PostType model) is not a Post/Term/User node, so new behavior gives it the request's ORIGINAL `$wp_query`. Faust's bare `parse_query()` turns that into `is_home` → `front-page, home, index`. On main, if any post was read earlier in the document, it returns that post's `single-*` templates. New is more correct for `/`. For a CPT archive URI it is wrong on both, since nothing ever parses `post_type=` into the global (NodeResolver uses a local `new WP_Query`, core `NodeResolver.php:194`). Pre-existing, not a regression |
| `Comment.templates` | same | same | **1** | Comment has no setup. New: at root (`comments{nodes{templates}}`) it sees the original state (home templates); under a post (`post{comments{nodes{templates}}}`) it sees the post (single templates). On main it sees whatever leaked. Semantically meaningless field; parity only |
| `*.conditionalTags` (deprecated) | `graphql/callbacks.php:299-307` | Calls every tag in `functions.php:29-115` with no args (`:303`): `$wp_query` flags, `is_front_page()` (`class-wp-query.php:4373`), `is_sticky()` → `get_the_ID()` → global `$post` (`post.php:2779-2783`), `is_page_template()` → `get_queried_object_id()` (`post-template.php:1812-1817`), `is_preview()`. **No** `parse_query()` call | **2** for Post/Term/User; **1** for ContentType/Comment | Same as `templates`, with one difference: it does not re-parse, so outside any node on the new behavior it sees the raw original `$wp_query` (all flags false: a /graphql request never runs the main query, and core sets `$wp_query->is_home = false` explicitly at `Router.php:239`). So `ContentType.conditionalTags.isHome` is **false** on new, while on main it reflects any leaked post. Under a node, new gives the right flags (`isSingle`, `isPage`, `isFrontPage` for the static front page, `isSticky` for the node) |
| side effect inside `templates` | `graphql/callbacks.php:202-204` | `remove_filter('the_content','prepend_attachment')` runs once `is_attachment` is true, and is **never re-added** | **1/5** | On main this can fire when a MediaItem *leaked* into an unrelated `templates` call (e.g. a ContentType after `featuredImage` was read), stripping core's attachment prepend from every later `MediaItem.content` in the request. New: it fires only when `templates` resolves on a real MediaItem node. Still request-global after that point (not restored by core) |
| side effect inside `templates` | `graphql/callbacks.php:188` | Mutates global `$wp_query` (`parse_query()` sets `->query`, `query_vars_changed`, recomputes flags) | **5** (low) | Inside a node this is idempotent: the vars are the ones the node's `setup()` just parsed, and core's restore puts things back when leaving. Outside a node (ContentType or Comment at root) it changes the ORIGINAL state from "no flags" to `is_home=true`, which any later root field reading `is_home()` would see. That only matters if core snapshots "original" lazily, after such a field. Verify that core captures the baseline before execution starts |
| `User.shouldShowFaustToolbar` | `graphql/callbacks.php:96-101` | `wp_get_current_user()` | SAFE | Ignores `$source` and reads the viewer, not the node. Node scoping does not change the current user |
| `RootQuery.globalStylesheet` | `graphql/callbacks.php:153-157` | `wp_get_global_stylesheet()`: no queried-object or `$post` reads (`global-styles-and-settings.php`, uses a cache when `$types` is empty) | SAFE | Root field, independent of node context |
| Mutation `generateAuthorizationCode` | `graphql/callbacks.php:343-371` | `wp_authenticate()` | SAFE | No post or query globals |
| `the_content` → `content_replacement` (runs inside `contentRendered`, and inside content-blocks rendering via `wpgraphql_content_blocks_resolver_content`) | `replacement/callbacks.php:40-98` | `faustwp_settings` option, `site_url()`, `wp_upload_dir()` | SAFE | Pure string rewrite of `href` and media URLs. It does run during `contentRendered`, but reads no per-node globals, so output is identical on main and new |
| `wp_calculate_image_srcset` → `image_source_srcset_replacement` | `replacement/callbacks.php:134-175` | settings + `site_url()` | SAFE | No node state |
| `preview_post_link` → `post_preview_link` | `replacement/callbacks.php:188-267` | Uses the `$post` **argument** (`:214`, `:223`, `:237`, `:247`) | SAFE | Explicit post. Gated on `enable_redirects` |
| `post_link`/`term_link` rewrites | `replacement/callbacks.php:281-339` | `$pagenow`, settings | SAFE | Return the original link during GraphQL requests (`:307`, `:333`) |

Pre-existing quirk, the same on main and new: for `contentNode(asPreview:true)`, core `Post::setup()` sets `queried_object` to the **revision** (`get_post($this->data->ID)`, core `Post.php` ~253) while it parses the parent's type and name. So `templates` gives `single-revision-*` for post previews and `page-{revisionId}` for page previews.

## 4. Fixtures

```bash
wp plugin install wp-graphql --activate
wp plugin install faustwp --activate
wp theme activate twentytwentyfive          # templates output is theme-dependent: the loop stops at the first template the theme has
wp option update faustwp_settings '{"frontend_uri":"http://localhost:3000","enable_rewrites":"1","enable_redirects":"1"}' --format=json
wp option update show_on_front posts

SITE=$(wp option get siteurl)
P1=$(wp post create --post_title='Alpha' --post_name=alpha --post_status=publish --post_content="<a href=\"$SITE/beta/\">beta</a> <img src=\"$SITE/wp-content/uploads/x.png\">" --porcelain)
P2=$(wp post create --post_title='Beta'  --post_name=beta  --post_status=publish --porcelain)
P3=$(wp post create --post_title='Gamma' --post_name=gamma --post_status=publish --porcelain)
wp option update sticky_posts "[$P2]" --format=json   # makes conditionalTags.isSticky node-dependent
PAGE=$(wp post create --post_type=page --post_title='About' --post_name=about --post_status=publish --porcelain)
CAT1=$(wp term create category News --slug=news --porcelain)
CAT2=$(wp term create category Events --slug=events --porcelain)
CAT3=$(wp term create category Guides --slug=guides --porcelain)
wp post term set "$P1" category news events guides
U=$(wp user create fauthor fauthor@example.com --role=author --porcelain)
wp post update "$P1" --post_author="$U"
MEDIA=$(wp media import https://s.w.org/style/images/about/WordPress-logotype-wmark.png --post_id="$P1" --featured_image --porcelain)
echo "{\"a\":$P1,\"b\":$P2,\"c\":$P3,\"page\":$PAGE,\"media\":$MEDIA,\"cat\":$CAT1}"
```

Assert on the **first element prefix**, which does not depend on the theme: `single-post-<slug>`, `page-<slug>`, `category-<slug>`, `author-<nicename>`, `front-page`, `attachment`/MIME-based.

Variables used below:

```json
{ "a": 0, "b": 0, "c": 0, "page": 0, "media": 0, "cat": 0 }
```

## 5. Queries

(a) Single concrete node, `templates` first (no model field read before it on main).
```graphql
query A($a: ID!) { post(id: $a, idType: DATABASE_ID) { templates databaseId } }
```
Main: whatever the original state gives (`front-page, home, index`) or a leaked node. New: `["single-post-alpha","single-post","single","index"]`.

(b) List of 3 differing nodes, `templates` before `title`: detects off-by-one bleed.
```graphql
query B { posts(first: 3, where: { orderby: { field: TITLE, order: ASC } }) { nodes { templates title } } }
```
Main: node 2's `templates` starts `single-post-alpha`, node 3's `single-post-beta` (each one is the previous node's). New: each node's own slug.

(b2) Same for terms and the deprecated tag field.
```graphql
query B2 { categories(where: { slug: ["news","events","guides"] }) { nodes { templates conditionalTags { isCategory isSingle } name } } }
```
Main: first node shows home or leaked flags, later nodes are off by one. New: each starts `category-<own slug>`, `isCategory:true`, `isSingle:false`.

(c) Nested under another node (User under Post), then the parent's field after the nested node.
```graphql
query C($a: ID!) { post(id: $a, idType: DATABASE_ID) { title author { node { templates name } } templates } }
```
Main: `author.node.templates` = `single-post-alpha...` (the post's). New: `author-fauthor...`. Then `post.templates` = `single-post-alpha...` on both (new: restored after leaving the User).

(d) Same selection, field before vs after model fields, with a different node resolved first.
```graphql
query D($a: ID!, $page: ID!) {
  post(id: $a, idType: DATABASE_ID) { title }
  before: page(id: $page, idType: DATABASE_ID) { templates title }
  after:  page(id: $page, idType: DATABASE_ID) { title templates }
}
```
Main: `before.templates` = `single-post-alpha...` (leaked from post A), `after.templates` = `page-about...`. New: both `page-about...`.

(e) #4572 shape: a root-level, non-model URI node after a post.
```graphql
query E($a: ID!) {
  post(id: $a, idType: DATABASE_ID) { title featuredImage { node { sourceUrl } } }
  nodeByUri(uri: "/") { __typename ... on ContentType { templates conditionalTags { isHome isFrontPage isSingle isAttachment } } }
}
```
Main: ContentType `templates` reflects the last leaked node (the attachment or post A), e.g. `attachment...`/`single-post-alpha...`, and `isAttachment`/`isSingle` true. New: `templates` = `["front-page","home","index"]`. `conditionalTags` (no re-parse) are all **false** on new, because the original `$wp_query` was never parsed. If `templates` is selected before `conditionalTags`, Faust's `parse_query()` (`callbacks.php:188`) first turns them into `isHome:true,isFrontPage:true`: an ordering effect inside the same node on new.

(e2) prepend_attachment side effect (category 1/5).
```graphql
query E2($a: ID!, $media: ID!) {
  post(id: $a, idType: DATABASE_ID) { featuredImage { node { databaseId } } }
  nodeByUri(uri: "/") { ... on ContentType { templates } }
  mediaItem(id: $media, idType: DATABASE_ID) { content }
}
```
Main: the leaked attachment makes ContentType `templates` take the `is_attachment` branch and remove `prepend_attachment`, so `mediaItem.content` has no attachment markup. New: ContentType sees the original state, the filter stays, and `content` includes the prepended attachment `<p class="attachment">` markup.

(f) Content replacement parity (SAFE control).
```graphql
query F($a: ID!) { post(id: $a, idType: DATABASE_ID) { content } }
```
Both: `href="http://localhost:3000/beta/"`, and the `img src` rewritten to `http://localhost:3000/wp-content/uploads/x.png`. Must be identical.

(g) Mutation (SAFE control, no global reads).
```graphql
mutation G { generateAuthorizationCode(input: { username: "fauthor", password: "wrong" }) { code error } }
```
Both: `code:null`, same `error` string.
