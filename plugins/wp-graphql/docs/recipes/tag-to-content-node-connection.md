<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Tag to Content Node Connection"
wordpressUri: "/recipes/tag-to-content-node-connection/"
wordpressId: "2420"
group: "Custom Post Types"
summary: "The following code registers a connection from Tags to ContentNodes. A field name called contentNodes will be added to the Tag type to make it easy to view all Posts that are tagged with that specific term. add_action( '…"
---

The following code registers a connection from Tags to ContentNodes. A field name called contentNodes will be added to the Tag type to make it easy to view all Posts that are tagged with that specific term.

```
add_action( 'graphql_register_types', function() {
	register_graphql_connection([
		'fromType' => 'Tag',
		'toType' => 'ContentNode',
		'fromFieldName' => 'contentNodes',
		'resolve' => function( \WPGraphQL\Model\Term $source, $args, $context, $info ) {
			// Get all post types allowed in GraphQL
			$post_types = WPGraphQL::get_allowed_post_types();

			// Instantiate a new PostObjectConnectionResolver class
			$resolver = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver( $source, $args, $context, $info, $post_types );

			// Set the argument that will be passed to WP_Query. We want only Posts (of any post type) that are tagged with this Tag's ID
			$resolver->set_query_arg( 'tag_id', $source->term_id );

			// Return the connection
			return $resolver->get_connection();
		}
	]);
} );
```
