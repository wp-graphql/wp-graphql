---
uri: "/docs/node-context/"
title: "Node Context and Global State"
---

Many WordPress functions don't take an ID: they read "the current" post, term or user from global state. Template tags like `get_the_ID()` and `get_the_title()`, conditional tags like `is_singular()` and `is_category()`, `get_queried_object()`, `render_block()`, and the filters many plugins hook into all work this way.

So that this code behaves as it would on the object's own page, WPGraphQL sets up a post, term or user as the current object while its part of a query resolves. This page explains what is set up, which fields see it, and how extensions can build on it.

> **New in WPGraphQL 2.27.0:** each post, term or user is the current object for every field selected under it, and for nothing outside it. Values that depend on the current object (some SEO, template, block and gallery output, for example) now come from the right node, including in lists, where they could come from a different one before. If a response changed after upgrading, see [Troubleshooting](#troubleshooting-after-upgrading).

## What gets set up

| Node | What WordPress sees |
| --- | --- |
| Post (any post type) | The main query is parsed for the post, so `is_singular()`, `get_queried_object()` and related conditional tags describe it. The global `$post` and post data are set up, which also fires the `the_post` action. Previews and revisions set up the post they belong to. |
| Term | The main query is parsed for the term's archive, so `is_category()`, `is_tag()`, `is_tax()` and `get_queried_object()` describe it. |
| User | The main query is parsed for the user's author archive, so `is_author()` and `get_queried_object()` describe it, and `$authordata` is the user. |

Models that extend Post, Term or User (for example, a product modeled on Post) get the same setup. Other types, such as comments, menu items, menus, content types and taxonomies, don't set anything up: their fields see the nearest post, term or user above them in the query, or the request's own state if there isn't one.

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
- If a field leaves the global `$post`, `$authordata` or the queried object pointing somewhere else (for example, by running a loop over other posts and not resetting), the node is set up again before its next field.
- A mutation's resolver runs with the request's own state. The nodes in its payload are set up like any other.
- A post, term or user the requester isn't allowed to see is never set up.
- When the request finishes, the global state is what it was before the request, including for hooks that run on the response. This also holds for a GraphQL request made from PHP in the middle of rendering a page, for example with `graphql()` in a shortcode or template.

Before WPGraphQL 2.27.0, the object was set up before each of the node's own fields and never restored, so the last object resolved stayed "current" for the rest of the request. Code that relied on that leftover state outside the node now sees the request's original state. See [#4572](https://github.com/wp-graphql/wp-graphql/issues/4572).

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

### Root fields

A field on `RootQuery` isn't selected under any node, so it sees the request's own state, even when it's selected next to a node. Before 2.27.0, a root field resolved after a node could see that node as the current object by accident. If a root field needs a post's, term's or user's context:

- register it on the node's type instead (for example on `ContentNode` or `UniformResourceIdentifiable`, which `nodeByUri` returns), so it's selected under the node, or
- give it an `id` or `uri` argument and work from that.

The same applies to `registeredScripts` and `registeredStylesheets` on `RootQuery`: they describe the request, not a node. For a node's assets, select its `enqueuedScripts` and `enqueuedStylesheets` fields.

### Deferred resolvers

The context is set up before each field's resolver runs. Code that runs later, in the callback of a `Deferred` (or a promise's `then()`) the resolver returns, runs after other fields (often other nodes' fields) have resolved, so by then a different node may be set up.

To have a callback see the node its field is selected under, wrap it with `graphql_bind_node_context()` in the resolver itself, while it runs. The wrapped callback runs with the global state as it was when it was wrapped, and the state at the time it's called is restored afterwards:

```php
register_graphql_field( 'Post', 'relatedSummary', [
	'type'    => 'String',
	'resolve' => static function ( \WPGraphQL\Model\Post $post, $args, \WPGraphQL\AppContext $context ) {
		$related_id = (int) get_post_meta( $post->databaseId, 'related_post', true );

		return $context->get_loader( 'post' )->load_deferred( $related_id )->then(
			graphql_bind_node_context(
				static function ( $related ) {
					// Runs after the related post loads, but sees the post this field is
					// selected under as the current one.
					return my_plugin_related_summary( get_the_ID(), $related );
				}
			)
		);
	},
] );
```

Wrap every callback in the chain that needs the context: a callback the wrapped one returns, or a later `then()`, isn't wrapped by it. Binding restores the global state (the main query and the restored globals), not other side effects of a model's `setup()`, such as filters it adds.

WPGraphQL does this for its own connections, so filters a connection runs while it loads its nodes (`graphql_connection_nodes`, `graphql_connection_edges` and the like) see the node the connection belongs to.

DataLoader batches are different: a batch loads keys requested from many nodes at once, so its callbacks don't belong to any one node. Don't rely on the current object in a loader; work from the keys and the data they load.

### Restoring more globals

Setting up a post fires the `the_post` action, and some plugins set their own globals there. When resolution leaves a node, WPGraphQL restores the main query and the post data globals (`$post`, `$id`, `$authordata`, `$page`, `$pages`, `$multipage`, `$more`, `$numpages`, `$currentday`, `$currentmonth`), but it doesn't know about globals other plugins set. Add them with the `graphql_model_node_context_globals` filter so they're restored too:

```php
add_filter(
	'graphql_model_node_context_globals',
	static function ( $globals, $model ) {
		// WooCommerce sets the global $product on `the_post`.
		if ( is_array( $globals ) && $model instanceof \WPGraphQL\Model\Post ) {
			$globals[] = 'product';
		}

		return $globals;
	},
	10,
	2
);
```

The filter receives the model being set up (or the current node, when a callback is being bound), so you can add a global only for the models that change it. It runs each time a node is set up, so keep callbacks quick and free of side effects. Globals that hold WordPress's own infrastructure, such as `$wpdb`, `$wp_filter` and `$current_user`, are never saved or restored, even if they're added.

### Custom models

If your extension has its own model (a class extending `WPGraphQL\Model\Model`), its `setup()` and `tear_down()` run in one of two ways:

- **Around each field (the default).** `setup()` runs before each of the model's fields is read and `tear_down()` after, then the main query and the restored globals are put back. Use this when `setup()` expects to run before every field.
- **For the node's part of the query.** Return `true` from `is_node_scoped()` and the model is treated like Post, Term and User: set up once when resolution enters its part of the query, seen by every field under it (including fields other plugins register on its type and nested values), and torn down when resolution leaves it. Its fields read anywhere else, such as from PHP, are still set up around each read.

A hypothetical course model from an LMS plugin, whose template functions read a global "current course":

```php
class Course extends \WPGraphQL\Model\Model {

	/**
	 * @return bool
	 */
	public function is_node_scoped() {
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
	static function ( $globals, $model ) {
		if ( is_array( $globals ) && $model instanceof Course ) {
			$globals[] = 'my_lms_current_course';
		}

		return $globals;
	},
	10,
	2
);
```

A few things to know when you opt in:

- **Subclasses inherit it.** Models that extend Post, Term or User are node-scoped too. If your subclass's `setup()` has to run before every field, return `false` from `is_node_scoped()`.
- **Setups nest.** Another node's `setup()` can run while yours is in effect (a course's author, for example), and `tear_down()` runs in the reverse order. Don't rely on `setup()` and `tear_down()` running once at a time, for example by adding and removing a filter with a shared callback.
- **`tear_down()` is for everything else.** Use it to undo changes that aren't globals, such as filters `setup()` adds.
- **Identifiers skip the setup.** A node-scoped model's `id`, `ID` and `databaseId` fields are read without setting the model up, because they're worked out from its data and are read often (the query analyzer reads every node's ID).

### Supporting older WPGraphQL versions

To support versions before 2.27.0 too, check for the new function before using it:

```php
$callback = static function () {
	return get_the_ID();
};

if ( function_exists( 'graphql_bind_node_context' ) ) {
	$callback = graphql_bind_node_context( $callback );
}
```

Overriding `is_node_scoped()` and adding the `graphql_model_node_context_globals` filter are harmless on older versions, which never call either.

## Extensions that benefit with no changes

These extensions read the current object in their own fields, so they get the right object with no code changes, including in lists and nested queries where earlier versions could hand them a different node:

- **Faust:** `templates` (each node's template hierarchy) and `conditionalTags`.
- **WPGraphQL Content Blocks:** blocks rendered with `render_block()`, and the Post Terms block's fallback to the current post.
- **WPGraphQL for ACF:** WYSIWYG fields whose content uses the current post, such as a `[gallery]` without IDs, including on options pages, terms and users.
- **WPGraphQL for Yoast SEO:** SEO output for a node, such as a taxonomy term's canonical URL.

These were checked by running the same queries against WPGraphQL with and without this change. The queries, results, setup scripts and some ideas for how extensions could build on node context are on the [`compat/node-scoped-context`](https://github.com/wp-graphql/wp-graphql/tree/compat/node-scoped-context) branch.

## Troubleshooting after upgrading

If a response changed after upgrading to 2.27.0:

- **Compare the same query on 2.26.x and 2.27.0.** The change only affects values that depend on the current post, term or user.
- **Check whether the old value depended on order.** Select the field on its own, or move it before or after the node's other fields, on 2.26.x. If the value changed with the order, or with an item's position in a list, it came from a different node, and the 2.27.0 value is the correct one.
- **Look for custom resolvers that read global state** (`get_the_ID()`, `global $post`, `get_queried_object()`, `is_singular()`, `is_tax()` and similar) in root fields, `Deferred` callbacks and DataLoaders. See [Root fields](#root-fields) and [Deferred resolvers](#deferred-resolvers).
- **Purge cached responses**, in WPGraphQL Smart Cache, your CDN and your front end (for example, Next.js incremental static regeneration). Otherwise corrected values only appear as cached responses expire or content changes.
- **Rolling back is safe.** This change adds no schema or database changes, so you can return to 2.26.x while you investigate.
