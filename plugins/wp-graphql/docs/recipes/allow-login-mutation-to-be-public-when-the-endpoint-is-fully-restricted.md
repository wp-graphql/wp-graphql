<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Allow login mutation to be public when the endpoint is fully restricted"
wordpressUri: "/recipes/allow-login-mutation-to-be-public-when-the-endpoint-is-fully-restricted/"
wordpressId: "822859"
group: "Authorization"
summary: "If you&#8217;ve configured your WPGraphQL settings to &#8220;Limit the execution of GraphQL operations to authenticated requests&#8221;, this will block all root operations unless the user making the request is already a…"
---

If you’ve configured your WPGraphQL settings to “Limit the execution of GraphQL operations to authenticated requests”, this will block all root operations unless the user making the request is already authenticated.  
  
If you’re using a GraphQL mutation to authenticate, such as the one provided by WPGraphQL JWT Authentication, you might want to allow the `login` mutation to still be executable by public users, even if the rest of the API is restricted.  
  
This snippet allows you to “allow” the login mutation when all other root operations are restricted.

```
add_filter( 
  'graphql_require_authentication_allowed_fields', 
  function( $allowed ) {
	$allowed[] = 'login';
	return $allowed;
}, 10, 1 );
```
