# WPGraphQL for ACF (monorepo `plugins/wp-graphql-acf`)

## Sources and install

- Read locally from the working tree at `plugins/wp-graphql-acf` (header `Version: 3.0.0`; the 3.1.0 release work is on main). Paths below are relative to that directory.
- Base plugin, either of:
  - `wp plugin install secure-custom-fields --activate` (free, and includes options pages, repeater and flexible content, which the options-page tests below need), or
  - `wp plugin install advanced-custom-fields --activate` (ACF free has no options pages, so skip the options-page queries), or ACF PRO (paid).
- Extension: `wp plugin install wpgraphql-acf --activate` (wp.org), or activate the monorepo copy in wp-env.

## How fields reach a node

The core implementation checked is `plugins/wp-graphql/src/Model/NodeContext.php` (commits `166eff865`..`d54073f9a`). Only models that override `setup()` own a node, and in core that is Post, Term and User. `AcfOptionsPage` does not override it, so it is never a node.


- Field group field on each location type: `Registry.php:822-853` registers interface `WithAcf<Group>` with field `<groupFieldName>`, and its resolver returns the array `['node' => $node, 'acf_field_group' => ...]` (`Registry.php:842-849`). So every ACF field is resolved on an **array** source nested under the Post, Term, User, Comment, MenuItem or options-page node. That is the "nested non-model source" shape the new behavior covers.
- Each ACF field resolves in `FieldConfig::resolve_field()` (`FieldConfig.php:446`). It computes `$node_id = Utils::get_node_acf_id( $node )` (`FieldConfig.php:451`), which reads `$node->databaseId` on Post, MenuItem, Menu, User and Comment models, and `$node->term_id` on Term (`Utils.php:40-56`). Today that model-field read triggers `setup()`, so for **posts** the global `$post` is the node's post (and then leaks, because `Post::tear_down()` on main does not restore it).
- Values are read by `get_field( $key, $node_id, $format )` with an explicit ID (`FieldConfig.php:427-431`, `586`), so value lookup itself never falls back to the global post. When `$node_id` is empty the resolver returns null (`FieldConfig.php:580-582`) instead of letting ACF fall back to `get_the_ID()`.
- Options pages: a **root** field per options page, registered on `RootQuery` (`Registry.php:419-442`), and resolved through the deferred loader `load_deferred( menu_slug )` (`Registry.php:428-436`). The `AcfOptionsPage` model (`src/Model/AcfOptionsPage.php:22`) extends `WPGraphQL\Model\Model` and does **not** override `setup()` / `tear_down()`, so it is not a Post, Term or User node. Fields under it see the request's original state under the new behavior.

## Risky fields

| Type.field | Resolver | Global read | Category | Why, under the NEW behavior |
| --- | --- | --- | --- | --- |
| `<OptionsPage>.<group>.<wysiwygField>` (for example `siteSettings.siteFields.body` from the fixture) | `FieldConfig.php:586` (`get_field` with format, because wysiwyg is in `should_format_field_value`, `FieldConfig.php:327-334`), then `FieldConfig.php:651-654` `apply_filters( 'the_content', $value )` | ACF wysiwyg formatting (`acf_the_content`, which runs `do_shortcode`) and WP `the_content`: `prepend_attachment()` reads `get_post()`, `[gallery]` without ids reads `get_post()`, and `WP_Embed::autoembed` reads global `$post` to cache oEmbed HTML in that post's meta | **1 + 3** | The options page is a root field resolved in a Deferred, outside any node. Today it sees whatever post leaked last: if a `mediaItem` was read before it, `prepend_attachment` **prepends the attachment's markup** to the options wysiwyg, and `[gallery]` renders the leaked post's attached images. Under the new behavior it sees the original state (no post), so `[gallery]` lists **unattached** media (`post_parent = 0`) and nothing is prepended. This is a behavior change that fixes a bleed, but any site that relied on it will see different HTML. |
| `<OptionsPage>.<group>.<wysiwygField>`, oEmbed URL on its own line | same as above | `WP_Embed::shortcode` uses global `$post->ID` to cache into `_oembed_*` post meta | **1** (side effect) | Today the oEmbed cache is written to the meta of whichever post leaked. Under the new behavior it goes to the `oembed_cache` post type. The output HTML is the same; only where the cache is written changes. Low impact, but it is a write side effect. |
| `<Term>.<group>.<wysiwygField>` (for example `category.termFields.body`) | `FieldConfig.php:586`, `651-654` | `get_post()` via `[gallery]`, `prepend_attachment` | **2** | Today `Term::setup()` sets `$post = get_post(0)` only while `term_id` is read (`Utils.php:41`), then `tear_down()` restores the **leaked** post before `get_field()` formats the value. So `[gallery]` in a term wysiwyg renders the last leaked post's gallery (a bug). Under the new behavior the term node is current for the whole subtree, so `$post` is null and the gallery lists unattached media. The result depends on what was queried before it today, and is stable under the new behavior. |
| `<User>.<group>.<wysiwygField>` | same | `get_post()` | **2 / SAFE** | `User::setup()` does not change `$post` unless the main query has a post. Nested under a post (`post > author > node > userFields`) both today and new give the outer post. At root (`user(id:)`) today gives the leaked post, new gives null. |
| `<Post>.<group>.<wysiwygField>` (for example `post.storyFields.body`) | `FieldConfig.php:451` reads `databaseId` first, then `586`, `651-654` | `get_post()` via `[gallery]`, `prepend_attachment`, autoembed | **SAFE** | Today it works because reading `databaseId` sets up and leaks the node's post. The new behavior sets up the same post for the whole subtree. This is a good regression canary. The intermediate "restore after each model field" commit (`d8f682e33`) would have broken it, because `$post` was restored before `get_field()` formats. With `NodeContext` (`166eff865`), `databaseId` is read inside the node's own subtree, so `run_as` (`Model.php:457`) sees the model is already active and leaves the context in place. |
| `<Post>.<group>.<repeater>.<wysiwygSubField>`, flexible content layouts | `FieldConfig.php:510-524` (row values passed down), `prepare_acf_field_value` `651-654` | same | **SAFE** (canary) | Rows are formatted by the parent `get_field()` call, while `databaseId` has just been read. Under the new behavior the sub-field resolves on an array two levels below the post, which the new behavior still covers. |
| ACF Block fields (ACF blocks surfaced through WPGraphQL Content Blocks) | `FieldConfig.php:546-566` (`acf_setup_meta( $block['data'], $block_id )` then `get_field( name, $block_id )`, then `acf_reset_meta`) | ACF meta setup (its own global, category 5); wysiwyg inside a block still runs `the_content` | **5 (low) / SAFE** | `acf_setup_meta` / `acf_reset_meta` are ACF's own local store and are balanced within the resolver, so there is no interaction with WP query globals. Shortcodes inside block wysiwyg see the post that owns the block under the new behavior (correct). Unverified for a block inside a synced pattern (`core/block`): the post above it is the referencing post, not the `wp_block`. |
| ACF Extended `acfe_taxonomies` / `acfe_taxonomy_terms` / `acfe_menus` / `acfe_user_roles` | `ThirdParty/AcfExtended/FieldType/AcfeTaxonomies.php:28`, `AcfeTaxonomyTerms.php:27`, `AcfeMenus.php:28`, `AcfeUserRoles.php:35` | none: they load by name through the dataloader inside a Deferred | **SAFE** | The Deferred only calls loaders with explicit values. No globals are read. |
| `post_object`, `relationship`, `page_link`, `image`, `file`, `gallery`, `taxonomy`, `user` | connections, resolved by stored ID (`FieldConfig.php:348-360`, raw-row path `501-503`) | none | **SAFE** | Explicit IDs and loaders. `page_link` is read unformatted, so `get_permalink()` is not called without an ID. |
| `oembed` (ACF field type) | formatted via `get_field` (`FieldConfig.php:327-334`) | ACF calls `wp_oembed_get( $url )`, which does not read the global post | **SAFE** (unverified in ACF source, not downloaded) | Not routed through `WP_Embed`, so no post-meta cache keyed on the global post. |
| Field groups with `isPreviewable` (`Registry.php:839`) | core per-field preview overlay | node data swapped to the preview revision | **unverified** | When the overlay runs, ACF's `$node` is the revision model. Core's node scoping keys off the path's node (the published post), and `Post::setup()` for a revision sets up the parent. Both point at the same parent, so this should be equivalent. Worth one preview query. |

Admin-only global reads (`Admin/Settings.php:419`, `Admin/PostTypeRegistration.php:176`, `Admin/TaxonomyRegistration.php:174`) never run during a GraphQL request.

Mutations: none. ACF registers no mutations.

## Fixtures (wp-cli)

```bash
# Base + extension
wp plugin install secure-custom-fields --activate
wp plugin activate wpgraphql-acf || wp plugin install wpgraphql-acf --activate

# Images: two attached to P1, one attached to P2, one unattached
P1=$(wp post create --post_title='ACF Post One' --post_status=publish --porcelain)
P2=$(wp post create --post_title='ACF Post Two' --post_status=publish --porcelain)
P3=$(wp post create --post_title='ACF Post Three (no images)' --post_status=publish --porcelain)
IMG=https://s.w.org/style/images/about/WordPress-logotype-wmark.png
A1=$(wp media import "$IMG" --post_id=$P1 --title='P1 image A' --porcelain)
A2=$(wp media import "$IMG" --post_id=$P1 --title='P1 image B' --porcelain)
A3=$(wp media import "$IMG" --post_id=$P2 --title='P2 image' --porcelain)
AU=$(wp media import "$IMG" --title='Unattached image' --porcelain)
CAT=$(wp term create category 'ACF Cat' --porcelain)
wp post term set $P1 category $CAT --by=id
wp post term set $P2 category $CAT --by=id
U=$(wp user create acfauthor acfauthor@example.com --role=author --porcelain)
wp post update $P1 --post_author=$U

# Field groups (persisted to the DB with acf_import_field_group)
wp eval '
$wysiwyg = function( $key ) { return [ "key" => $key, "label" => "Body", "name" => "body", "type" => "wysiwyg", "show_in_graphql" => 1, "graphql_field_name" => "body" ]; };
acf_import_field_group( [ "key" => "group_story", "title" => "Story Fields", "show_in_graphql" => 1, "graphql_field_name" => "storyFields", "graphql_types" => [ "Post" ],
  "fields" => [ $wysiwyg( "field_story_body" ),
    [ "key" => "field_story_rows", "label" => "Rows", "name" => "rows", "type" => "repeater", "show_in_graphql" => 1, "graphql_field_name" => "rows",
      "sub_fields" => [ [ "key" => "field_story_row_body", "label" => "Row body", "name" => "row_body", "type" => "wysiwyg", "show_in_graphql" => 1, "graphql_field_name" => "rowBody" ] ] ] ],
  "location" => [ [ [ "param" => "post_type", "operator" => "==", "value" => "post" ] ] ] ] );
acf_import_field_group( [ "key" => "group_term", "title" => "Term Fields", "show_in_graphql" => 1, "graphql_field_name" => "termFields", "graphql_types" => [ "Category" ],
  "fields" => [ $wysiwyg( "field_term_body" ) ],
  "location" => [ [ [ "param" => "taxonomy", "operator" => "==", "value" => "category" ] ] ] ] );
acf_import_field_group( [ "key" => "group_user", "title" => "User Fields", "show_in_graphql" => 1, "graphql_field_name" => "userFields", "graphql_types" => [ "User" ],
  "fields" => [ $wysiwyg( "field_user_body" ) ],
  "location" => [ [ [ "param" => "user_form", "operator" => "==", "value" => "all" ] ] ] ] );
'

# Values: a [gallery] with no ids (reads the global post) and an oEmbed URL on its own line
for P in $P1 $P2 $P3; do
  wp eval "update_field( 'field_story_body', \"<p>Post $P</p>\n[gallery]\", $P ); update_field( 'field_story_rows', [ [ 'field_story_row_body' => '[gallery]' ] ], $P );"
done
wp eval "update_field( 'field_term_body', '[gallery]', 'term_$CAT' ); update_field( 'field_user_body', '[gallery]', 'user_$U' );"
```

Options page. Persist it with an mu-plugin, because `acf_add_options_page()` must run on every request. In wp-env, put the file in a mapped mu-plugins dir:

```php
<?php // wp-content/mu-plugins/ext-matrix-acf-options.php
add_action( 'acf/init', function () {
	acf_add_options_page( [ 'page_title' => 'Site Settings', 'menu_slug' => 'site-settings', 'show_in_graphql' => true, 'graphql_type_name' => 'SiteSettings' ] );
	acf_add_local_field_group( [ 'key' => 'group_site', 'title' => 'Site Fields', 'show_in_graphql' => 1, 'graphql_field_name' => 'siteFields', 'graphql_types' => [ 'SiteSettings' ],
		'fields' => [ [ 'key' => 'field_site_body', 'label' => 'Body', 'name' => 'body', 'type' => 'wysiwyg', 'show_in_graphql' => 1, 'graphql_field_name' => 'body' ] ],
		'location' => [ [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'site-settings' ] ] ] ] );
} );
```

```bash
wp eval "update_field( 'field_site_body', \"<p>Options</p>\n[gallery]\nhttps://www.youtube.com/watch?v=dQw4w9WgXcQ\", 'options' );"
```

The root field is named from `graphql_type_name` through `Utils::get_field_group_name()` (`Utils.php:310-317`) and `Registry.php:415`, which should give `siteSettings`. Confirm it with introspection before running the queries.

Variables used below: `{"p1": P1, "p2": P2, "cat": CAT, "att": A1, "user": U}` (database IDs).

## Queries

(a) Single post node, wysiwyg with `[gallery]`. Expect P1's two images on both main and new.

```graphql
query A($p1: ID!) { post(id: $p1, idType: DATABASE_ID) { storyFields { body rows { rowBody } } } }
```

(b) List of three differing posts. Each `body` must show only that post's images (P3 shows unattached or none). Look for any cross-post bleed.

```graphql
query B { posts(first: 3, where: { orderby: { field: DATE, order: ASC } }) { nodes { databaseId storyFields { body rows { rowBody } } } } }
```

(c1) Nested: term > posts. On main, `category.termFields.body` shows whichever post leaked last. Under the new behavior it shows no post's gallery (unattached `AU` only). Each nested post shows its own gallery.

```graphql
query C1($cat: ID!) { category(id: $cat, idType: DATABASE_ID) { name termFields { body } posts { nodes { databaseId storyFields { body } } } } }
```

(c2) Nested: post > author > user field group. Expect P1's gallery inside `userFields.body` on both main and new (user setup stacked on post setup keeps `$post`).

```graphql
query C2($p1: ID!) { post(id: $p1, idType: DATABASE_ID) { author { node { databaseId userFields { body } } } } }
```

(d) Same node, ACF field before and after model fields. Expect identical `body` in both aliases on main and new, because ACF reads `databaseId` itself.

```graphql
query D($p1: ID!) { before: post(id: $p1, idType: DATABASE_ID) { storyFields { body } title databaseId } after: post(id: $p1, idType: DATABASE_ID) { title databaseId storyFields { body } } }
```

(e1) The #4572 shape: options page after a media item. On main, `siteSettings.siteFields.body` starts with `<p class="attachment">…` (prepend_attachment from the leaked attachment). Under the new behavior there is no prefix.

```graphql
query E1($att: ID!) { mediaItem(id: $att, idType: DATABASE_ID) { sourceUrl } siteSettings { siteFields { body } } }
```

(e2) Options page after a post. On main, `[gallery]` shows P1's images. Under the new behavior it shows unattached media. Swap the order (options page first) to see main's order dependence.

```graphql
query E2($p1: ID!) { post(id: $p1, idType: DATABASE_ID) { title } siteSettings { siteFields { body } } }
```

```graphql
query E2rev($p1: ID!) { siteSettings { siteFields { body } } post(id: $p1, idType: DATABASE_ID) { title } }
```

(e3) Term at root after a post. On main, the term's `[gallery]` shows P2's images (leaked). Under the new behavior it shows unattached media.

```graphql
query E3($p2: ID!, $cat: ID!) { post(id: $p2, idType: DATABASE_ID) { title } category(id: $cat, idType: DATABASE_ID) { termFields { body } } }
```

(e4) oEmbed cache side effect. After running E2 on each build, check where the cache was written: `wp post meta list $P1 --fields=meta_key | grep _oembed_` and `wp post list --post_type=oembed_cache --fields=ID,post_name`. On main the `_oembed_*` meta lands on P1. Under the new behavior it lands in `oembed_cache`. Between runs, delete the `_oembed_*` keys from P1 one at a time with `wp post meta delete $P1 <key>`, and run `wp post delete $(wp post list --post_type=oembed_cache --format=ids) --force`. Test site only.

(f) Mutations: none in this extension.
