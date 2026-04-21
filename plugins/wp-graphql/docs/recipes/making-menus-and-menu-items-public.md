<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Making Menus and Menu Items public"
wordpressUri: "/recipes/making-menus-and-menu-items-public/"
wordpressId: "7729"
group: "Authorization"
summary: "By default, Menus and Menu Items that are not assigned to a Menu Location are considered private, meaning they are not exposed in non-authenticated WPGraphQL Queries. If you want to expose Menus and Menu Items that are n…"
---

By default, Menus and Menu Items that are not assigned to a Menu Location are considered private, meaning they are not exposed in non-authenticated WPGraphQL Queries.  
  
If you want to expose Menus and Menu Items that are not assigned to menu locations to public GraphQL Queries, you can use the following snippet:

```
add_filter( 'graphql_data_is_private', function( $is_private, $model_name, $data, $visibility, $owner, $current_user ) {

	if ( 'MenuObject' === $model_name || 'MenuItemObject' === $model_name ) {
		return false;
	}
	
	return $is_private;

}, 10, 6 );
```
