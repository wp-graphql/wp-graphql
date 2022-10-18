---
uri: "/docs/wpgraphql-request-lifecycle/"
title: "WPGraphQL Request Lifecycle"
---

This page is intended to be a technical guide on how a WPGraphQL request executes.

An over-simplified summary of the WPGraphQL lifecycle looks something like the following:

- The GraphQL operation is sent to the GraphQL endpoint (or [executed in PHP](/docs/use-with-php/) directly)
- The GraphQL Request class is instantiated
- The Operation is Parsed to determine what fields were asked for
- The fields are validated against the Schema
- If the request is invalid, errors are returned and no execution occurs
- If the request is valid, the fields are mapped to their resolvers (callback functions) and execution proceeds.
- Each resolver returns data and passes it down to the next level of resolvers until no more fields require execution.
- The response is formatted as JSON and returned
GraphQL Endpoint

Most users of WPGraphQL will be interacting with it from the site's `/graphql` endpoint.

The endpoint is created by the [WPGraphQL Router Class](https://github.com/wp-graphql/wp-graphql/blob/develop/src/Router.php). The default Route is `/graphql` but can be changed via filter or the WPGraphQL Settings page. Additionally, for sites that do not have pretty permalinks enabled, the endpoint can be queried at `/index.php?graphql`.

The Router determines whether the request is a GraphQL HTTP Request.

The Router than proceeds to process the GraphQL request by parsing the Query (mutations are included here), the Variables (if any) and the Operation Name (if specified) and passes this data to the WPGraphQL Request class.

### Using WPGraphQL in PHP

If you're using WPGraphQL directly in PHP, the router part is skipped and the `graphql()` function call maps directly to the WPGraphQL Request Class.

[Learn more about using WPGraphQL in PHP](/docs/use-with-php/).

WPGraphQL Request Class

The WPGraphQL Request class initializes the TypeRegistry and builds the Schema.

### Schema Generation

When the Schema is built, by default it just builds the RootQuery and RootMutation Types, and only fields that are asked for beyond the Root types are generated. Resolvers for fields in the Schema are not executed when the Schema is being built, only when the fields are asked for in a request.

This is similar to Action and Filter registries in WordPress. You can register many actions and filters, but they don't ever execute until the `do_action` or `apply_filters` functions run and call the action/filter callbacks.

WPGraphQL resolvers are callbacks that only execute when a field is asked for. This allows WPGraphQL to execute requests rather quickly.

### Validation Rules

Validation rules are set in the Request class and are passed to execution to determine whether to execute in Debug mode, whether to return stack traces, or even whether to validate requests at all.

### App Context

The Request class also sets up AppContext to pass down to all resolvers.

The AppContext Class holds information such as the current user, and Loader classes which are used to load data throughout execution.

### Execution

With the Schema, App Context and Validation Rules prepared, the Query, Variables and Operation Name are passed to the underlying GraphQL-PHP library for execution.

Each field in the GraphQL Query (or Mutation) maps to a callback function, commonly called "resolve functions" or "resolvers".

Fields that have been registered using [WPGraphQL Type Registry functions](/functions/), such as `register_graphql_field`, have actions and filters applied during execution, allowing for fine control over the behavior of resolvers.

### Loaders

In many cases, the request will ask for objects, which are stored as rows in the the WordPress database.

When asking for core WordPress objects such as `Posts`, `Terms`, `Users`, or `Comments`, WPGraphQL will use a Loader to load the data. Custom loaders can also be registered to [handle data from Custom Database Tables](/docs/using-data-from-custom-database-tables/), or external data sources.

Loaders allow WPGraphQL to be extremely efficient with fetching data from the database.

You can read more about how WPGraphQL Loaders work in the [WPGraphQL performance guide](/docs/performance/).

### Model Layer

Once Data, such as a Post, Comment, User or Term has been loaded, it is passed through the WPGraphQL Model Layer.

The Model Layer handles the transformation of objects from WordPress objects, such as `WP_Post`, `WP_Term`, etc to WPGraphQL Models.

The Model Layer converts fields from WordPress `snake_case` to GraphQL `camelCase`.

And most importantly, the Model Layer determines whether the user making the request has proper capabilities to view the objects (posts, terms, etc) that have been loaded by the Loaders.

In some cases, entire objects can be determined private and the Model Layer will prevent the object from being included in the results. In other cases, individual fields, such as a User email address, will be hidden from the results, but other fields will be returned.

Because of the nature of GraphQL requests, objects can be accessed from various entry points into the Graph, so handling permission checks within resolvers would lead to loads of duplicate code. The Model Layer allows for centralization of capability checks, data normalization and sanitization.

You can read more about the Model Layer in the [WPGraphQL Security guide](/docs/security/).

### Response

Execution continues, returning the results of each resolver and passing it down to the next level until leaf fields, or the concrete fields that resolve scalar values are reached.

Once there are no more fields to execute, the results are formatted as JSON and returned. Any errors that happened along the way are collected and included in the results, as with any debug logs, or extensions such as Query Logs or Trace data.
