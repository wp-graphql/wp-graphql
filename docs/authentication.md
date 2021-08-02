---
uri: "/docs/authentication-and-authorization/"
title: "Authentication and Authorization"
---

## A quick word about GraphQL Mutations vs Queries

From a technical perspective, the only differences between GraphQL Queries and Mutations is the `mutation` keyword, and the GraphQL spec requires mutations to be processed synchronously, where queries can be processed Async (in environments that support it).

Other than that, Queries and Mutations are the same, they’re both just strings that map to functions.

Now that we’re clear on Queries vs. Mutations (both are just maps to functions), authentication & authorization is left up to the application layer, not the GraphQL API layer, although some mechanisms in GraphQL can help facilitate these processes.

## Authentication & Authorization

- **Authentication**: the process of verifying who you are (logging in)
- **Authorization**: the process of verifying that you have access to something – (the ability to view/change private data)

## Authentication with WPGraphQL

Since WPGraphQL is a WordPress plugin that adheres largely to common WordPress practices, there are many ways to make authenticated WPGraphQL requests.

For remote HTTP requests to the `/graphql` endpoint, existing authentication plugins *should* work fine. These plugins make use of sending data in the Headers of requests and validating the credentials and setting the user before execution of the API request is returned:

- https://github.com/wp-graphql/wp-graphql-jwt-authentication
- https://github.com/WP-API/Basic-Auth (even though it’s labeled for the REST API, it works well with WPGraphQL – but not recommended for non-SSL connections)
- https://github.com/WP-API/OAuth1 (labeled for use with the WP REST API, but works well with WPGraphQL)

If the remote request is within the WordPress admin, such as the WPGraphiQL plugin, you can use the existing Auth nonce as seen in action [here](https://github.com/wp-graphql/wp-graphiql/blob/master/assets/app/src/App.js#L16-L29).

For non-remote requests (PHP function calls), if the context of the request is already authenticated, such as an Admin page in the WordPress dashboard, existing WordPress authentication can be used, taking advantage of the existing session. For example, if you wanted to use a GraphQL query to populate a dashboard page, you could send your query to the `do_graphql_request( $query )` function, and since the request is already authenticated, GraphQL will execute with the current user set, and will resolve fields that the users has permission to resolve.

## Authorization with WPGraphQL

Since WPGraphQL is built as a WordPress plugin, it makes use of WordPress core methods to determine the current user for the request, and execute with that context.

The mutations that WPGraphQL provide out of the box attempt to adhere to best practices in regards to respecting user roles and capabilities. Whether the mutation is creating, updating or deleting content, WPGraphQL checks for capabilities before executing the mutation.

For example, any mutation that would create a `post` will first check to make sure the current user has proper capabilities to create a `post`.

Mutations are not alone when it comes to checking capabilities. Some queries expose potentially sensitive data, such as the email address field in `generalSettings`. By default, this field will only resolve if the request is authenticated, meaning that the value of the email address is only exposed to logged in users.

A public, non-authenticated request would return a null value for the field and would return an error message in the GraphQL response. However, it wouldn’t block the execution of the entire GraphQL request, just that field. So, if the request had a mix of publicly allowed fields and private fields, GraphQL would still execute the public data. 

For example, trying a query like:

```graphql
{
  generalSettings {
    title
    email
  }
}
```

Will return:

```graphql
{
  \"data\": {
    \"generalSettings\": {
      \"title\": \"WPGraphQL\",
      \"email\": null
    }
  }
}
```
