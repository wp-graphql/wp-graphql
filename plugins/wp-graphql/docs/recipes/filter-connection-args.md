<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Filter Connection Args"
wordpressUri: "/recipes/filter-connection-args/"
wordpressId: "2350"
group: "Uncategorized"
summary: "This filters connection args by checking the field name the connection is coming from as well as the type the connection is coming from. add_filter( 'graphql_connection_query_args', function( $args, \\WPGraphQL\\Data\\Conne…"
---

This filters connection args by checking the field name the connection is coming from as well as the type the connection is coming from.

```
add_filter( 'graphql_connection_query_args', function( $args, \WPGraphQL\Data\Connection\AbstractConnectionResolver $connection ) {

	// Get the Info from the resolver
	$info = $connection->getInfo();

	// Get the field name being queried
	$field_name = $info->fieldName;

	// If the resolver isn't for the children field, return the $args and move on
	if ( 'children' !== $field_name ) {
		return $args;
	}

	// Get the context
	$context = $connection->getContext();

	// Get the args that were input for the query. ex: first/last/after/before/where
	$input_args = $connection->getArgs();

	// If the field had orderby arguments input, respect them. Return the $args as-is.
	if ( isset( $input_args['where']['orderby'] ) ) {
		return $args;
	}

	// Determine if the parent type implements the ContentNode interface
	$parent_implements_content_node = $info->parentType->implementsInterface( $context->type_registry->get_type( 'ContentNode' ) );

	// If the parent type implements ContentNode, this means the `children` field is a connection
	// from a ContentNode to children (as a pose to menuItem children, Term children, etc)
	// So we will want to modify the args being sent to WP_Query
	if ( $parent_implements_content_node ) {
		// modify the args here.
		// the $args are what get passed to WP_Query, so make sure they're formatted
		// for WP_Query.
		$args['orderby'] = [ 'menu_order' ];
		$args['order'] = 'ASC';
	}
	return $args;

}, 10, 2 );
```
