<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Add Edit Link to All Post Types"
wordpressUri: "/recipes/add-edit-link-to-all-post-types/"
wordpressId: "7712"
group: "Custom Fields"
summary: "The following snippet shows how to add the &#8220;Edit&#8221; link as a GraphQL field to all post types: add_action( 'graphql_register_types', function() { register_graphql_field( 'ContentNode', 'editLink', [ 'type' => '…"
---

The following snippet shows how to add the “Edit” link as a GraphQL field to all post types:

```
add_action( 'graphql_register_types', function() {

	register_graphql_field( 'ContentNode', 'editLink', [
		'type' => 'String',
		'description' => __( 'Link to edit the content', 'your-textdomain' ),
		'resolve' => function( \WPGraphQL\Model\Post $post, $args, $context, $info ) {
		   return get_edit_post_link( $post->databaseId );
		}
	]);

} );
```

This could then be queried like so:

![Screenshot of a GraphQL Query for posts with their editLink](https://content.wpgraphql.com/wp-content/uploads/2020/12/Screen-Shot-2020-12-01-at-8.02.58-AM-1024x409.png)

Screenshot of a GraphQL Query for posts with their editLink
