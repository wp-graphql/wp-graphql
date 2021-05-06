---
uri: "/docs/security/"
title: "Security"
---

WPGraphQL has been developed with security in mind. Below, are details on some of the ways WPGraphQL works to allow you to use the benefits of GraphQL while keeping your data secure.

## Introspection Disabled by Default

One feature of GraphQL is Schema Introspection, which means the GraphQL Schema itself can be queried. This is a feature used by tools such as GraphiQL and others.

It's possible that exposing the Schema publicly (in some cases) can leak information about the system that's not intended to be known publicly.

WPGraphQL disables public Schema Introspection by default, but for users that want to enable it, it can be enabled with one-click from the GraphQL > Settings page in the WordPress dashboard. 

## Access Control Rights

WordPress core has many access control rights established, and WPGraphQL follows them. Anything that is publicly exposed by WordPress is publicly exposed by WPGraphQL, and any data that requires a user to be authenticated to WordPress to see, WPGraphQL also requires requests to be properly authenticated for users to see.  

For example, in WordPress core, users that have not published posts are only visible within the WordPress dashboard. There is no public URL for unpublished authors. WPGraphQL respects this, and queries for users will not include users without published posts. Properly authenticated requests from a user that has the capability to list users will be able to see unpublished authors, much like they would be able to within the WordPress dashboard. 

## Model Layer

To help facilitate what data is publicly exposed and what data is considered private, WPGraphQL has a "Model Layer". Each type of object (Posts, Terms, Users, Comments, etc) have a WPGraphQL Model that is responsible for permission checks. Anytime an object is asked for from the Graph, the Model determines if the object is allowed to be returned to the requesting user, and if so, what specific fields can be returned. The Model Layer takes into consideration many things when determining if the object should be considered public or private.  

Some things the [Model Layer](https://github.com/wp-graphql/wp-graphql/tree/develop/src/Model) will consider before returning an object:  
 
- Is the Request for data authenticated or public?
- What is the state of the object being requested (is it published, draft, etc)?
- Who is the owner of the object? ex: is the author of the post the same user requesting it in GraphQL?
- Does the object belong to a private Type (private post_type, for example?)

If an object is determined to be allowed to be returned to the requesting user, further checks are done on the fields being requested.

For example, a user with published posts is considered a public entity, but the email address for that user is still considered a non-public field and requires specific permission to access.

Read more about the WPGraphQL Model Layer. 

## Authentication and Authorization

- **Authentication**: the process of verifying who you are (logging in)
- **Authorization**: the process of verifying that you have access to something – (the ability to view/change private data)

### A quick word about GraphQL Mutations vs Queries

From a technical perspective, the only differences between GraphQL Queries and Mutations is the `mutation` keyword, and the GraphQL spec requires mutations to be processed synchronously, where queries can be processed Async (in environments that support it).

Other than that, Queries and Mutations are the same, they’re both just strings that map to functions.

Now that we’re clear on Queries vs. Mutations (both are just maps to functions), authentication & authorization is left up to the application layer, not the GraphQL API layer, although some mechanisms in GraphQL can help facilitate these processes.

### Authentication with WPGraphQL

Since WPGraphQL is a WordPress plugin that adheres largely to common WordPress practices, there are many ways to make authenticated WPGraphQL requests.

For remote HTTP requests to the `/graphql` endpoint, existing authentication plugins *should* work fine. These plugins make use of sending data in the Headers of requests and validating the credentials and setting the user before execution of the API request is returned:

- https://github.com/wp-graphql/wp-graphql-jwt-authentication
- https://github.com/WP-API/Basic-Auth (even though it’s labeled for the REST API, it works well with WPGraphQL – but not recommended for non-SSL connections)
- https://github.com/WP-API/OAuth1 (labeled for use with the WP REST API, but works well with WPGraphQL)

If the remote request is within the WordPress admin, such as the WPGraphiQL plugin, you can use the existing Auth nonce as seen in action [here](https://github.com/wp-graphql/wp-graphiql/blob/master/assets/app/src/App.js#L16-L29).

For non-remote requests (PHP function calls), if the context of the request is already authenticated, such as an Admin page in the WordPress dashboard, existing WordPress authentication can be used, taking advantage of the existing session. For example, if you wanted to use a GraphQL query to populate a dashboard page, you could send your query to the `do_graphql_request( $query )` function, and since the request is already authenticated, GraphQL will execute with the current user set, and will resolve fields that the users has permission to resolve.

### Authorization with WPGraphQL

Since WPGraphQL is built as a WordPress plugin, it makes use of WordPress core methods to determine the current user for the request, and execute with that context.


The mutations that WPGraphQL provide out of the box attempt to adhere to best practices in regards to respecting user roles and capabilities. Whether the mutation is creating, updating or deleting content, WPGraphQL checks for capabilities before executing the mutation.

For example, any mutation that would create a `post` will first check to make sure the current user has proper capabilities to create a `post`.

Mutations are not alone when it comes to checking capabilities. Some queries expose potentially sensitive data, such as the email address field in `generalSettings`. By default, this field will only resolve if the request is authenticated, meaning that the value of the email address is only exposed to logged in users.

A public, non-authenticated request would return a null value for the field and would return an error message in the GraphQL response. However, it wouldn’t block the execution of the entire GraphQL request, just that field. So, if the request had a mix of publicly allowed fields and private fields, GraphQL would still execute the public data. For example, trying a query like:

```graphql
{
  generalSettings {
    title
    email
  }
}
```

But the results would return `null` for the email field, like so:

```graphql
{
  \"data\": {
    \"generalSettings\": {
      \"title\": \"WPGraphQL.com\",
      \"email\": null
    }
  }
}
```
