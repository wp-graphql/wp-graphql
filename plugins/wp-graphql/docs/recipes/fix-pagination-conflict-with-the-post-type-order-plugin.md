<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Fix pagination conflict with the “Post Type Order” plugin"
wordpressUri: "/recipes/fix-pagination-conflict-with-the-post-type-order-plugin/"
wordpressId: "832717"
group: "Uncategorized"
summary: "When using the Post Type Order plugin along with WPGraphQL, you might experience some issues with paginated queries. This snippet should help correct the conflict: add_action( 'pre_get_posts', function () { // access the…"
---

When using the Post Type Order plugin along with WPGraphQL, you might experience some issues with paginated queries.

This snippet should help correct the conflict:

```
add_action( 'pre_get_posts', function () {

	// access the custom post type order class
	global $CPTO;

	// if WPGraphQL isn't active or CPTUI isn't active, carry on and bail early
	if ( ! function_exists( 'is_graphql_request' ) || ! is_graphql_request() || ! $CPTO ) {
		return;
	}

	// Remove the Post Type Order plugin's "posts_orderby" filter for WPGraphQL requests
	// This filter hooks in too late and modifies the SQL directly so WPGraphQL
	// can't properly map the orderby args to generate the SQL for proper pagination
	remove_filter( 'posts_orderby', [ $CPTO, 'posts_orderby' ], 99 );

	// Add a filter
	add_filter( 'graphql_post_object_connection_query_args', function ( $args, $source, $input_args, $context, $info ) {

		$orderby = [];

		// If the connection has explicit orderby arguments set,
		// use them
		if ( ! empty( $input_args['where']['orderby'] ) ) {
			return $args;
		}

		// Else use any orderby args set on the WP_Query
		if ( isset( $args['orderby'] ) ) {
			$orderby = [];

			if ( is_string( $args['orderby'] ) ) {
				$orderby[] = $args['orderby'];
			} else {
				$orderby = $args['orderby'];
			}
		}

		$orderby['menu_order'] = ! empty( $input_args['last'] ) ? 'DESC' : 'ASC';
		$args['orderby']       = $orderby;

		return $args;
	}, 10, 5 );

});
```

## What this snippet does:

First, this hooks into `pre_get_posts` which fires when WP\_Query is executing.

This then checks to see if the request is a GraphQL request and whether Post Types Order plugin class is available as a global. If these conditions are not met, nothing happens. If these conditions are met, we carry on.

Next, we remove the “posts\_orderby” filter from the Post Types Order plugin, as it was overriding WPGraphQL’s ordering which is needed for cursor pagination to work properly.

Then, we add our own filter back to WPGraphQLs Post Object Connection query args and set the orderby to be `menu_order => 'ASC'`
