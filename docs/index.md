![Logo](https://www.wpgraphql.com/wp-content/uploads/2017/06/wpgraphql-logo-e1502819081849.png)

WPGraphQL - A free, open-source WordPress plugin that provides an extendable GraphQL schema and API for any WordPress site.

You are currently viewing the WPGraphQL docs and reference, where you can read about major concepts, dive into technical details or follow practical examples to learn how WPGraphQL works.

<a href="https://www.wpgraphql.com" target="_blank">Website</a> • <a href="https://wp-graphql.github.io/wp-graphql-api-docs/" target="_blank">ApiGen Code Docs</a> • <a href="https://wpgql-slack.herokuapp.com/" target="_blank">Slack</a>

[![Build Status](https://travis-ci.org/wp-graphql/wp-graphql.svg?branch=master)](https://travis-ci.org/wp-graphql/wp-graphql)
[![Coverage Status](https://coveralls.io/repos/github/wp-graphql/wp-graphql/badge.svg?branch=master)](https://coveralls.io/github/wp-graphql/wp-graphql?branch=master)

----

!!! warning "Beta Software Notice"
    Until WPGraphQL hits a [1.0.0 release](contributing.md), it is still considered beta software. This doesn't mean that the plugin isn't ready for use, it just means that there _might_ still be [bugs](https://github.com/wp-graphql/wp-graphql) and that there _might_ be breaking changes to the shape of the API or internal functions as we work toward a stable release. 

    Don't hesitate to start using the plugin, but just be sure to follow along with [releases](https://github.com/wp-graphql/wp-graphql/releases) 
    and keep up to date with conversations in [Slack \(join here\)](https://wpgraphql.com/community) 
    
    WPGraphQL is already in use in production on several sites, including [work.qz.com](https://work.qz.com), [hopelabs.org](http://hopelabs.org) and more.


# What is WPGraphQL?

WPGraphQL is A free, open-source WordPress plugin that provides an extendable [GraphQL](http://graphql.org) GraphQL Schema and API for any WordPress site.

WPGraphQL provides a GraphQL API and `/graphql` endpoint for your WordPress site, allowing for interaction with WordPress data using [GraphQL Queries and Mutations](http://graphql.org/learn/queries/).

## What is GraphQL?

GraphQL is an open source technical specification, developed and maintained by Facebook, for an application level query language. 

What does that mean? Basically GraphQL provides a consistent way to make declarative queries, which will enable you to more easily retrieve the data you want, and the shape that you want it in. 

GraphQL can be implemented in any language and can cover a vast amount of use cases. 

WPGraphQL exposes a WordPress installation's data through a GraphQL API. You can send a GraphQL request over HTTP to the `/graphql` endpoint provided by the plugin and in response you will get the matching JSON representation of your data.

!!! note "Use WPGraphQL without HTTP requests"
    You can also use GraphQL Queries and Mutations from within WordPress PHP without the need for HTTP network requests. [Learn more](tutorials/use-graphql-in-php-without-http-request.md)

## Why use WPGraphQL?

WPGraphQL is arguably the easiest _and_ most efficient way to interact with WordPress data.

GraphQL enables small, efficient responses by only retrieving and returning exactly what was asked for, and nothing more.

WPGraphQL helps reduce:

- **HTTP Requests:** Multiple resources can be fetched in a single request
- **Response size:** Only the fields asked for are returned minimizing the payload downloaded by the client
- **Endpoint Bloat:** GraphQL provides a single endpoint and allows clients to ask for what they want from the single source. No need to memorize and maintain various feature endpoints.
- **External Documentation:** GraphQL is self-documenting, reducing the time and resources needed for maintaining API documentation
- **Total SQL queries:** GraphQL queries can ask for multiple resources, or nested resources in a single request allowing GraphQL to determine the most efficient way to get the data from the database with as few SQL queries as possible.
- **Code Duplication:** Because GraphQL exposes all server capabilities in a single Schema, there's no need to duplicate code for various endpoints. The client can control the shape of their model without specific feature endpoint, which typically requires duplicate server code.
- **Over/Under Fetching:** With REST, you are constantly over and under-fetching at the same time. Endpoints typically have more data than you need, and not enough requiring round-trips to get other resources. GraphQL returns exactly what was asked for and can handle connections in a single request.

WPGraphQL helps improve:

- **Performance:** WPGraphQL utilizies existing WordPress Core APIs, such as WP_Query, so caching and filters are respected, but WPGraphQL also makes use of deferred resolvers and some look-ahead techniques (as all fields are known in the request before execution begins), allowing for data to be fetched and returned with as few SQL queries as possible. 
- **Developer Happiness:** The strong-type system and explicit nature of GraphQL requests makes it easy for developers to understand GraphQL query and mutation requests long after code is written. Additionally, code duplication is drastically reduced as the API is defined in one spot, not in feature endpoints.
- **Versioning:** REST APIs and other APIs can be difficult to version. GraphQL makes [evolving the API](http://graphql.org/#without-versions) much easier than how other APIs are versioned. 
- **Client/Server Decoupling:** Since the GraphQL server describes the capabilities and the client defines the shape of the model, the coupling between client and server is loosened. Clients can iterate their models without custom endpoints needing to be created or updated.

## How does WPGraphQL Work?

WPGraphQL adds a `/graphql` endpoint to your WordPress install. 

When HTTP requests are made to that endpoint, WPGraphQL executes the GraphQL request. 

!!! note "Use WPGraphQL without HTTP requests"
    You can also use GraphQL Queries and Mutations from within WordPress PHP without the need for HTTP network requests. [Learn more](tutorials/use-graphql-in-php-without-http-request.md)

The first part of the execution process, is validating the Query. Since GraphQL is centered around a strong typed Schema, before any real execution begins GraphQL can compare the request against the Schema to make sure the request is even valid. 

If it's invalid, no execution occurs, errors are returned right away. 

If the request is valid, GraphQL breaks down the fields and resolves them as a tree, passing the resolved data down to the next fields in the tree. 

Take this query for example: 

```graphql
{
  post(id:"...") {
     id
     title
     customField
  }
}
```

Behind the Scenes, GraphQL uses native WordPress Core functions to resolve the post by it's ID. 

```php
\WP_Post::get_instance( $id );
```

Then, GraphQL passes the Post down to the fields to be resolved. The fields use the Post as context to resolve the `id`, `title`, and `customField`. 

Once all fields are resolved, GraphQL returns the response in JSON format. 
