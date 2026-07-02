<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Log WPGraphQL requests to error log"
wordpressUri: "/recipes/log-wpgraphql-requests-to-error-log/"
wordpressId: "2434"
group: "WP Admin"
summary: "This snippet logs all WPGraphQL requests to the error log add_action( 'do_graphql_request', function( $query, $operation, $variables, $params) { error_log( wp_json_encode( [ 'query' => $query, 'operation' => $operation,…"
---

This snippet logs all WPGraphQL requests to the error log

```
add_action( 'do_graphql_request', function( $query, $operation, $variables, $params) {
	error_log( wp_json_encode( [
		'query' => $query,
		'operation' => $operation,
		'variables' => $variables,
		'params' => $params
	] ) );
}, 10, 4 );
```
