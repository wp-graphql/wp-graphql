# WPGraphQL Polylang (valu-digital/wp-graphql-polylang) — node-scoped context risk matrix

## 1. Versions, sources, install

| Item | Value |
| --- | --- |
| Extension | wp-graphql-polylang **0.7.1** (`wp-graphql-polylang.php:7`), source `dl/valu-digital_wp-graphql-polylang/wp-graphql-polylang-HEAD` (GitHub default-branch HEAD tarball, which includes `vendor/autoload.php`, needed by `wp-graphql-polylang.php:13-16`). No release zip asset |
| Base plugin | Polylang (free) **3.9.1** from wp.org, downloaded to `dl/wporg_polylang/polylang` (`polylang.php:13`) |
| Core WPGraphQL ref | origin/main `62b43019f` |

```bash
wp plugin install wp-graphql --activate
wp plugin install polylang --activate
# No release asset: install the branch zip (README.md:116-119 recommends the "stable" branch)
wp plugin install https://github.com/valu-digital/wp-graphql-polylang/archive/refs/heads/stable.zip --activate
#   (equivalently https://codeload.github.com/valu-digital/wp-graphql-polylang/zip/refs/heads/stable)
# Composer alternative (README.md:112-114): composer require valu/wp-graphql-polylang
```

PHP 8.2+ note: the extension uses `"${type}"` interpolation (`src/PostObject.php:97,100,108,112,118`, `src/TermObject.php:57,60,68,72,77`), which raises E_DEPRECATED at schema build. That noise shows up the same on main and new, so ignore it when diffing `extensions.debug`.

## 2. How it hooks in

| What | Where |
| --- | --- |
| Forces Polylang into **admin** context and model for GraphQL HTTP requests | `pll_context` → `PLL_Admin` (`src/Loader.php:18`, `:86-95`); `pll_model` → `PLL_Admin_Model` (`:17`, `:77-84`); applied at Polylang `class-polylang.php:187`, `:220` |
| Forces `pll_filter_content` user meta to `all` during GraphQL | `src/Loader.php:19-24`, `:32-43` → Polylang `admin/admin-base.php:466` gives `filter_lang = get_language('all')` = false → `set_current_language()` sets `curlang = filter_lang` (`admin-base.php:396-397`), so `PLL()->curlang` is **false** for the whole request (only the `pll_admin_current_language` filter at `admin-base.php:434` can change that) |
| Request detection | `src/Loader.php:130-146` (`is_graphql_http_request()`; internal `graphql()` calls are NOT treated as GraphQL, see notes) |
| `Language` object type, `LanguageCodeEnum`, `LanguageCodeFilterEnum` | `src/PolylangTypes.php:17-112` |
| `RootQuery.languages` | `src/LanguageRootQueries.php:21-66` |
| `RootQuery.defaultLanguage` | `src/LanguageRootQueries.php:68-98` |
| `RootQuery.translateString(string:, language:)` | `src/StringsTranslations.php:19-40` |
| `<PostType>.language` | `src/PostObject.php:124-178` (one per translated post type, `:84-86`, `:91`) |
| `<PostType>.translation(language:)` | `src/PostObject.php:180-212` |
| `<PostType>.translations` | `src/PostObject.php:214-273` |
| `language`/`languages` where-args on `RootQueryToContentNodeConnectionWhereArgs` and `RootQueryTo<Type>ConnectionWhereArgs` | `src/PostObject.php:67-82`, `:97-110`; mapped by `graphql_map_input_fields_to_wp_query` → `Helpers::map_language_to_query_args` (`src/PostObject.php:28-33`, `src/Helpers.php:21-53`) |
| `Create<Type>Input.language`/`Update<Type>Input.language` + `graphql_post_object_mutation_update_additional_data` | `src/PostObject.php:112-122`, `:18-26`, `:49-63` |
| `isFrontPage` override for translated front pages | `graphql_resolve_field` filter `src/PostObject.php:38-43`, `:276-310` |
| `<Taxonomy>.language` / `.translations` / `.translation(language:)` | `src/TermObject.php:114-157`, `:159-193`, `:195-218` |
| Term where-args, term create/update language | `src/TermObject.php:57-81`, `:18-30`, `:33-40`, `:86-112` |
| Menu items: `RootQueryToMenuItemConnectionWhereArgs.language` + location rewrite `LOC` → `LOC___<lang>` | `src/MenuItem.php:79-87`, `:22-27`, `:30-54`; per-language locations created at `wp_loaded` (`:60-77`) |
| ACF Options Pages (needs ACF Pro + "ACF Options For Polylang") | `src/OptionsPages.php:44-79`, loaded at `src/Loader.php:62-67` |

There is no separate menu item or menu location `language` *field*: language for menus exists only as the where-arg and location rewrite above. Grep of `src/` finds **no** `Deferred`, `load_deferred`, `get_queried_object`, `global $post`, `$wp_query`, `setup_postdata`, `wp_reset_*`, `switch_to_blog`, `pll_current_language` or `curlang` use.

## 3. Risk table

| `Type.field` | Resolver | Global(s) read (downstream) | Cat. | Why, under the NEW behavior |
| --- | --- | --- | --- | --- |
| `<PostType>.language` | `src/PostObject.php:130-176` | None. Explicit `$post->ID` / `$post->isPreview` (`:143-149`) → `pll_get_post_language($id)` (Polylang `api.php:537`), which reads the language taxonomy for that ID | SAFE | Source-driven. Side note: on main, reading `$post->ID` runs `Post::setup()` and is one of the reads that *causes* the leak other extensions see. Under new it is a no-op change |
| `<PostType>.translation(language:)` | `src/PostObject.php:196-210` | None. `pll_get_post_translations($post->ID)` (`api.php:588`) | SAFE | Returns `new \WPGraphQL\Model\Post(...)` directly, not via the loader or a Deferred (`:207-209`). Under new, fields under it nest as Post-in-Post: the translation's context should stack on the parent's and be restored after. It is a plain `Model\Post`, so it is recognized as a Post node (no subclass issue). Worth one nested test, see (c). Pre-existing, unrelated: no `is_private()` filter here (unlike `translations`) |
| `<PostType>.translations` | `src/PostObject.php:225-271` | None. `pll_get_post_translations`, `wp_get_post_parent_id($post->ID)` for previews (`:228-233`) | SAFE | Same as above: a list of directly built `Model\Post` (`:255`, `:263`), so the list-bleed test applies to fields *under* each translation |
| `isFrontPage` override (all Post-model types) | `src/PostObject.php:276-310` | `get_option('show_on_front'/'page_on_front')`, `pll_get_post_translations(page_on_front)`, `$source->ID` | SAFE | Filter on the resolved value (`graphql_resolve_field`, core `InstrumentSchema.php:181`). Explicit source and options only |
| `<Taxonomy>.language` | `src/TermObject.php:120-156` | None. `$term->term_id` → `pll_get_term_language` (`api.php:567`) | SAFE | Source-driven |
| `<Taxonomy>.translations` / `.translation(language:)` | `src/TermObject.php:167-192`, `:208-217` | None. `pll_get_term_translations`, `get_term($id)` | SAFE | Return directly built `Model\Term`, which nests as Term-in-Term under new behavior |
| `RootQuery.languages` | `src/LanguageRootQueries.php:27-65` | `pll_languages_list()`, `pll_home_url($slug)` with **explicit** slug (`:60`) | SAFE | Root field, no node or curlang dependence |
| `RootQuery.defaultLanguage` | `src/LanguageRootQueries.php:71-97` | `pll_default_language()` (`api.php:173`, model option). **Edge:** `homeUrl` uses `$language['slug']` (`:93`), which is only set when `code`/`slug`/`id` is also selected (`:76-83`, `Helpers.php:10-15`). With `{ defaultLanguage { homeUrl } }` alone it calls `pll_home_url(null)` → `pll_current_language()` → `PLL()->curlang` (`api.php:244-253`, `:142-151`) | SAFE | `curlang` is fixed to false for the whole GraphQL request (admin context, `admin-base.php:396-397`, `:466`). It is never derived from the queried object or `$post`, and nothing in the extension or Polylang's admin code changes it during resolution. So `homeUrl` falls back to `home_url('/')` on both main and new. Bug-ish (no language prefix), but not a regression |
| `RootQuery.translateString` | `src/StringsTranslations.php:37-39` | `pll_translate_string($s, $lang)` with explicit language: not `PLL_Frontend`, so it loads `PLL_MO` for that language (`api.php:368-387`) | SAFE | No curlang or node dependence |
| Where-args `language`/`languages` on post, content-node and term connections | `src/Helpers.php:21-53` | `pll_default_language('slug')` for `DEFAULT` (`:43-45`) | SAFE | Pure arg mapping to the `lang` query var. Connection queries are local `WP_Query`/`WP_Term_Query` objects. Polylang's admin `parse_query` hook (`admin/admin-filters-post.php:40`, `:143-146`) calls `PLL_Query::filter_query($curlang)`, which applies a language only `if ( ! empty( $lang ) )` (`query.php:171`), and curlang is false |
| Menu item `where.language` + `location` rewrite | `src/MenuItem.php:30-54` | `pll_default_language('slug')` | SAFE | Arg rewrite only. Location → menu mapping comes from Polylang options via `theme_mod_nav_menu_locations` (`admin/admin-nav-menu.php:28`, `:287-306`), with no globals |
| Mutations: create/update post `language` | `src/PostObject.php:49-63` | `pll_set_post_language($post_id, …)`, `pll_default_language()`; Polylang `save_post` → `set_default_language` → `Create_Post` reads `$_GET['new_lang']`, `$_REQUEST['lang']`, the parent's language, and `pref_lang`/`curlang` (`crud-posts.php:91-111`, `:123-152`; `Capabilities/Create/Post.php:30-42`) | SAFE (cat. 4 checked) | Explicit post ID throughout. No global `$post` or queried-object reads in the mutation path |
| Mutations: create/update term `language` | `src/TermObject.php:33-40`, `:86-112` | `pll_set_term_language($term_id, …)` | SAFE | Explicit term ID |
| ACF Options Pages `language` arg | `src/OptionsPages.php:102-155`, `:167-179` | Its own static `self::$current_language` / `$root_query_locale_mapping`, set in `graphql_before_resolve_field` and cleared in `graphql_after_resolve_field` (core `InstrumentSchema.php:121`, `:199`, both synchronous) | SAFE w.r.t. node scoping (own cat.‑5‑style state, not WP globals); **unverified** functionally | Core's node setup and restore never touch these statics, so behavior is unchanged. Separately: (1) `is_options_page()` expects the legacy array source `['type'=>'options_page']` (`:181-189`), so with WPGraphQL for ACF 2.x it is probably inert. (2) The after-hook clears the language when the resolver *returns*, so any ACF option field that resolves through a Deferred would already have lost it (cat. 3, pre-existing, not affected by this change). Both unverified |

**Polylang base check (what `pll_*` read):** `pll_current_language()` reads only `PLL()->curlang` (`api.php:142-151`). In the forced `PLL_Admin` context, `curlang` is set once from `filter_lang`/request params (`admin-base.php:396-434`) and never from the queried object. A grep of Polylang's non-frontend code (`src/admin`, `src/*.php`, `src/Model`, `src/modules`) finds no `get_queried_object`, `global $post`, `get_post()` or `get_the_ID()` reads. The queried-object fallback exists only in the **frontend** chooser (`frontend/choose-lang-content.php:26`, `:110`), which is not loaded for `/graphql` HTTP requests. Even there, `set_language()` refuses to run twice (`frontend/choose-lang.php:90-94`), so core's per-node `parse_query()` can't flip `curlang` mid-request. Internal `graphql()` calls from a themed page run in `PLL_Frontend` context (the extension's detection is HTTP-only, `Loader.php:145`), and there `curlang` comes from the page URL, not from GraphQL nodes. Same on main and new.

**Result: 0 risky fields.** Expected diff between main and new: none. The queries below are parity checks that also exercise the nested-model paths (`translation(s)` returning directly built models) that the new scoping has to handle.

## 4. Fixtures

```bash
wp plugin install wp-graphql --activate
wp plugin install polylang --activate
wp plugin install https://github.com/valu-digital/wp-graphql-polylang/archive/refs/heads/stable.zip --activate
wp theme activate twentytwentyone            # classic theme with a "primary" menu location
wp rewrite structure '/%postname%/' --hard

# Languages (first added becomes default: Polylang Model/Languages.php:243-246)
wp eval 'foreach ([["en_US","en","English",0],["fi","fi","Suomi",1],["de_DE","de","Deutsch",2]] as $l) { $r = PLL()->model->languages->add(["locale"=>$l[0],"slug"=>$l[1],"name"=>$l[2],"term_group"=>$l[3]]); if (is_wp_error($r)) { echo $r->get_error_message(), "\n"; } }'

# Three translated post groups (en/fi/de) with different titles
for N in 1 2 3; do
  EN=$(wp post create --post_title="Post $N EN" --post_status=publish --porcelain)
  FI=$(wp post create --post_title="Post $N FI" --post_status=publish --porcelain)
  DE=$(wp post create --post_title="Post $N DE" --post_status=publish --porcelain)
  wp eval "pll_set_post_language($EN,'en'); pll_set_post_language($FI,'fi'); pll_set_post_language($DE,'de'); pll_save_post_translations(['en'=>$EN,'fi'=>$FI,'de'=>$DE]);"
  eval "EN$N=$EN; FI$N=$FI; DE$N=$DE"
done

# Translated static front page (exercises the isFrontPage override)
HOME_EN=$(wp post create --post_type=page --post_title='Home' --post_name=home --post_status=publish --porcelain)
HOME_FI=$(wp post create --post_type=page --post_title='Koti' --post_name=koti --post_status=publish --porcelain)
wp eval "pll_set_post_language($HOME_EN,'en'); pll_set_post_language($HOME_FI,'fi'); pll_save_post_translations(['en'=>$HOME_EN,'fi'=>$HOME_FI]);"
wp option update show_on_front page && wp option update page_on_front "$HOME_EN"

# Translated categories
CAT_EN=$(wp term create category News --slug=news --porcelain)
CAT_FI=$(wp term create category Uutiset --slug=uutiset --porcelain)
wp eval "pll_set_term_language($CAT_EN,'en'); pll_set_term_language($CAT_FI,'fi'); pll_save_term_translations(['en'=>$CAT_EN,'fi'=>$CAT_FI]);"
wp post term set "$EN1" category "$CAT_EN" --by=id && wp post term set "$FI1" category "$CAT_FI" --by=id

# String translation for translateString (Polylang MO stored in DB)
wp eval '$mo = new PLL_MO(); $mo->add_entry(new Translation_Entry(["singular"=>"Read more","translations"=>["Lue lisää"]])); $mo->export_to_db(PLL()->model->get_language("fi"));'

# Per-language menus at the "primary" location (Polylang option nav_menus[theme][loc][lang]; admin-nav-menu.php:287-306)  -- unverified shape on 3.9.1 options object
M_EN=$(wp menu create "Main EN" --porcelain); M_FI=$(wp menu create "Main FI" --porcelain)
wp menu item add-post "$M_EN" "$EN1"; wp menu item add-post "$M_FI" "$FI1"
wp menu location assign "$M_EN" primary
wp eval "\$o = get_option('polylang'); \$o['nav_menus'][get_stylesheet()]['primary'] = ['en'=>$M_EN,'fi'=>$M_FI]; update_option('polylang', \$o);"

echo "{\"en1\":$EN1,\"fi2\":$FI2,\"de3\":$DE3,\"homeFi\":$HOME_FI,\"catEn\":$CAT_EN}"
```

Variables:

```json
{ "en1": 0, "fi2": 0, "de3": 0, "homeFi": 0, "catEn": 0 }
```

## 5. Queries (all should be byte-identical on main and new)

(a) Single node: language and translations, language selected before and after model fields.
```graphql
query A($en1: ID!) {
  post(id: $en1, idType: DATABASE_ID) {
    language { code name locale }
    title
    translations { databaseId title language { code } }
    translation(language: FI) { title language { code } }
  }
}
```
Expect `language.code: EN`, two translations (`Post 1 FI`/`fi`, `Post 1 DE`/`de`), and `translation(FI).title = "Post 1 FI"`.

(b) List of 3+ differing nodes across languages (bleed detector): each row's language must match its own title suffix.
```graphql
query B { posts(first: 9, where: { language: ALL, orderby: { field: TITLE, order: ASC } }) { nodes { language { code } title translations { title language { code } } } } }
```
Expect 9 nodes. For each, `language.code` matches the title suffix and `translations` are the other two of the same group number.

(b2) Language filter on connections (where-arg mapping).
```graphql
query B2 {
  fi: posts(where: { language: FI }) { nodes { title } }
  multi: contentNodes(where: { languages: [EN, DE], contentTypes: [POST] }) { nodes { ... on Post { title language { code } } } }
  def: categories(where: { language: DEFAULT }) { nodes { name language { code } } }
}
```
Expect `fi` = 3 FI posts, `multi` = 6 EN+DE posts, `def` = `News` (plus EN `Uncategorized` if present).

(c) Nested model under model: fields under directly built translation models, then parent fields after the nested node.
```graphql
query C($en1: ID!) {
  post(id: $en1, idType: DATABASE_ID) {
    translation(language: FI) { title uri categories { nodes { name language { code } translation(language: EN) { name } } } }
    title
    uri
    categories { nodes { name translations { name } } }
  }
}
```
Expect the FI branch: `Post 1 FI`, its own `uri`, category `Uutiset`/`fi` → `News`. Then the parent `title`/`uri` are the EN post's again (new: restored after the nested Post node).

(d) Field before vs after model fields, with a different-language node resolved first.
```graphql
query D($fi2: ID!, $de3: ID!) {
  first: post(id: $fi2, idType: DATABASE_ID) { title }
  before: post(id: $de3, idType: DATABASE_ID) { language { code } translations { title } title }
  after:  post(id: $de3, idType: DATABASE_ID) { title translations { title } language { code } }
}
```
Expect `before` == `after` (`DE`, translations `Post 3 EN`/`Post 3 FI`).

(e) #4572 shape: root fields after a node (curlang / default-language reads).
```graphql
query E($homeFi: ID!) {
  page(id: $homeFi, idType: DATABASE_ID) { title isFrontPage language { code } }
  defaultLanguage { homeUrl }
  defaultLanguage2: defaultLanguage { code homeUrl }
  languages { code name homeUrl }
  translateString(string: "Read more", language: FI)
  menuItems(where: { location: PRIMARY, language: FI }) { nodes { label } }
}
```
Expect `isFrontPage: true` (the FI translation of `page_on_front`, override at `PostObject.php:301-309`) and `language.code: FI`. `defaultLanguage.homeUrl` = the plain `home_url('/')` (curlang false; same on both, not influenced by the FI page above). `defaultLanguage2` = `EN` + EN home URL. `languages` = EN/FI/DE with per-language home URLs. `translateString` = `"Lue lisää"`. `menuItems` = `Post 1 FI` (location rewritten to `PRIMARY___fi`).

(f) Mutation: language on create/update (no global reads).
```graphql
mutation F { createPost(input: { title: "Created FI", status: PUBLISH, language: FI }) { post { databaseId language { code } } } }
```
Expect `language.code: FI` on both. Without `language`, the default `EN` (`PostObject.php:59-61`).
