<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Update WPGraphQL Endpoint URL"
wordpressUri: "/recipes/update-wpgraphql-endpoint-url/"
wordpressId: "2462"
group: "WP Admin"
summary: "You can modify the WPGraphQL endpoint in code with the following: function my_new_graphql_endpoint() { return 'cutepuppies'; }; add_filter( 'graphql_endpoint', 'my_new_graphql_endpoint' ); This will change the graphql en…"
---

You can modify the WPGraphQL endpoint in code with the following:

```
function my_new_graphql_endpoint() {
  return 'cutepuppies';
};

add_filter( 'graphql_endpoint', 'my_new_graphql_endpoint' );
```

This will change the graphql endpoint url from `/graphql` to `/cutepuppies`

This also updates the WPGraphQL settings page:

![WPGraphQL Settings page with updated endpoint url](https://content.wpgraphql.com/wp-content/uploads/2020/10/WPGraphQLEndpoint-1024x196.jpg)
