<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Fix pagination conflict with the “Advanced Taxonomy Terms Order” plugin"
wordpressUri: "/recipes/fix-pagination-conflict-with-the-advanced-taxonomy-terms-order-plugin/"
wordpressId: "832722"
group: "Uncategorized"
summary: "When using the Advanced Taxonomy Terms Order plugin along with WPGraphQL, you might experience some issues with paginated queries. This snippet should help correct the conflict: add_action( 'pre_get_terms', function() {…"
---

When using the [Advanced Taxonomy Terms Order](https://wordpress.org/plugins/taxonomy-terms-order/) plugin along with WPGraphQL, you might experience some issues with paginated queries.

This snippet should help correct the conflict:

```
add_action( 'pre_get_terms', function() {

	// if WPGraphQL isn't active or CPTUI isn't active, carry on and bail early
	if ( ! function_exists( 'is_graphql_request' ) || ! is_graphql_request() || ! function_exists( 'TO_activated' ) ) {
		return;
	}

        // Remove the terms_clauses filter from the Term Order plugin
        // as this filter runs after WPGraphQL has determined how
        // to resolve paginated requests with order arguments applied
	remove_filter( 'terms_clauses', 'TO_apply_order_filter', 2 );

        // Set the query order and orderby to term_order
        // to match the intent of the Term Order plugin
	add_filter( 'graphql_term_object_connection_query_args', function( $query_args, $source, $input_args, $context, $info ) {

		$query_args['orderby'] = ! empty( $input_args['orderby'] ) ? $input_args['orderby'] : 'term_order';
		$query_args['order'] = ! empty( $input_args['order'] ) ? $input_args['order'] : ( ! empty( $input_args['last'] ) ? 'DESC' : 'ASC' );

		return $query_args;

	}, 10, 5 );

});
```

## What this snippet does:

First, this hooks into `pre_get_terms` which fires when WP\_Term\_Query is executing to get Terms out of the database.

This checks to see if the request is a GraphQL request and whether Term Order plugin is active. If these conditions are _not_ met, nothing happens.

If these conditions are met, we carry on.

Next, we remove the “terms\_clauses” filter from the Terms Order plugin, as it modifies the SQL statement directly, after WPGraphQL has determined how to map order args to SQL.

Then, we add our own filter back to WPGraphQLs Term Object Connection query args and set the order and orderby arguments here. This ensures that the order will be properly mapped to WPGraphQLs cursor pagination logic.
