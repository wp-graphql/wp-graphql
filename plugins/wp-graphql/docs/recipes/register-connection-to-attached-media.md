<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Register Connection to Attached Media"
wordpressUri: "/recipes/register-connection-to-attached-media/"
wordpressId: "2501"
group: "Custom Post Types"
summary: "This code shows how to register a connection to attached media in WPGraphQL add_action( 'graphql_register_types', function() { register_graphql_connection([ 'fromType' => 'ContentNode', 'toType' => 'MediaItem', 'fromFiel…"
---

This code shows how to register a [connection](https://www.wpgraphql.com/docs/connections/) to attached media in WPGraphQL

```
add_action( 'graphql_register_types', function() {

	register_graphql_connection([
		'fromType' => 'ContentNode',
		'toType' => 'MediaItem',
		'fromFieldName' => 'attachedMedia',
		'connectionArgs' => \WPGraphQL\Connection\PostObjects::get_connection_args(),
		'resolve' => function( \WPGraphQL\Model\Post $source, $args, $context, $info ) {
			$resolver = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver( $source, $args, $context, $info, 'attachment' );
			$resolver->set_query_arg( 'post_parent', $source->ID );
			return $resolver->get_connection();
		}
	]);

} );
```

You can then query for this new connection using the `attachedMedia` field on a ContentNode:

```
{
  contentNodes {
    nodes {
      ... on NodeWithTitle {
        title
      }
      attachedMedia {
        nodes {
          id
          title
        }
      }
    }
  }
}
```
