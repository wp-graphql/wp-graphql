# WPGraphQL for Yoast SEO (`add-wpgraphql-seo`) — node-scoped context risk matrix

## 1. Versions / sources examined

| Component | Source | Version |
|---|---|---|
| Extension (GitHub) | `dl/ashhitch_wp-graphql-yoast-seo/wp-graphql-yoast-seo-HEAD` (github.com/ashhitch/wp-graphql-yoast-seo, HEAD snapshot) | 5.1.0 (`wp-graphql-yoast-seo.php:11`, `readme.txt:7`) |
| Extension (wp.org) | `dl/wporg_add-wpgraphql-seo/add-wpgraphql-seo` (slug `add-wpgraphql-seo`) | 5.1.0, Stable tag 5.1.0 |
| Base plugin | `dl/wporg_wordpress-seo/wordpress-seo` (wp.org `wordpress-seo` latest-stable, downloaded 2026-10-23) | Yoast SEO 28.7 (`wp-seo.php:11`) |
| Core | WPGraphQL `origin/main` @ `c0dc6a95a` | — |

**GitHub HEAD vs wp.org difference (only one):** `includes/resolvers/post-type.php:31-35` exists only in HEAD. It routes the static front page to `YoastSEO()->meta->for_home_page()` instead of `for_post()`. The wp.org copy always calls `for_post($post->ID)`. Both versions read `$post->ID` before calling Yoast, so neither depends more on global context than the other. They differ only in which title template and page type the front page gets.

Install:
```bash
wp plugin install wp-graphql --activate
wp plugin install wordpress-seo --activate          # Yoast SEO 28.7
wp plugin install add-wpgraphql-seo --activate      # wp.org build of this extension (5.1.0)
# GitHub HEAD instead (branch name unverified):
# wp plugin install https://github.com/ashhitch/wp-graphql-yoast-seo/archive/refs/heads/master.zip --activate
```

### How it talks to Yoast (important framing)
- The extension **never** calls `wpseo_head`, `ob_start`, `for_current_page()`, `query_posts`, `setup_postdata` or `switch_to_blog` (grep of `includes/` and the main file). Every call is an explicit-object Surfaces API call: `for_post($id)` (post-type.php:37), `for_home_page()` (post-type.php:35, HEAD only), `for_term($id)` (post-type.php:30, taxonomy.php:40), `for_author($id)` (user.php:23), and `for_post_type_archive($type)` (functions.php:356, root-query.php:213). `fullHead`/`head` come from `Meta::get_head()` (functions.php:200-228 → Yoast `src/surfaces/values/meta.php:126`), which renders the presenters against the **explicit** `Meta_Tags_Context`, not the current page.
- The global reads happen **inside Yoast**, in helpers the explicit context still consults:
  - `Current_Page_Helper` (`src/helpers/current-page-helper.php:38-60` `get_page_type()`, `:79-81` `get_simple_page_id()` → `is_singular()`/`get_the_ID()`, `:202-213` `is_home_static_page()`, `:382` `is_front_page()`) reads `$GLOBALS['wp_the_query']` through `WP_Query_Wrapper::get_main_query()` (`src/wrappers/wp-query-wrapper.php:26-27`). That is the same object as `$wp_query`, which `Post::setup()`/`Term::setup()`/`User::setup()` mutate.
  - `Meta_Tags_Context::generate_main_image_id()` (`src/context/meta-tags-context.php:634-669`) uses `switch (true) { case is_singular(): get_singular_post_image($this->id) ... case is_author()/is_tax()/is_category()/...: $GLOBALS['wp_query']->posts[0] }`. `get_singular_post_image()` (`:713-725`) calls `has_post_thumbnail($id)`. For an archive indexable `$this->id` is `null` (`:249-251`), so this falls back to the **global `$post`**. `generate_main_image_url()` (`:608-627`) also checks `is_singular()`.
  - `Indexable_Presentation::generate_permalink()` (`src/presentations/indexable-presentation.php:235-250`): `is_date()` → date permalink, `is_attachment()` → `home_url($wp->request)`. In a `/graphql` request that resolves to `https://site/graphql/`. This feeds canonical, og:url and schema `@id`s.
  - `Open_Graph\Image_Presenter::present()` (`src/presenters/open-graph/image-presenter.php:47-49`): if `is_attachment()`, og:image becomes `home_url($wp->request)`.
  - `Breadcrumbs_Generator::generate()` (`src/generators/breadcrumbs-generator.php:97,140` `get_page_type()`; `:371` `is_home()||is_search()||!is_singular('post')`; `:404-407` `is_simple_page()`/`is_paged()`).
  - Schema: `schema-generator.php:220` and `schema/breadcrumb.php:43,47,75` (`is_home_static_page`/`is_paged`/`is_static_posts_page`), `schema/webpage.php:41,45` (`is_search()`, `is_front_page()` → adds `about`), `schema/webpage.php:108-113` (image from `has_image`/`main_image_url`).
  - `WPSEO_Replace_Vars` (`inc/class-wpseo-replace-vars.php`). `%%pt_plural%%`/`%%pt_single%%` (`:692-707`) prefer `$wp_query->query_vars['post_type']`, then `args->post_type`, then `$wp_query->get_queried_object()`. `%%name%%` (`:986-990`) → `retrieve_userid()` (`:1131-1133`) → `get_query_var('author')`, then `get_the_author_meta('display_name', 0)`, which reads the **global `$authordata`**. `%%page%%` (`:643-680`) reads `is_singular()`, `get_query_var('page'|'paged')` and global `$post->post_content`.
- **Amplifier: Yoast memoizes per request.** `Meta_Tags_Context_Memoizer::get()` caches the context per indexable id (`src/memoizers/meta-tags-context-memoizer.php:134-157`). `Abstract_Presentation::__get()` caches every generated property (`src/presentations/abstract-presentation.php:60-70`). Whatever global state is active the **first** time an indexable's permalink, schema, breadcrumbs or main image is generated sticks for the rest of the request, batch operations included.

### Why posts are "accidentally right" today
`wp_gql_seo_get_post_type_graphql_fields()` reads `$post->ID` (post-type.php:33/37) before any Yoast call. On main this runs `Post::setup()` for **that** post (Model.php `prepare_field` → `setup()`), and `Post::tear_down()` is a no-op. Yoast then sees `is_singular`, `queried_object` and `$post` for the right post. Term (`$term->term_id`, taxonomy.php:37) and User (`$user->userId`, user.php:23) work the same way through `Term::setup()`/`User::setup()`. Core `Model::__get()` memoizes each field, so the setup happens only on the first `->ID` read on that model instance (`AbstractConnectionResolver.php:359` also reads `$source->ID`). Dataloaders build a fresh model per load, so in practice the `seo` resolver almost always triggers its own setup.

### Reconciled against the implemented `NodeContext` (working tree `src/Model/NodeContext.php`, `src/Utils/InstrumentSchema.php:109`, `src/Model/Model.php:457`)
- **Which models are nodes.** A node is any `Model` subclass that overrides `setup()`, detected by reflection (NodeContext.php:268-277). In core today that means Post, Term and User. Nothing in this extension subclasses a model. WooGraphQL `Product` inherits `Post::setup()`, so it counts as a node.
- **Every `seo` field sits under its node.** For `post { seo { ... } }`, `enter()` records the Post as owner of path `post` (NodeContext.php:120-122), because the Post is the source of `seo`. The array-valued children (`PostTypeSEO.*`, `SEOHead.*`, the `opengraphImage` closure fields) inherit that chain (:127-135), so the whole `seo` subtree runs in the post's context. Root `seo` and all its nested arrays have no owner, so `enter()` pops everything and they run in the request's original state (:137-149). A root `posts { pageInfo { seo } }` likewise has no owner on the `pageInfo` path, which confirms the E6/E7 expectation (same result for both orders).
- **The `->ID` read no longer triggers setup.** Reading `$post->ID`, `$term->term_id` or `$user->userId` inside the node goes through `NodeContext::run_as` (Model.php:457), which is a no-op when the model is already active (NodeContext.php:172-174). So the `seo` resolvers now depend on `enter()` having pushed the node, not on their own model read. Inside the node the output is unchanged (rows marked SAFE stay SAFE).
- **Setup runs once per node entry (point 2 of the correction).** No Yoast API this extension calls mutates `$wp_query` or the restored globals. The `for_*` surfaces, `Meta::get_head()` and `Title_Presenter::get()` (add/remove filter around `wp_get_document_title()`, title-presenter.php:38-43) only read. The one in-resolver mutation is `wp_reset_postdata()` at taxonomy.php:117. It persists for the term node's later sibling fields until the node is popped, and `restore_global_state` puts `$post` and `$wp_query->post` back on exit (NodeContext.php:296, :309-324). In HTTP `/graphql`, `$wp_query->post` is null, so the call stays a no-op (see the category 5 row).
- **Deferred callbacks (point 4).** No `seo` resolver returns a Deferred whose callback calls Yoast. Every Yoast call happens synchronously inside the resolver, and all context-dependent values (head, schema, breadcrumbs, images) are computed eagerly there. `PostTypeSEO.opengraphImage`/`twitterImage` (post-type.php:68-72, :79-89) are field-value closures run by the default resolver *as a field*, so `enter()` runs first and re-applies the post's context. Their `$meta->...` reads hit Yoast's memoized presentation. The `load_deferred(...)` they return (and those at taxonomy.php:77-88, user.php:44-50, root-query.php:97-174, functions.php:325) only loads a MediaItem by ID, and its fields get their own node via `enter()`. Category 3 is real in core but has **no Yoast exposure here**.
- **Yoast "current page" memo (point 6).** The extension never calls `for_current_page()`. `Meta_Tags_Context_Memoizer::for_current_page()` caches **once per request** under the fixed key `'current_page'` (meta-tags-context-memoizer.php:87-122). It is not keyed by object, and it is built from the main query after a temporary `wp_reset_query()`. Under node scoping, any *third-party* code that calls `YoastSEO()->meta->for_current_page()` (or `Front_End_Integration::get_presenters()` without a context, front-end-integration.php:504) from inside a node would freeze that first node as "current" for the rest of the request. Before, it froze whichever node had leaked. Not reachable from this extension's own resolvers. The per-indexable memo it does use (`get()`, :134-157) is keyed by indexable id, so node scoping only changes the state seen the first time each indexable is built. For node-owned indexables that is now always the right node.

## 2. Field registration (all in `graphql_register_types`, loaded on `graphql_init`: `wp-graphql-yoast-seo.php:39-51`)

| Field | Registered at | Resolver |
|---|---|---|
| `ContentNode.seo: PostTypeSEO` | `includes/resolvers/post-type.php:118-124` | `wp_gql_seo_get_post_type_graphql_fields` post-type.php:23-114 |
| `NodeWithTitle.seo: PostTypeSEO` | post-type.php:126-132 | same |
| `Product.seo: PostTypeSEO` (only if `class_exists('WooCommerce')`) | post-type.php:135-142 | same. WooGraphQL `Product extends WC_Post extends WPGraphQL\Model\Post`, so it is a Post node |
| `{Taxonomy graphql_single_name}.seo: TaxonomySEO` (every allowed taxonomy) | `includes/resolvers/taxonomy.php:29-121` | inline closure taxonomy.php:36-120 |
| `{PostType}To{Tax}ConnectionEdge.isPrimary: Boolean` (hierarchical taxonomies) | taxonomy.php:143-159 | taxonomy.php:150-158 |
| `User.seo: SEOUser` | `includes/resolvers/user.php:18-85` | user.php:21-84 |
| `RootQuery.seo: SEOConfig` | `includes/resolvers/root-query.php:18-193` | root-query.php:21-192 (calls `wp_gql_seo_build_content_type_data` functions.php:337-394 and `wp_gql_seo_build_taxonomy_data` functions.php:404-435) |
| `WP{PostTypeSingle}Info.seo: SEOPostTypePageInfo` | root-query.php:196-225 | root-query.php:212-221 |
| Object types (PostTypeSEO, TaxonomySEO, SEOConfig, SEOContentTypeArchive, SEOGlobalMetaAuthor, SEOSocialArchive, ...) | `includes/schema/types.php:25-513` | no per-field resolvers. Values are eager arrays, except the two closures at post-type.php:68 and :79 |

Notes:
- The `WP{Single}Info` registration only lands where that type exists. Core registers the `WPPageInfo` interface (`src/Type/InterfaceType/PageInfo.php:17`) but no `WPPostInfo`. The only registration that sticks is the one for the `page` post type, which puts `seo` on the `WPPageInfo` interface, so every connection's `pageInfo` resolves the **`page`** archive schema (the README documents `posts { pageInfo { seo { schema { raw } } } }`). Unverified at runtime.
- There is no `seo` field on MenuItem or ContentType. Content-type SEO only exists under `RootQuery.seo.contentTypes`.
- Everything is computed **eagerly inside the parent resolver**: selecting just `seo { title }` still runs `get_head()` twice (functions.php:202 and :211 via post-type.php:96-97). For root `seo`, every allowed post type's archive context, head and schema is generated on each request (functions.php:346-391).

## 3. Risky fields

"main" is today's behavior (leaky). "new" is node-scoped context.

| `Type.field` | Resolver file:line | Global(s) read (Yoast file:line) | Cat. | Why, specifically under the NEW behavior |
|---|---|---|---|---|
| `RootQuery.seo` → `SEOConfig.meta.author.title` and `.meta.author.social.title` | root-query.php:50, :56 → functions.php:323 → `wpseo_replace_vars()` (functions.php:39) | Templates `title-author-wpseo` = `%%name%%, Author at %%sitename%% %%page%%` (`inc/options/class-wpseo-option-titles.php:272`) and `social-title-author-wpseo` = `%%name%%` (`:41`). `%%name%%` → `retrieve_userid()` (replace-vars.php:1133) `get_query_var('author')` = '' → `get_the_author_meta('display_name', 0)` → **global `$authordata`** | **1** | On main, `Post::setup()`→`setup_postdata()` sets `$authordata` to the post's author and never restores it. So `{ post{title} seo{meta{author{social{title}}}} }` returns the leaked post author's display name, and a root `seo` placed **before** the post returns "". New: root runs in the original state, so it is always "" or ", Author at Site". The value changes, and the new value is the correct one. The `has_premium` check at functions.php:312 is satisfied by free-plugin defaults, so `social` is populated without Premium |
| `SEOConfig.contentTypes.{cpt}.archive.title` | functions.php:356, :373 (`$meta->title`) | Yoast `Title_Presenter::get_title()` → replace vars with source `['post_type'=>X]` (`indexable-post-type-archive-presentation.php:69-71`). `%%pt_plural%%` prefers **`$wp_query->query_vars['post_type']`** (replace-vars.php:698-699) | **1** | `Post::setup()` for a CPT node does `parse_query([... 'post_type'=>$type])` (Post.php setup). If the leaked node is a *different* CPT, every other CPT's archive title says "Movies Archive" (for example). New: root sees no `post_type` query var, so the title is correct ("Books Archive"). Memoized per archive indexable (first computation wins) |
| `SEOConfig.contentTypes.{cpt}.archive.social.title` | functions.php:388 → :323 → replace vars with args = list of type names (functions.php:31-39, no `post_type` key) | `%%pt_plural%%`: `query_vars['post_type']` (:698), then **`$wp_query->get_queried_object()`** (:706) | **1** | Main: after any post node, this returns that node's type plural ("Posts Archive" after `post`, "Movies Archive" after `movie`); before any node it returns "Archive". New: always "Archive" at root. The output changes. Both are "wrong" because the extension never passes the archive's post type. Expect bug reports if users relied on the leak |
| `SEOConfig.contentTypes.{type}.schema.raw`, `.archive.fullHead`, `.archive.head`, `.archive.metaRobots*` | functions.php:356, :364-368, :386-387 | `for_post_type_archive($type)` (meta-surface.php:147-157, explicit). Inside: main image `is_singular()` → `has_post_thumbnail(null)` → **global `$post`** (meta-tags-context.php:642-643, 713-714). Page type `Static_Home_Page` via `is_front_page()`/`is_page(page_on_front)` (current-page-helper.php:202-213) changes breadcrumbs (breadcrumbs-generator.php:97, 140), BreadcrumbList (schema/breadcrumb.php:75, schema-generator.php:220) and WebPage `about` (schema/webpage.php:45). `is_attachment()` → canonical/og:url `= home_url('graphql')/` (indexable-presentation.php:244-246) and og:image (open-graph/image-presenter.php:47-49) | **1** | Main: root `seo` after a post leaks that post's featured image into **every** archive's `primaryImageOfPage`/`thumbnailUrl`. After the static front page, archive schema gains `about` and loses the Home > … trail. After a MediaItem, archive canonical/og:url/og:image become `https://site/graphql/`. All of this is memoized for the request. New: root runs in the original state (no flags, `$post` null), so there is no image and the trail and canonical are correct. The output changes, and the change is a fix. **Highest-impact item**: headless sites render archive `<head>` from exactly this field |
| `WPPageInfo.seo.schema.raw` (any connection's `pageInfo { seo }`) | root-query.php:212-221 | same as the row above (`for_post_type_archive('page')`) | **1 / 2** | `pageInfo` is not a node. Main: `posts{ nodes{title} pageInfo{seo{schema{raw}}} }` sees the **last** node's leaked `$post`/flags (featured image appears). With `pageInfo` selected **before** `nodes` it may not, so selection order changes the output. New: a root connection gives the original state. A connection nested under a node gives that node's context, so a nested `pageInfo.seo` now consistently shows the *parent* post's image (still semantically wrong, but deterministic). Unverified at runtime (see the registration note) |
| `{CustomTaxonomy}.seo` → `TaxonomySEO.canonical`, `.opengraphUrl`, `.fullHead`, `.head`, `.schema.raw` (taxonomies other than category/post_tag) | taxonomy.php:37-40, :65, :91-97, :112 | `for_term()` explicit, but `Term::setup()` only calls `parse_query` for `category`/`post_tag`. For custom taxonomies the **previous** `is_singular`/`is_attachment` flags survive. Then `is_attachment()` → canonical/og:url `/graphql/` (indexable-presentation.php:244-246) and `is_singular()` → `get_singular_post_image($term_id)` treats the **term id as a post id** (meta-tags-context.php:642-643, 713-714) | **2** | Main: `{ mediaItem{title} genres{nodes{seo{canonical}}} }` gives the genre canonical as `https://site/graphql/`. After a post, genre schema may carry a bogus primary image (the post whose ID equals the term_id). New: a root `genres` connection runs from the original state and is fixed. Nested `book{ genres{ nodes{ seo } } }` **stays wrong** because the term scope stacks on the book scope and `Term::setup` does not reset flags for custom taxonomies. This is a core `Term::setup` gap, not an extension bug. Same on main and new for the nested shape |
| `{Taxonomy}.seo` (all taxonomies), side effect | taxonomy.php:117 `wp_reset_postdata()` | `$wp_query->reset_postdata()` restores `$GLOBALS['post'] = $wp_query->post` and runs `setup_postdata` | **5** | In an HTTP `/graphql` request `$wp_query->post` is null, so this is a no-op on both main and new. If WPGraphQL runs in-process (`graphql()` during a theme render where the main query ran), this call overwrites the term scope's `$post` (Term::setup sets it to null) with the main query's post. NodeContext sets up the node once per entry and does not re-run setup per field, so the overwrite **persists for the term node's later sibling fields** until the node is popped. `restore_global_state` then puts `$post` and `$wp_query->post` back (NodeContext.php:309-324), so nothing leaks outside the node. Low impact |
| `PostTypeSEO.opengraphImage`, `.twitterImage` | post-type.php:68-72, :79-89 (closures run later by the default field resolver) → `load_deferred` | `$meta->open_graph_images` (memoized presentation, no `Images_Presenter` class → `presentation->open_graph_images`), `$meta->twitter_image` (`Twitter\Image_Presenter::get`, memoized presentation) | 3 → **SAFE** | The values were generated eagerly by `get_head()` inside the `seo` resolver, and `load_deferred` only loads a MediaItem by ID, which gets its own node scope. Same for `TaxonomySEO.opengraphImage/twitterImage` (taxonomy.php:77-88), `SEOUser.opengraphImage/twitterImage` (user.php:44-50) and root logos/default images (root-query.php:97-174, functions.php:325) |
| `ContentNode.seo` / `NodeWithTitle.seo` / `Product.seo` → all `PostTypeSEO` fields (title, metaDesc, fullHead, head, canonical, opengraph*, twitter*, breadcrumbs, schema{raw,pageType,articleType}, readingTime, cornerstone, robots, analysis, …) | post-type.php:23-114 | `$post->ID` (model, triggers `Post::setup`), then `for_post`/`for_home_page`. Yoast reads `is_singular`, `$post`, `is_front_page`, `is_attachment`, `get_query_var('page')` as listed above | **SAFE** | Main gets the right post because the `->ID` read sets it up immediately before. New runs it under the node anyway. Same output; nested (`user{posts{nodes{seo}}}`) and list shapes are covered. Pre-existing, same on both: `mediaItem{ seo{ canonical opengraphUrl } }` yields `https://site/graphql/` (indexable-presentation.php:244-246, `is_attachment()` true under the attachment's own setup). Unverified |
| `User.seo` (`SEOUser.*` incl. fullHead, schema.raw) | user.php:21-84 | `$user->userId` (model → `User::setup`: `parse_query(author_name)`, `is_author=true`). `for_author()` explicit. Main image `is_author` → `$GLOBALS['wp_query']->posts[0]` (meta-tags-context.php:645-658). Social fields use an explicit `get_the_author_meta(..., $user->userId)` | **SAFE** | Same on main and new. `wp_query->posts` is empty in HTTP `/graphql` |
| `SEOConfig.meta.homepage/date/search/notFound`, `taxonomyArchives.*`, `contentTypes.*.{title,metaDesc}`, webmaster, social, breadcrumbs, schema, redirects, openGraph, advanced | root-query.php:42-191, functions.php:359-363, 422-430 | Option reads only. Replace vars whose globals are inert here: `%%page%%` (Post::setup sets `page=''` → 1), `%%term_title%%`/`%%title%%`/`%%date%%` (args, empty) | **SAFE** | No observable dependency on leaked state with default templates |
| `{Post}To{Tax}ConnectionEdge.isPrimary` | taxonomy.php:150-158 | `$item['source']->ID` (model read, triggers Post setup), then `WPSEO_Primary_Term` (`inc/class-wpseo-primary-term.php:43-58`, explicit `get_post_meta`) | **SAFE** | Explicit IDs only. The edge sits under its post node |

Category 6 (third-party models): none in this extension. `Product.seo` relies on WooGraphQL's `Product` model, which extends `WPGraphQL\Model\Post` (`includes/model/class-wc-post.php:24`, `class-product.php:98`, no `setup()` override), so the new scoping recognizes it as a Post node. Third-party `wpseo_schema_*`, `wpseo_frontend_presenters` and `pre_get_document_title` callbacks that read `is_singular()`/`get_queried_object()` run inside these resolvers. Under the new behavior they see the node (or the original state at root) instead of the last leaked node. Yoast's WooCommerce integration (`src/integrations/third-party/woocommerce.php:107` `wpseo_frontend_page_type_simple_page_id`, `is_shop_page()`) is one example. Unverified.

## 4. Fixtures (wp-cli)

```bash
# Two CPTs with archives + a non-category hierarchical taxonomy, and let Yoast persist indexables in non-production envs.
MU=$(wp eval 'echo WPMU_PLUGIN_DIR;'); mkdir -p "$MU"
cat > "$MU/yoast-ctx-fixtures.php" <<'PHP'
<?php
add_action( 'init', function () {
	foreach ( [ 'book' => [ 'Books', 'Book' ], 'movie' => [ 'Movies', 'Movie' ] ] as $pt => $l ) {
		register_post_type( $pt, [ 'public' => true, 'has_archive' => true, 'labels' => [ 'name' => $l[0], 'singular_name' => $l[1] ],
			'supports' => [ 'title', 'editor', 'thumbnail', 'author' ], 'show_in_graphql' => true,
			'graphql_single_name' => $pt, 'graphql_plural_name' => $pt . 's' ] );
	}
	register_taxonomy( 'genre', [ 'post', 'book' ], [ 'public' => true, 'hierarchical' => true, 'label' => 'Genres',
		'show_in_graphql' => true, 'graphql_single_name' => 'genre', 'graphql_plural_name' => 'genres' ] );
}, 5 );
add_filter( 'Yoast\WP\SEO\should_index_indexables', '__return_true' );
PHP
wp rewrite structure '/%postname%/' --hard

ALICE=$(wp user create alice alice@example.com --role=author --display_name="Alice Author" --porcelain)
BOB=$(wp user create bob bob@example.com --role=author --display_name="Bob Builder" --porcelain)

IMG_SRC="$(wp eval 'echo ABSPATH;')wp-admin/images/w-logo-blue.png"
IMG1=$(wp media import "$IMG_SRC" --title="Image One" --porcelain)
IMG2=$(wp media import "$IMG_SRC" --title="Image Two" --porcelain)

CAT=$(wp term create category "News" --porcelain)
CAT2=$(wp term create category "Sports" --porcelain)
G1=$(wp term create genre "Fantasy" --porcelain)

P1=$(wp post create --post_type=post --post_title="Alice Post" --post_author=$ALICE --post_status=publish --porcelain)
P2=$(wp post create --post_type=post --post_title="Bob Post" --post_author=$BOB --post_status=publish --porcelain)
wp post meta set $P1 _thumbnail_id $IMG1
wp post meta set $P2 _thumbnail_id $IMG2
wp post term set $P1 category $CAT $CAT2 --by=id
wp post meta set $P1 _yoast_wpseo_primary_category $CAT2       # isPrimary => Sports

BOOK1=$(wp post create --post_type=book --post_title="Book One" --post_author=$ALICE --post_status=publish --porcelain)
MOVIE1=$(wp post create --post_type=movie --post_title="Movie One" --post_author=$BOB --post_status=publish --porcelain)
wp post meta set $BOOK1 _thumbnail_id $IMG2
wp post term set $BOOK1 genre $G1 --by=id

FRONT=$(wp post create --post_type=page --post_title="Home Page" --post_status=publish --porcelain)
BLOG=$(wp post create --post_type=page --post_title="Blog" --post_status=publish --porcelain)
PARENT=$(wp post create --post_type=page --post_title="Parent" --post_status=publish --porcelain)
CHILD=$(wp post create --post_type=page --post_title="Child" --post_parent=$PARENT --post_status=publish --porcelain)
wp option update show_on_front page
wp option update page_on_front $FRONT
wp option update page_for_posts $BLOG

# Yoast settings: breadcrumbs on, blog crumb on (affects breadcrumbs-generator.php:371 path)
wp option patch update wpseo_titles breadcrumbs-enable true --format=json
wp option patch update wpseo_titles breadcrumbs-display-blog-page true --format=json
wp yoast index --reindex     # build indexables (src/commands/index-command.php)

echo "{\"p1\":\"$P1\",\"p2\":\"$P2\",\"book1\":\"$BOOK1\",\"movie1\":\"$MOVIE1\",\"front\":\"$FRONT\",\"child\":\"$CHILD\",\"img1\":\"$IMG1\",\"g1\":\"$G1\",\"alice\":\"$ALICE\",\"ids\":[\"$P1\",\"$P2\",\"$BOOK1\",\"$CHILD\",\"$FRONT\"]}"
```

Run every query below as its **own HTTP request**. Yoast memoizes contexts per request, so combining shapes in one request or batch masks the difference.

## 5. Queries

Variables (substitute the IDs echoed above):
```json
{ "p1": "11", "p2": "12", "book1": "13", "movie1": "14", "front": "15", "child": "18", "img1": "8", "g1": "5", "alice": "2", "ids": ["11", "12", "13", "18", "15"] }
```

(a) Single node: post SEO, including head, schema and breadcrumbs. **Expect identical output on main and new.** canonical = P1 permalink, schema `thumbnailUrl` = IMG1, breadcrumbs Home > Blog > Alice Post.
```graphql
query A($p1: ID!) {
  post(id: $p1, idType: DATABASE_ID) {
    seo { title canonical opengraphUrl fullHead breadcrumbs { text url } schema { raw pageType articleType } opengraphImage { databaseId } readingTime cornerstone }
  }
}
```

(b) List of five differing nodes (two posts with different authors and images, a CPT, a child page, the static front page). Detects wrong-post bleed. **Expect identical output on main and new.** Each node's `canonical`/`thumbnailUrl` is its own. The front page under the GitHub HEAD build uses `for_home_page` (title template differs from the wp.org build; context-independent).
```graphql
query B($ids: [ID]) {
  contentNodes(first: 10, where: { in: $ids, contentTypes: [POST, PAGE, BOOK] }) {
    nodes { databaseId uri seo { title canonical opengraphAuthor breadcrumbs { text } schema { raw } } }
  }
}
```

(c1) Nested node: user, then the user's posts, then each post's SEO, plus author SEO under a post. **Expect identical output on main and new.** SEOUser.schema is a ProfilePage for Alice. Each nested post's canonical and image is its own. The `isPrimary` edge is true only for Sports.
```graphql
query C1($alice: ID!, $p1: ID!) {
  user(id: $alice, idType: DATABASE_ID) {
    seo { title canonical schema { raw } }
    posts { nodes { databaseId seo { canonical schema { raw } } } }
  }
  post(id: $p1, idType: DATABASE_ID) {
    author { node { seo { title } } }
    categories { edges { isPrimary node { name } } }
  }
}
```

(c2) Custom-taxonomy term nested under a CPT node, a known gap of `Term::setup`. **Expect identical output on main and new** (both have `is_singular` from book1 still set). Inspect `schema.raw` for a bogus `primaryImageOfPage` (post id == term id). Documents that the new stack does not fix custom-tax terms.
```graphql
query C2($book1: ID!) {
  book(id: $book1, idType: DATABASE_ID) { title genres { nodes { name seo { canonical schema { raw } } } } }
}
```

(d1) `seo` before model fields on the same node. **Expect identical output on main and new, and identical to (d2).**
```graphql
query D1($p1: ID!) {
  post(id: $p1, idType: DATABASE_ID) { seo { canonical schema { raw } opengraphImage { databaseId } } databaseId title }
}
```

(d2) `seo` after model fields on the same node. Compare to (d1).
```graphql
query D2($p1: ID!) {
  post(id: $p1, idType: DATABASE_ID) { databaseId title seo { canonical schema { raw } opengraphImage { databaseId } } }
}
```

(d3) Root `seo` **before** a node. main: `author.social.title` = "" (nothing leaked yet). new: "". Pair with E1.
```graphql
query D3($p1: ID!) {
  seo { meta { author { title social { title } } } }
  post(id: $p1, idType: DATABASE_ID) { title }
}
```

(e1) #4572 shape: root `seo` after a post leaks `$authordata`. main: `author.social.title` = "Alice Author", `author.title` = "Alice Author, Author at <Site>". new: "" and ", Author at <Site>" (same as D3).
```graphql
query E1($p1: ID!) {
  post(id: $p1, idType: DATABASE_ID) { title }
  seo { meta { author { title social { title } } } }
}
```

(e2) Root `seo` after a different CPT leaks `query_vars['post_type']`. main: `book.archive.title` = "Movies Archive - <Site>" and `book.archive.social.title` = "Movies Archive". new: "Books Archive - <Site>" and "Archive".
```graphql
query E2($movie1: ID!) {
  movie(id: $movie1, idType: DATABASE_ID) { title }
  seo { contentTypes { book { archive { title social { title } } } } }
}
```

(e3) Root `seo` after a post leaks `$post` and `is_singular` into archive schema and head. main: `book.schema.raw` WebPage has `primaryImageOfPage`/`thumbnailUrl` = IMG1 (P1's featured image). new: no image keys.
```graphql
query E3($p1: ID!) {
  post(id: $p1, idType: DATABASE_ID) { title }
  seo { contentTypes { book { schema { raw } archive { fullHead } } } }
}
```

(e4) Root `seo` after the static front page leaks `Static_Home_Page`. main: `book.schema.raw` WebPage has `"about"`, and BreadcrumbList is a single "Home" item (no Books crumb). new: no `about`, and the breadcrumb is Home > Books.
```graphql
query E4($front: ID!) {
  page(id: $front, idType: DATABASE_ID) { title }
  seo { contentTypes { book { schema { raw } archive { fullHead } } } }
}
```

(e5) Root fields after a MediaItem leak `is_attachment`. main: `book.archive.fullHead` canonical/og:url/og:image = `https://<site>/graphql/`, and genre `canonical`/`opengraphUrl` = `https://<site>/graphql/`. new: `/books/` archive URL and the genre term link.
```graphql
query E5($img1: ID!) {
  mediaItem(id: $img1, idType: DATABASE_ID) { title }
  seo { contentTypes { book { archive { fullHead } } } }
  genres { nodes { name seo { canonical opengraphUrl } } }
}
```

(e6) Connection `pageInfo.seo` (not a node), order A, `nodes` first. main: the page-archive `schema.raw` may contain the last node's featured image. new: no image. **Compare to (e7).** On main the two orders can differ; on new they must match. Unverified: `seo` exists on `pageInfo` only through the `WPPageInfo` registration.
```graphql
query E6 {
  posts(first: 2) { nodes { title } pageInfo { seo { schema { raw } } } }
}
```

(e7) `pageInfo` first. Compare to (e6).
```graphql
query E7 {
  posts(first: 2) { pageInfo { seo { schema { raw } } } nodes { title } }
}
```

(f) Mutations: N/A. The extension registers no mutations, and the `seo` fields on mutation payload nodes behave like (a).

## Conflicts / co-existence
- **Cannot co-exist meaningfully with WPGraphQL for Rank Math (`AxeWP/wp-graphql-rank-math`).** Both register `seo` on `ContentNode`, `User`, the taxonomy types and `RootQuery`, with different return types. Both base plugins compete for `<head>` and post meta, so the second registration produces a duplicate-field conflict or overwrite.
- **WooGraphQL:** `Product.seo` is registered only when WooCommerce is active (post-type.php:135). The Product model is a Post subclass, so scoping covers it. A leaked `product` node makes other archives' `%%pt_plural%%` read "Products" (E2 shape) on main.
- `register_graphql_field('NodeWithTitle','seo')` and `('ContentNode','seo')` define the same field twice with the same type. This is harmless today, but a stricter interface-field check would flag it.
