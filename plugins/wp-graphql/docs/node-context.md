---
uri: "/docs/node-context/"
title: "Node Context and Global State"
---

Many WordPress functions don't take an ID: they read "the current" post, term or user from global state. Template tags like `get_the_ID()` and `get_the_title()`, conditional tags like `is_singular()` and `is_category()`, `get_queried_object()`, `render_block()`, and the filters many plugins hook into all work this way.

So that this code behaves as it would on the object's own page, WPGraphQL sets up a post, term or user as the current object while its part of a query resolves. This page explains what is set up, which fields see it, and how extensions can build on it.

## What gets set up

| Node | What WordPress sees |
| --- | --- |
| Post (any post type) | The main query is parsed for the post, so `is_singular()`, `get_queried_object()` and related conditional tags describe it. The global `$post` and post data are set up, which also fires the `the_post` action. |
| Term | The main query is parsed for the term's archive, so `is_category()`, `is_tag()`, `is_tax()` and `get_queried_object()` describe it. |
| User | The main query is parsed for the user's author archive, so `is_author()` and `get_queried_object()` describe it, and `$authordata` is the user. |

Models that extend Post, Term or User (for example, a product modeled on Post) get the same setup.

## Which fields see it

A node's context applies to **every field selected under it** in the query, and to nothing outside it.

```graphql
{
  post(id: "hello-world", idType: SLUG) {
    title              # the post is the current object
    seo {              # so are fields other plugins register on the post...
      fullHead
    }
    editorBlocks {     # ...and fields of nested values that aren't nodes themselves
      renderedHtml
    }
    author {
      node {
        name           # the author is the current object here (set up on top of the post)
      }
    }
    excerpt            # the post again
  }
  generalSettings {
    title              # outside any node: the state the request started with
  }
}
```

In detail:

- A node is set up before the first field under it resolves, and torn down when resolution moves outside it. The global state from before is restored at that point, so a field outside the node never sees a node it isn't part of.
- Nodes nest. Under a post's author, the user is set up on top of the post, and the post's later fields get the post back.
- If a field leaves the global `$post`, `$authordata` or the queried object pointing somewhere else (for example, by running a loop over other posts and not resetting), the node is set up again before its next field. That field and the ones after it see the node, not whatever the earlier field left behind.
- When the request finishes, the global state is what it was before the request. This also holds for a GraphQL request made from PHP in the middle of rendering a page, for example with `graphql()` in a shortcode or template.

Earlier versions of WPGraphQL set up the object before each of the node's own fields, and never restored it, so the last object resolved stayed "current" for the rest of the request. Code that relied on that leftover state outside the node now sees the request's original state. See [#4572](https://github.com/wp-graphql/wp-graphql/issues/4572).

## For extension authors

### Fields you register on node types

A field you register with `register_graphql_field()` on `Post`, `Category`, `User` or any other type backed by those models resolves with the node set up, as do the fields of any objects it returns. You can call WordPress functions that read the current object, and filters hooked into them see the right object.

For the object's own data, keep reading from the source, which is always the right object:

```php
register_graphql_field( 'Post', 'readingTime', [
	'type'    => 'Int',
	'resolve' => static function ( \WPGraphQL\Model\Post $post ) {
		// Prefer the source for the post itself...
		$content = get_post_field( 'post_content', $post->databaseId );

		// ...and rely on the node context for code that reads the current post, such as
		// `the_content` filters added by other plugins.
		$content = apply_filters( 'the_content', $content );

		return (int) ceil( str_word_count( wp_strip_all_tags( $content ) ) / 200 );
	},
] );
```

Don't use the current object to decide _what_ a field returns, such as limiting a nested connection's results to the ones related to a post a few levels up the query. The same node can be reached by different paths, and clients that cache by ID (like Apollo) expect its fields to be the same wherever it appears. Build results from the field's source and arguments, and use the node context for WordPress functions and filters that need a current object. WPGraphQL itself once limited a category's `children` to the ones assigned to an ancestor post this way, and removed it for this reason ([#887](https://github.com/wp-graphql/wp-graphql/issues/887)).

### Deferred resolvers

The context is set up before each field's resolver runs. Code that runs later, in the callback of a `Deferred` the resolver returns, runs after other fields (often other nodes' fields) have resolved, so by then a different node may be set up.

To have a callback see the node its field is selected under, wrap it with `graphql_bind_node_context()` while the resolver runs. The wrapped callback runs with the global state as it was when it was wrapped, and the state at the time it's called is restored afterwards:

```php
register_graphql_field( 'Post', 'relatedSummary', [
	'type'    => 'String',
	'resolve' => static function ( \WPGraphQL\Model\Post $post ) {
		return new \GraphQL\Deferred(
			graphql_bind_node_context(
				static function () {
					// Runs later, but sees the post this field is selected under.
					return my_plugin_related_summary( get_the_ID() );
				}
			)
		);
	},
] );
```

WPGraphQL does this for its own connections, so filters a connection runs while it loads its nodes (`graphql_connection_nodes`, `graphql_connection_edges` and the like) see the node the connection belongs to.

DataLoader batches are different: a batch loads keys requested from many nodes at once, so its callbacks don't belong to any one node. Don't rely on the current object in a loader; work from the keys and the data they load.

### Restoring more globals

Setting up a post fires the `the_post` action, and some plugins set their own globals there. When resolution leaves the node, WPGraphQL restores the main query and the post data globals (`$post`, `$id`, `$authordata`, `$page`, `$pages`, `$multipage`, `$more`, `$numpages`, `$currentday`, `$currentmonth`), but it doesn't know about globals other plugins set. Add them with the `graphql_model_node_context_globals` filter so they're restored too:

```php
add_filter(
	'graphql_model_node_context_globals',
	static function ( array $globals, \WPGraphQL\Model\Model $model ): array {
		// WooCommerce sets the global $product on `the_post`.
		if ( $model instanceof \WPGraphQL\Model\Post ) {
			$globals[] = 'product';
		}

		return $globals;
	},
	10,
	2
);
```

The filter receives the model about to be set up, so you can add a global only for the models that change it.

### Custom models

If your extension has its own model (a class extending `WPGraphQL\Model\Model`), its `setup()` and `tear_down()` run in one of two ways:

- **Around each field (the default).** `setup()` runs before each of the model's fields is read and `tear_down()` after, then the main query and the restored globals are put back. Use this when `setup()` expects to run before every field.
- **For the node's part of the query.** Return `true` from `scopes_setup_to_node()` and the model is treated like Post, Term and User: set up once when resolution enters its part of the query, seen by every field under it (including fields other plugins register on its type and nested values), and torn down when resolution leaves it.

A hypothetical course model from an LMS plugin, whose template functions read a global "current course":

```php
class Course extends \WPGraphQL\Model\Model {

	public function scopes_setup_to_node(): bool {
		return true;
	}

	public function setup() {
		// The plugin's template functions, such as my_lms_get_course_progress(),
		// read the current course from this global.
		$GLOBALS['my_lms_current_course'] = $this->data;
	}

	// ...the constructor and init() as for any model.
}

// Restore the global when resolution leaves the course, instead of writing a tear_down().
add_filter(
	'graphql_model_node_context_globals',
	static function ( array $globals, \WPGraphQL\Model\Model $model ): array {
		if ( $model instanceof Course ) {
			$globals[] = 'my_lms_current_course';
		}

		return $globals;
	},
	10,
	2
);
```

`tear_down()` is still the place to undo anything that isn't a global, such as filters `setup()` adds.

## Ideas for existing extensions

These are illustrations of how some widely used extensions could build on node context, based on reading their source in October 2026. They are pseudo-code, not prescriptions, and not recommendations from those projects' maintainers. An extension that adopts any of them needs to keep working on WPGraphQL versions without node context.

**Restoring `$product` (WPGraphQL WooCommerce).** WooCommerce sets the global `$product` on `the_post` (`wc_setup_product_data()`), so it's set whenever WPGraphQL sets up a product, but nothing resets it when resolution leaves the product. Adding `product` with the filter above would mean fields outside a product never see a stale `$product`, and any field under a product, including nested objects, could call WooCommerce template functions that read it.

**Less duplicated setup (WPGraphQL for Rank Math).** The extension's SEO models repeat the matching core model's `setup()` before adding their own state. `ContentNodeSeo` repeats the Post model's setup almost line for line (the `parse_query()` call for each post type, the global `$post`, the queried object). `TermNodeSeo` repeats the Term model's and also adds a filter for Rank Math's replacement variables. `UserSeo` repeats the User model's and also sets the global `$author`, which core doesn't. The `seo` field is resolved from its node, so the node is already set up when an SEO model's fields resolve, and only the additions are still needed. Pseudo-code:

```php
// TermNodeSeo, sketched for WPGraphQL versions with node context.
public function setup(): void {
	// Rank Math's own addition, still needed.
	add_filter( 'rank_math/replacements/non_cacheable', [ $this, 'non_cacheable_replacements' ] );

	// No parse_query(), global $post or queried object here: the term this SEO object
	// belongs to is already set up as the current object, because `seo` is selected under it.
	parent::setup();
}
```

`UserSeo` would keep setting `$author` (and could add `author` to the restored globals with the filter instead of restoring it in `tear_down()`). The models would still be set up around each of their fields (they don't need `scopes_setup_to_node()`), because the parent `setup()` resets Rank Math's head output before each field. Reading an SEO model's fields from anywhere other than under its node would need the old setup, so this only fits code paths that always resolve the model through the `seo` field.

**Correct results without changes (Faust, WPGraphQL Content Blocks).** Some extensions already read the current object and get the right one now with no code change:

- Faust's `templates` field works out a node's template hierarchy from the main query's conditional tags.
- Content Blocks renders blocks with `render_block()`, which reads the global `$post`, and its Post Terms block falls back to `get_the_ID()`.

Under node context those always describe the node the field is selected under, including in lists and nested queries, where earlier versions could see the previous node.
