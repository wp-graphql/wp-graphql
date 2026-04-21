<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Debugging JWT Authentication"
wordpressUri: "/recipes/debugging-jwt-authentication/"
wordpressId: "2353"
group: "WP Admin"
summary: "This snippet outputs the $_SERVER superglobal so we can see if the Authorization token is being passed to the server or not. add_action( 'init', function() { if ( is_graphql_http_request() ) { wp_send_json( [ 'server' =>…"
---

This snippet outputs the `$_SERVER` superglobal so we can see if the Authorization token is being passed to the server or not.

```
add_action( 'init', function() {

	if ( is_graphql_http_request() ) {
		wp_send_json( [ 'server' => $_SERVER ] );
	}

} );
```
