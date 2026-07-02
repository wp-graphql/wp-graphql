<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Changing the Server Debug Flag"
wordpressUri: "/recipes/change-the-debug-flag/"
wordpressId: "4759"
group: "Extensions"
summary: "The following snippets show how to change the Debug Flag for the GraphQL Server execution. By default, the function callstack trace is not included with errors data unless users are logged in. If you wanted to track this…"
---

The following snippets show how to change the Debug Flag for the GraphQL Server execution.

By default, the function callstack trace is _not_ included with errors data unless users are logged in.

If you wanted to track this data on the server, for example, even for public requests, and send the data to a logging service, for example, you could enable the call stack with the following snippet. (Just make sure you \_also\_ cleanup the errors after the fact so you don’t expose too much data to public users.)

```
add_action( 'graphql_server_config', function( \GraphQL\Server\ServerConfig $config ) {
	$config->setDebugFlag( 1 );
});
```

**Example of the Error Trace when the debug flag is set to 1**

There is no callstack trace with the error.

![](https://content.wpgraphql.com/wp-content/uploads/2020/11/Screen-Shot-2020-11-12-at-11.32.41-PM-1024x444.png)

**Example of the Error Trace when the debug flag is set to 2**

```
add_action( 'graphql_server_config', function( \GraphQL\Server\ServerConfig $config ) {
	$config->setDebugFlag( 2 );
});
```

There is a callstack trace included with the error.

![](https://content.wpgraphql.com/wp-content/uploads/2020/11/Screen-Shot-2020-11-12-at-11.31.42-PM-1024x490.png)
