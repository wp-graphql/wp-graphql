<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Popular Posts"
wordpressUri: "/recipes/popular-posts/"
wordpressId: "2329"
group: "Custom Post Types"
summary: "The following code allows you to query for popular posts. It&#8217;s still up to you to determine the best way to store popular posts, but this example assumes a meta_key is involved. Beware though, meta queries can be e…"
---

The following code allows you to query for popular posts. It’s still up to you to determine the best way to store popular posts, but this example assumes a meta\_key is involved. Beware though, meta queries can be expensive!

```
add_action( 'graphql_register_types', function() {

	// This registers a connection to the Schema at the root of the Graph
	// The connection field name is "popularPosts"
	register_graphql_connection( [
		'fromType'           => 'RootQuery',
		'toType'             => 'Post',
		'fromFieldName'      => 'popularPosts', // This is the field name that will be exposed in the Schema to query this connection by
		'connectionTypeName' => 'RootQueryToPopularPostsConnection',
		'connectionArgs'     => \WPGraphQL\Connection\PostObjects::get_connection_args(), // This adds Post connection args to the connection
		'resolve'            => function( $root, $args, \WPGraphQL\AppContext $context, $info ) {

			$resolver = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver( $root, $args, $context, $info );

			// Note, these args will override anything the user passes in as { where: { ... } } args in the GraphQL Query
			$resolver->set_query_arg( 'meta_key', 'wpb_post_views_count' );
			$resolver->set_query_arg( 'orderby', 'meta_value_num' );
			$resolver->set_query_arg( 'order', 'DESC' );

			return $resolver->get_connection();
		}
	] );

	// This registers a field to the "Post" type so we can query the "viewCount" and see the value of which posts have the most views
	register_graphql_field( 'Post', 'viewCount', [
		'type'    => 'Int',
		'resolve' => function( $post ) {
			return get_post_meta( $post->databaseId, 'wpb_post_views_count', true );
		}
	] );

} );
```

You can query for the popular posts using this GraphQL query:

```
{
  popularPosts(first: 10, where: {dateQuery: {after: {year: 2021, month: 10}}}) {
    nodes {
      id
      title
      date
      viewCount
    }
  }
}
```

![](https://content.wpgraphql.com/wp-content/uploads/2021/11/Screen-Shot-2021-11-11-at-9.09.22-AM-1024x638.png)
