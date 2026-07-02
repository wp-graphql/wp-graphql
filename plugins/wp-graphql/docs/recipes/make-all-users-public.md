<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Make all Users Public"
wordpressUri: "/recipes/make-all-users-public/"
wordpressId: "7823"
group: "Authorization"
summary: "The following snippets allow for Users with no published content to be shown in public (non-authenticated) WPGraphQL query results. For a more detailed write-up, read the blog post: Allowing WPGraphQL to show unpublished…"
---

The following snippets allow for Users with no published content to be shown in public (non-authenticated) WPGraphQL query results.

For a more detailed write-up, read the blog post: [Allowing WPGraphQL to show unpublished authors in User Queries](https://www.wpgraphql.com/2020/12/11/allowing-wpgraphql-to-show-unpublished-authors-in-user-queries/)

```
add_filter( 'graphql_connection_query_args', function( $query_args, $connection_resolver ) {

  if ( $connection_resolver instanceof \WPGraphQL\Data\Connection\UserConnectionResolver ) {
    unset( $query_args['has_published_posts'] );
  }

  return $query_args;

}, 10, 2 );
```

```
add_filter( 'graphql_object_visibility', function( $visibility, $model_name, $data, $owner, $current_user ) {

  // only apply our adjustments to the UserObject Model
  if ( 'UserObject' === $model_name ) {
    $visibility = 'public';
  }

  return $visibility;

}, 10, 5 );
```
