# WPGraphQL for Rank Math SEO: node-scoped context risk matrix

## 1. Versions, sources, install

| What | Version / ref | Source |
| --- | --- | --- |
| Extension | **0.3.4** (`wp-graphql-rank-math.php:10`, `readme.txt` Stable tag 0.3.4; snapshot dir `wp-graphql-rank-math-HEAD`, no git metadata) | github AxeWP/wp-graphql-rank-math |
| Base plugin | **seo-by-rank-math 1.0.280** (wp.org latest-stable, downloaded to `dl/wporg_seo-by-rank-math/seo-by-rank-math`) | wordpress.org |
| Core, today | origin/main `c0dc6a95a` | |
| Core, new behavior | branch `fix/model-restore-global-query` @ `166eff865` (`src/Model/NodeContext.php`, `Model.php:431-457`, `InstrumentSchema.php:109`, `Post.php:262` tear_down) | |

Extension paths below are relative to the extension root. Rank Math paths (prefixed **RM:**) are relative to `seo-by-rank-math/`. Core paths (prefixed **core:**) are relative to `plugins/wp-graphql/src/`.

```bash
wp plugin install wp-graphql --activate        # or the local monorepo build
wp plugin install seo-by-rank-math --activate
wp plugin install https://github.com/AxeWP/wp-graphql-rank-math/releases/download/0.3.4/wp-graphql-rank-math.zip --activate
```

### Key finding: the SEO models are scoped as nodes

The brief assumed the new scoping only recognizes Post, Term and User. **It doesn't.** `NodeContext::sets_up_context()` (core: `Model/NodeContext.php:268-277`) treats **any** `Model` whose `setup()` is declared outside the base `Model` class as a node owner, and checks this with `ReflectionMethod`. All four SEO models override `setup()`:

| Model | Extends | setup() | tear_down() | Recognized as node owner? |
| --- | --- | --- | --- | --- |
| `ContentNodeSeo` | `Seo` → `WPGraphQL\Model\Model` (`src/Model/Seo.php:22`) | `ContentNodeSeo.php:68-149`: `reset_postdata`, `parse_query` (p= / pagename= / attachment= / {cpt}=), `setup_postdata`, `$GLOBALS['post']`, `queried_object(_id)`, then `Seo::setup` | **none** (base no-op, core: `Model.php:182`) | **yes** |
| `TermNodeSeo` | `Seo` | `TermNodeSeo.php:62-106`: adds the `rank_math/replacements/non_cacheable` filter, `$GLOBALS['post']=get_post(0)`, parse_query (category_name / tag / {tax}=slug), queried_object, then `Seo::setup` | `:114-120`: removes the filter, `$GLOBALS['post']=captured`, `wp_reset_postdata()` | **yes** |
| `UserSeo` | `Seo` | `UserSeo.php:67-95`: `reset_postdata`, `parse_query(author_name)`, `is_author=true`, `$authordata`, **`$author`**, queried_object, then `Seo::setup` | `:103-112`: restores `$author`, `$authordata` and `$post`, then `wp_reset_postdata()` | **yes** |
| `ContentTypeSeo` | `Seo` | `ContentTypeSeo.php:63-88`: `$GLOBALS['post']=get_post(0)`, `parse_query(post_type)`, queried_object=WP_Post_Type, then `Seo::setup` | `:96-100`: `$GLOBALS['post']=captured`, `wp_reset_postdata()` | **yes** |
| `Settings` (rankMathSettings) | `Model` (`src/Model/Settings.php:21`) | none | none | no |
| `Redirection` | `Model` (`src/Modules/Redirection/Model/Redirection.php:33`) | none | none | no |

Shared `Seo::setup()` (`src/Model/Seo.php:101-111` and `setup_post_head` at `:326-348`) does the following:
- `Paper::reset()` + `Paper::get()`. The singleton reads `is_search/is_author/is_category/is_tax/is_archive`, `Post::is_simple_page()` → `is_singular()` + `get_the_ID()`, and `get_queried_object()` (RM: `includes/frontend/paper/class-paper.php:84-136`, `includes/class-post.php:101-153,195-206`).
- `$_SERVER['REQUEST_URI'] = …object URL` (`:329`).
- `remove_all_actions()` for `wp`, `parse_request`, `rank_math/head`, `rank_math/json_ld`, and `rank_math/opengraph/{facebook,twitter,slack}` (`:330-336`).
- `$wp_query->is_home = true` for the home URL (`:338-340`).
- `rank_math()->variables->setup()`, `rank_math()->manager->load_modules()`, `new Frontend()` (`:345-347`). These add `wp` callbacks: Schema module `integrations` → `(new JsonLD())->setup()` (RM: `includes/modules/schema/class-schema.php:32,59`), and Frontend `integrations` → `Paper::get()` + `new Head()` (RM: `includes/frontend/class-frontend.php:76,88-107`).

**How the lifecycle changes:**

| | Today (main) | New behavior |
| --- | --- | --- |
| When the SEO model's `setup()` runs | Inside `prepare_field`, once per distinct field read (fields are memoized) | Once, when resolution enters `…/seo` |
| Stack under a post | n/a | `[post → Post, post/seo → ContentNodeSeo]`. `ContentNodeSeo::setup` re-parses the same post on top of `Post::setup`, which is idempotent |
| Field reads inside the node | Each one re-runs setup | `run_as()` short-circuits because `active_model === $this` (core: `NodeContext.php:171-174`) |
| Leaving the node | ContentNodeSeo never tears down | `pop()` runs the model's `tear_down()`, then `restore_global_state()` puts back the snapshot taken before `setup()` (core: `NodeContext.php:222-236, 309-325`) |

So the extension's tear_downs that "reset to empty" (`wp_reset_postdata()`) **cannot clobber the parent node's context**. Core overwrites `$wp_query` public properties and `post/authordata/id/page/pages/more/...` right after, which restores the parent Post/Term/User context for later sibling fields.

Core does **not** restore these, on main or under the new behavior:
- `$_SERVER['REQUEST_URI']`
- removed or added hooks
- the `Paper` static singleton
- `$author` (not in `NodeContext::GLOBALS`, core: `NodeContext.php:37`)
- `rank_math()->head`
- the Replacer static caches
- the WooCommerce `$product` set by `the_post`

## 2. Field registration (file:line)

| Registration | Where |
| --- | --- |
| `NodeWithRankMathSeo` interface + `seo` field, applied to `User`, `TermNode`, `ContentType`, `ContentNode` (filter `graphql_seo_types_with_seo`) | `src/Type/WPInterface/NodeWithSeo.php:34-65` (interface fields at `:84-127`, `seo` resolver at `:89-125`, model mapping at `:142-172`) |
| `ContentNode.seo` / `HierarchicalContentNode.seo` narrowed to `ContentNodeSeo` | `NodeWithSeo.php:62-64` |
| `Seo` interface: `title`, `description`, `robots`, `focusKeywords`, `canonicalUrl`, `breadcrumbTitle`, `fullHead`, `jsonLd`, `openGraph`, `breadcrumbs` (only if `Helper::is_breadcrumbs_enabled()` at schema build) | `src/Type/WPInterface/Seo.php:47-97`. The values come from model closures at `src/Model/Seo.php:116-164` |
| `ContentNodeSeo` interface: `isPillarContent`, `seoScore` | `src/Type/WPInterface/ContentNodeSeo.php:52-63`. Model closures at `src/Model/ContentNodeSeo.php:154-185` |
| `SeoScore` object: `badgeHtml`, `hasFrontendScore`, `score`, `rating` | `src/Type/WPObject/SeoScore.php:36-55` (values are closures returned by `ContentNodeSeo.php:167-181`) |
| Per-type SEO objects `RankMath{PostType}ObjectSeo` / `…TypeSeo` / `…TermSeo` / `RankMathUserSeo` (`facebookProfileUrl`, `twitterUserName`, `additionalProfiles`) | `src/Type/WPObject/SeoObjects.php:44,62,82,101` |
| `JsonLd.raw` | `src/Type/WPObject/JsonLd.php:37` |
| `OpenGraphMeta` and children (array-only resolvers) | `src/Type/WPObject/OpenGraphMeta.php:40-145`, `OpenGraph/*.php` |
| `RootQuery.rankMathSettings` → `new Settings()` | `src/Fields/RootQuery.php:41-45` |
| Settings deferred image fields (`load_deferred` of the post loader) | `Settings/Meta/GlobalMeta.php:53-54`, `HomepageMeta.php:72-73`, `LocalMeta.php:50-51`, `ContentTypeMeta.php:177-178` |
| Sitemap connections (`connectedContentNodes`, `connectedTerms`, `connectedAuthors`) | `Settings/Sitemap/ContentType.php:36`, `Taxonomy.php:36`, `Author.php:35` |
| `RootQuery.redirection` (loader) | `src/Modules/Redirection/Fields/RootQuery.php:41-60` |
| `RootQuery.redirections` connection | `src/Modules/Redirection/Connection/RedirectionConnection.php:38-52` |
| Mutations | none (`src/TypeRegistry.php:263-275` registers an empty list) |

## 3. Risk table

Under the new behavior "seo node" means `NodeContext` has pushed the SEO model on top of its parent node.

| Type.field | Resolver file:line | Global(s) read / written (base-plugin file:line) | Cat | Why, specifically under the NEW behavior |
| --- | --- | --- | --- | --- |
| `*Seo.fullHead` (also feeds `openGraph`) | `src/Model/Seo.php:133-136` → `get_head()` `:256-270` (`do_action('wp')`, `do_action('rank_math/head')`) | All RM head callbacks: Paper singleton, `is_*`, `get_queried_object`, global `$post`. The JSON-LD printer is hooked on `rank_math/head` at priority 90 by every `JsonLD::setup()` (RM: `includes/modules/schema/class-jsonld.php:53-58`, echo at `:134-171`) | **2 / 5 (NEW REGRESSION)** | Today `setup()` runs before **each** field, so `remove_all_actions('rank_math/head')` (`Seo.php:332`) wipes the `rank_math/head` hook that an earlier `jsonLd` field added (`Seo.php:137-139`, `$json->setup()`). Under the new behavior `setup()` runs **once per seo node**, so with `jsonLd` selected **before** `fullHead`, the jsonLd field's JsonLD instance stays hooked. `do_action('wp')` then adds the Schema module's own instance (RM: `class-schema.php:59`), and **`fullHead` prints two `<script type="application/ld+json">` blocks**. With `fullHead` selected first there is no difference. `openGraph` is unaffected because its regex (`Seo.php:282`) only matches `<meta>`. |
| `*Seo.jsonLd { raw }` | `src/Model/Seo.php:137-145` (static closure: `new JsonLD`, `setup()`, `json_ld()`) | `is_singular()`, global `$post` (RM: `class-jsonld.php:134-141`). `add_context_data` reads `is_singular/is_author/get_queried_object/is_front_page` (`:233-255, 370-394`) and `Paper::get()` (`:514,681,695`). `JsonLD::setup` also adds `Block_Parser` (`rank_math/json_ld` at priority 8) and schema `Frontend` (`add_schema` at 10, `connect_schema_entities` at 99) (RM: `class-jsonld.php:53-58`, `blocks/class-block-parser.php:37`, `schema/class-frontend.php:47-48`) | **2 / 5** (unverified output diff) | Mirror of the row above. If `fullHead` or `openGraph` resolved earlier in the same seo selection, `do_action('wp')` has already registered a second full set of `rank_math/json_ld` callbacks. Today the jsonLd field's own `setup()` would have removed them (`Seo.php:333`). `raw` is then built with every filter running twice. The output is probably idempotent because entities are keyed, but that is **unverified**, and it costs double the work. Global reads are SAFE: they run inside the seo node. |
| `ContentNodeSeo` lifecycle (every field under `ContentNode.seo`) | `src/Model/ContentNodeSeo.php:68-149` (no `tear_down`) | Writes `$wp_query` (parse_query, queried_object), `$post`, postdata globals, and `is_home` via `Seo.php:339` | **6** (fix) | Recognized as a node owner (core: `NodeContext.php:268-277`). Today it never tears down, so its post globals and its `is_home=true` (front/home URL) **leak to everything after**. Under the new behavior the snapshot restore on leaving `post/seo` brings back exactly the parent Post context. Sibling fields after `seo` see the Post, which is the correct result. |
| `TermNodeSeo` lifecycle (`TermNode.seo`) | `src/Model/TermNodeSeo.php:62-120` | `$post = get_post(0)` (`:75`), which is **not** a reset: `get_post(0)` returns the current global post. parse_query, queried_object. `tear_down` calls `wp_reset_postdata()` (`:119`) | **6** | Today `wp_reset_postdata()` after each term-SEO field can set `$post` to `$wp_query->post` and fire `the_post`, clobbering the enclosing node's `$post` for the next field. Under the new behavior, `pop()` restores right after `tear_down`, so the parent context (for example `post > categories > seo`) survives. Under a Post node, `$post` inside the term seo is still the parent post (same as today). |
| `UserSeo` lifecycle (`User.seo`) | `src/Model/UserSeo.php:67-112` | `$author` (`:89`, restored at `:106`), `$authordata`, `is_author`. RM Breadcrumbs reads `global $author` (RM: `includes/frontend/class-breadcrumbs.php:448-457`) | **6** | Stacks on core `User::setup` (core: `Model/User.php:91-116`), which does not set `$author`. On leaving the seo node, `tear_down` sets `$author` to the outer `$authordata->ID` (the same user), and core does not restore `$author`, so it **leaks for the rest of the request (same as today)**. Only RM breadcrumbs on an `is_author()` page read it, and `UserSeo` always re-sets it there. Low. |
| `ContentTypeSeo` at root (`contentType(id:)` / `nodeByUri` → ContentType `.seo`) | `src/Model/ContentTypeSeo.php:63-100`, breadcrumbs `:122-156` | `$post = get_post(0)` (`:73`, not a reset), `parse_query(post_type)`, `is_home` (`Seo.php:339`), `Breadcrumbs::generate` conditionals (RM: `class-breadcrumbs.php:206-255`), JsonLD `is_singular` | **1 / 6** | Core `PostType` has no `setup()`, so the chain is just `[contentType/seo → ContentTypeSeo]`. **Today**, a root `contentType{seo}` after `post{…}` sees the **leaked** `$post` (from Post / ContentNodeSeo), and `get_post(0)` keeps it. **New:** `$post` is the request's original (null). This matches the code's intent ("Reset global post"). A visible diff appears only if an RM path reads `$post` while `!is_singular()`. None was found, so **unverified**, expected identical output. |
| `Seo::setup` side effects not covered by core restore (every `seo` node) | `src/Model/Seo.php:102-104, 329-347` | `$_SERVER['REQUEST_URI']`, `remove_all_actions('wp'/'parse_request'/RM head hooks)`, the `Paper` singleton (RM: `class-paper.php:35,84-92,553-556`), `rank_math()->head`, the `new Frontend()` filters on `category_description`/`term_description` (RM: `class-frontend.php:81-82`) | **5** (unchanged) | Neither main nor new restores these. Every field after an seo node (siblings, later root fields, other plugins' resolvers) sees `REQUEST_URI` = the last SEO object's path, no `wp`/`parse_request` callbacks, and a stale `Paper::get()`. Same on both, **not a regression**. The one difference is `$wp_query->is_home` (`Seo.php:339`), which new restores (fix). |
| Restricted fields on `ContentTypeSeo` (e.g. `focusKeywords`, which is not in the allowed list `ContentTypeSeo.php:55` + `Seo.php:80-91`, for users without `edit_posts`) | `src/Model/ContentTypeSeo.php:53-57` | as `Seo::setup` above | **6** (low) | Today a restricted field is removed from `$fields` (core: `Model.php:326-343`), so reading it never calls `setup()`. New: `enter()` pushes the model for **any** field under the node, so `setup()` and its side effects (row above) run even when only restricted fields are selected. The value is still null. |
| `*Seo.title`, `description`, `robots`, `canonicalUrl`, `focusKeywords` | `src/Model/Seo.php:120-158` (`$this->helper` = Paper captured at `:102-105`) | Paper singleton, `is_*`, `get_queried_object`, `get_the_ID` (RM: `class-paper.php:97-136`, `class-post.php:101-153`) | SAFE | The Paper is built at seo-node entry, after the model's parse_query. That is equivalent to today (rebuilt per field). |
| `*Seo.breadcrumbs` | `src/Model/Seo.php:119,171-197`, `ContentNodeSeo.php:190-204` (`is_single($id)`/`is_page($id)`), `ContentTypeSeo.php:122-156` | `RMBreadcrumbs::get()` is a fresh instance each call (RM: `class-breadcrumbs.php:57-66`). `generate()` reads `is_*`, global `$post`, `get_the_ID()`, `get_queried_object()`, `$author` (`:206-255, 282-285, 339-419, 448`) | SAFE | Runs inside the seo node, so `$wp_query` and `$post` are this object's. In lists, every node is pushed fresh. |
| `*Seo.breadcrumbTitle` | `ContentNodeSeo.php:161-165`, `TermNodeSeo.php:132-136`, `UserSeo.php:124-128`, `ContentTypeSeo.php:112` | Explicit IDs (`get_the_title($id)`, `RMHelper::get_*_meta`) | SAFE | No implicit globals. |
| `*Seo.openGraph { … }` | `src/Model/Seo.php:146-150` + array resolvers `OpenGraphMeta.php:40-145` | Same as `fullHead` (memoized `get_head`) | SAFE | Nested resolvers read the parsed array only. Duplicate ld+json (row 1) does not match the OG regex. |
| `ContentNodeSeo.seoScore { badgeHtml hasFrontendScore score rating }` | Closures returned by `ContentNodeSeo.php:167-181`, executed later by the default resolver of the nested `SeoScore` fields | `badgeHtml` → `rank_math_get_seo_score()` → `get_score(0)` reads **global `$post`** (RM: `includes/template-tags.php:56-58`, `class-frontend-seo-score.php:120-135, 216-221`). `hasFrontendScore` reads `is_singular`, `get_the_ID`, `did_action('loop_start')` (`:96-110`) | SAFE (cat 3 covered) | These are deferred closures that run after the `seoScore` field. Today they rely on ContentNodeSeo's leak, which happens to be the same post. New: path `…/seo/seoScore/badgeHtml` is under the seo node, so `$post` is guaranteed. `hasFrontendScore` is always false in GraphQL (no `loop_start`) on both. |
| `ContentNodeSeo.isPillarContent`, `RankMathUserSeo.{facebookProfileUrl,twitterUserName,additionalProfiles}` | `ContentNodeSeo.php:166`, `UserSeo.php:129-136` | Explicit IDs | SAFE | |
| `NodeWithRankMathSeo.seo` (the resolver itself) | `src/Type/WPInterface/NodeWithSeo.php:89-125` | Reads `$source->uri/databaseId/isPostsPage/name` (model fields) | SAFE | Runs inside the parent node, where `run_as` is a no-op. Today the first read triggered `Post::setup`. |
| `RootQuery.rankMathSettings` (+ `meta.*.openGraphImage/socialImage/logo` via `load_deferred`) | `src/Fields/RootQuery.php:44`, `Settings.php:41-55`, deferred at `GlobalMeta.php:53-54` etc. | Options only. Deferred resolves to core Post (MediaItem) models | SAFE | No globals. Deferred Post models are scoped by core by path. |
| Sitemap `connectedContentNodes` / `connectedTerms` / `connectedAuthors` | `Sitemap/ContentType.php:36-50`, `Taxonomy.php:36`, `Author.php:35-48` | Core connection resolvers | SAFE | |
| `RootQuery.redirection`, `RootQuery.redirections` | `Modules/Redirection/Fields/RootQuery.php:55-58`, `Connection/RedirectionConnection.php:42-46` | DB only. The `Redirection` model has no setup | SAFE | |

**Not node-related, but it bites fixtures (unchanged on both behaviors, unverified):** if Rank Math's **Redirections** module is active, `Seo::setup` → `load_modules()` hooks `Redirections::do_redirection` on `wp` (RM: `includes/modules/redirections/class-redirections.php:41-45`). `get_head()` fires `do_action('wp')` with the faked `REQUEST_URI`, and `Redirector` can `wp_redirect(); exit;` (RM: `class-redirector.php:79-83,143-144`), killing the GraphQL response. **Don't create a redirection whose source matches a fixture permalink.**

**Risky (non-SAFE) rows: 8.** Of those:
- New-behavior regressions: rows 1, 2 and 8 (row 2 unverified).
- Behavior changes that are fixes: rows 3, 4 and 6 (row 6 diff unverified).
- Unchanged leaks: rows 5 and 7.

## 4. Fixtures (wp-cli)

```bash
wp rewrite structure '/%postname%/' --hard

# Rank Math: breadcrumbs on (the `breadcrumbs` GraphQL field only exists if enabled at schema build) + schema module on
wp option patch insert rank-math-options-general breadcrumbs on 2>/dev/null || wp option patch update rank-math-options-general breadcrumbs on
wp eval 'update_option("rank_math_modules", array_values(array_unique(array_merge((array) get_option("rank_math_modules", []), ["rich-snippet"]))));'
# Keep the redirections module OFF for these runs (see the exit hazard above)
wp eval 'update_option("rank_math_modules", array_values(array_diff((array) get_option("rank_math_modules", []), ["redirections"])));'

# Hierarchical categories for term breadcrumbs
C0=$(wp term create category "Parent Cat" --slug=parent-cat --porcelain)
C1=$(wp term create category "Child Cat" --slug=child-cat --parent=$C0 --porcelain)
wp term meta update $C1 rank_math_title "Child Cat SEO Title"
wp term meta update $C1 rank_math_description "Child cat description"

# Author with SEO meta
U1=$(wp user create rmauthor rmauthor@example.com --role=author --display_name="RM Author" --porcelain)
wp user meta update $U1 rank_math_title "RM Author SEO Title"
wp user meta update $U1 twitter rmauthor

# Three differing posts (detect wrong-post bleed)
P1=$(wp post create --post_title="Alpha" --post_status=publish --post_author=$U1 --post_category=$C1 --post_content='<!-- wp:paragraph --><p>Alpha body</p><!-- /wp:paragraph -->' --porcelain)
P2=$(wp post create --post_title="Beta"  --post_status=publish --post_author=1 --post_content='<p>Beta body</p>' --porcelain)
P3=$(wp post create --post_title="Gamma" --post_status=publish --post_author=1 --post_content='<p>Gamma body</p>' --porcelain)
for X in "$P1:Alpha:81" "$P2:Beta:42" "$P3:Gamma:12"; do
  ID=${X%%:*}; R=${X#*:}; T=${R%%:*}; S=${R#*:}
  wp post meta update $ID rank_math_title "$T SEO Title"
  wp post meta update $ID rank_math_description "$T meta description"
  wp post meta update $ID rank_math_focus_keyword "$(echo $T | tr A-Z a-z),kw"
  wp post meta update $ID rank_math_breadcrumb_title "$T Crumb"
  wp post meta update $ID rank_math_seo_score $S
done
wp post meta update $P1 rank_math_pillar_content on

# Hierarchical pages for page breadcrumbs
PG_PARENT=$(wp post create --post_type=page --post_title="Parent Page" --post_status=publish --porcelain)
PG_CHILD=$(wp post create --post_type=page --post_title="Child Page" --post_parent=$PG_PARENT --post_status=publish --porcelain)

# Optional: static front + posts page (exercises ContentTypeSeo blog crumb, ContentTypeSeo.php:134-153, and Seo.php:338-340 is_home)
# FP=$(wp post create --post_type=page --post_title=Home --post_status=publish --porcelain)
# PFP=$(wp post create --post_type=page --post_title=Blog --post_status=publish --porcelain)
# wp option update show_on_front page && wp option update page_on_front $FP && wp option update page_for_posts $PFP
# wp option patch insert rank-math-options-general breadcrumbs_blog_page on

echo "{\"p1\":$P1,\"p2\":$P2,\"p3\":$P3,\"c1\":$C1,\"u1\":$U1,\"child\":$PG_CHILD}"
```

## 5. Queries

Variables for all queries (replace them with the echo output):

```json
{ "p1": 101, "p2": 102, "p3": 103, "c1": 7, "u1": 3, "child": 105, "ids": ["101","102","103"] }
```

**(a) Single node, every SEO field.** Compare: identical on main and new, except `fullHead` per (d). `title` = "Alpha SEO Title", `breadcrumbTitle` = "Alpha Crumb", breadcrumbs end with Parent Cat > Child Cat > Alpha, and `seoScore.score` = 81.

```graphql
query A($p1: ID!) {
  post(id: $p1, idType: DATABASE_ID) {
    databaseId
    seo {
      title description canonicalUrl robots focusKeywords breadcrumbTitle
      breadcrumbs { text url isHidden }
      openGraph { title url description type }
      jsonLd { raw }
      fullHead
      isPillarContent
      seoScore { score rating badgeHtml hasFrontendScore }
    }
  }
}
```

**(b) List of three differing nodes (bleed).** Compare: each node's `title`/`canonicalUrl`/`seoScore.score`/last crumb matches its own post (Alpha/Beta/Gamma; 81/42/12), and `jsonLd.raw` contains its own URL. Expected identical on both. Any cross-node value is a bug.

```graphql
query B($ids: [ID]) {
  posts(where: { in: $ids, orderby: { field: TITLE, order: ASC } }) {
    nodes {
      databaseId title
      seo { title canonicalUrl breadcrumbs { text } seoScore { score badgeHtml } jsonLd { raw } }
    }
  }
}
```

**(c) Nested under another node (stacked contexts: post > author > UserSeo, post > term > TermNodeSeo, page > parent > ContentNodeSeo).** Compare:
- `author.node.seo.title` = "RM Author SEO Title", and its breadcrumbs = author archive crumb.
- `categories…seo.title` = "Child Cat SEO Title".
- The `afterNested` alias (resolved after the nested nodes) still shows Alpha.
- The child page's parent seo breadcrumbs end at "Parent Page".

Expected identical. On main, `afterNested` relies on re-setup. On new it relies on restore.

```graphql
query C($p1: ID!, $child: ID!) {
  post(id: $p1, idType: DATABASE_ID) {
    seo { title }
    author { node { databaseId seo { title breadcrumbs { text } ... on RankMathUserSeo { twitterUserName } } } }
    categories { nodes { databaseId seo { title breadcrumbs { text url } } } }
    afterNested: seo { title canonicalUrl breadcrumbs { text } }
  }
  page(id: $child, idType: DATABASE_ID) {
    seo { breadcrumbs { text } }
    parent { node { ... on Page { seo { title breadcrumbs { text } } } } }
  }
}
```

**(d1) Ordering, `jsonLd` BEFORE `fullHead` (the regression).** Compare: count `application/ld+json` in `fullHead`. **main = 1, new = 2** (expected regression). Also diff `jsonLd.raw` against (d2).

```graphql
query D1($p1: ID!) {
  post(id: $p1, idType: DATABASE_ID) {
    seo { jsonLd { raw } fullHead }
  }
}
```

**(d2) Ordering, `fullHead` BEFORE `jsonLd`.** Compare:
- `fullHead` has 1 ld+json on both.
- `jsonLd.raw` should equal main's. If new's `raw` differs from D1's `raw` (duplicated `@graph` entities), the doubled `rank_math/json_ld` callbacks are visible (row 2, unverified).

```graphql
query D2($p1: ID!) {
  post(id: $p1, idType: DATABASE_ID) {
    seo { fullHead openGraph { title } jsonLd { raw } }
  }
}
```

**(d3) SEO before and after core model fields in the same selection.** Compare: `seo.title`, `seo.canonicalUrl` and `content` are identical between the two aliases and between main and new.

```graphql
query D3($p1: ID!) {
  first: post(id: $p1, idType: DATABASE_ID) { seo { title canonicalUrl } title content uri }
  second: post(id: $p1, idType: DATABASE_ID) { title content uri seo { title canonicalUrl } }
}
```

**(e) Root/sibling fields after a node (#4572 shape).** On main, `contentType.seo` and `category.seo` run with the leaked `$post` (Alpha) and leaked flags. On new they run on the request's original state. Compare:
- `contentType.seo.title` / `breadcrumbs` / `jsonLd.raw` and `category.seo.*` are expected identical. A diff means RM read `$post` outside `is_singular()` (row 6).
- `jsonLd.raw` for the content type must **not** contain Alpha's URL or title on either behavior. If main does and new doesn't, main was bleeding.
- `rankMathSettings` is identical (no globals).

```graphql
query E($p1: ID!, $c1: ID!) {
  post(id: $p1, idType: DATABASE_ID) { seo { title } }
  contentType(id: "post", idType: NAME) {
    seo { title canonicalUrl breadcrumbs { text url } jsonLd { raw } }
  }
  category(id: $c1, idType: DATABASE_ID) {
    seo { title description breadcrumbs { text url } jsonLd { raw } }
  }
  rankMathSettings { general { hasBreadcrumbs } meta { global { separator } } }
}
```

**(f) Mutations:** none. The extension registers no mutations (`src/TypeRegistry.php:263-275`).

## 6. Conflicts with other extensions

- **WPGraphQL Yoast SEO** (`ashhitch/wp-graphql-yoast-seo`) and **add-wpgraphql-seo** both register an `seo` field on `ContentNode`/`TermNode`/`User`/`ContentType`. They cannot meaningfully coexist with this plugin (field and type collisions). Rank Math and Yoast themselves also conflict. Test each SEO extension in isolation.
- **WPGraphQL WooCommerce:** this plugin adds `ContentNodeSeo` to product variation types (`src/Extensions/WPGraphQLWooCommerce/Type/WPObject/SeoObjects.php:63-69`). `ContentNodeSeo::setup` → `setup_postdata()` fires `the_post`, so Woo sets global `$product`. Core's restore does not include `product`, so it leaks past the seo node on both behaviors. Cross-check against the woocommerce matrix.
- **Smart Cache / anything that reads `$_SERVER['REQUEST_URI']` after execution:** after any `seo` resolution it holds the last SEO object's path (`Seo.php:329`). This is unchanged by the new behavior.
