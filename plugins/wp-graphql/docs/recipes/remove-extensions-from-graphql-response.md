<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Remove Extensions from GraphQL Response"
wordpressUri: "/recipes/remove-extensions-from-graphql-response/"
wordpressId: "8496"
group: "Execution"
summary: "This snippet removes the &#8220;extensions&#8221; from the GraphQL response: add_filter( 'graphql_request_results', function( $response ) { // If it's an ExecutionResult object, we need to handle it differently if ( $res…"
---

This snippet removes the “extensions” from the GraphQL response:

```
add_filter( 'graphql_request_results', function( $response ) {
	// If it's an ExecutionResult object, we need to handle it differently
	if ( $response instanceof \GraphQL\Executor\ExecutionResult ) {
		// Convert to array and remove extensions if they exist
		$array = $response->toArray();
		if ( isset( $array['extensions'] ) ) {
			unset( $array['extensions'] );
		}
		return $array;
	}

	// Handle array responses
	if ( is_array( $response ) && isset( $response['extensions'] ) ) {
		unset( $response['extensions'] );
	}

	// Handle object responses
	if ( is_object( $response ) && isset( $response->extensions ) ) {
		unset( $response->extensions );
	}

	return $response;
}, 99, 1 );
```

## Before

![](https://content.wpgraphql.com/wp-content/uploads/2021/07/Screen-Shot-2021-07-13-at-10.44.00-AM.png)

## After

![](https://content.wpgraphql.com/wp-content/uploads/2021/07/Screen-Shot-2021-07-13-at-10.44.30-AM.png)
