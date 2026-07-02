<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Page Siblings Connection"
wordpressUri: "/recipes/page-siblings-connection/"
wordpressId: "833239"
group: "Uncategorized"
summary: "Below is a connection that adds the ability to query page siblings (i.e. pages that share the same parent) add_action( 'graphql_register_types', function() { register_graphql_connection( [ 'fromType' => 'Page', 'toType'…"
---

Below is a connection that adds the ability to query page siblings (i.e. pages that share the same parent)

```
add_action( 'graphql_register_types', function() {

	register_graphql_connection( [
		'fromType' => 'Page',
		'toType' => 'Page',
		'connectionTypeName' => 'PageSiblings',
		'fromFieldName' => 'siblings',
		'resolve' => function( $page, $args, $context, $info ) {
			$parent = $page->parentDatabaseId ?? null;

			if ( ! $parent ) {
				return null;
			}

			$resolver = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver( $page, $args, $context, $info );
			$resolver->set_query_arg( 'post_parent', $parent );
			$resolver->set_query_arg( 'post__not_in', $page->databaseId );
			return $resolver->get_connection();
		}
	]);

} );
```
