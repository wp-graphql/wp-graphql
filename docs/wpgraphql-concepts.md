---
uri: "/docs/wpgraphql-concepts/"
title: "WPGraphQL Concepts"
---

This page covers some opinionated concepts of GraphQL that are implemented by WPGraphQL

## Relay Specification

Many GraphQL APIs, including WPGraphQL adhere to the Relay Specification for GraphQL Servers.

While GraphQL itself prescribes almost nothing in terms of how a GraphQL Schema is designed, it can be easy to design a naive schema that will not hold up to real-world use cases.

The Relay Specification came about as Facebook engineers used GraphQL in production. The specification provides guidance for concepts such as Object Identification and Global ID, Connections and Mutations.

### Node & Global ID

Every object in WordPress is treated as an individual "[node](https://relay.dev/docs/guides/graphql-server-specification/#object-identification)" in GraphQL. Posts are nodes. Pages are nodes. Categories, tags, users, comments, menu items, etc are all considered "nodes".

And each "node" in the Graph can be identified by a unique ID.

In WordPress, IDs are not truly unique. There can be a Post with ID `1`, a User with ID `1`, a Category with ID `1` and a Comment with ID `1`. WPGraphQL generates opaque global IDs for entities by hashing the underlying loader type and the database id. So, objects loaded by the Post loader get a global ID of `base_64_encode( 'post:' . $database_id );`. So, a Post with the database ID of `1` would have a global ID of `cG9zdDox`.

WPGraphQL allows for a global ID to be passed to the root `node` field, like so:

```graphql
{
  node( id: "cG9zdDox" ) {
    __typename
  }
}
```

This would return something like the following:

```json
{
  "data": {
    "node": {
      "__typename": "Post"
    }
  }
}
```

This allows a client to access data of any type of node in the Graph if it's ID is provided, by using inline fragments, like so:

```graphql
{
  node(id: "cG9zdDox") {
    __typename
    ... on Post {
      id
      title
    }
  }
}
```

Which would return data like so:

```json
{
  "data": {
    "node": {
      "__typename": "Post",
      "id": "cG9zdDox",
      "title": "Hello World"
    }
  }
}
```

### Connections

[Connections](https://relay.dev/docs/guides/graphql-server-specification/#connections) are a concept introduced by the Relay Specification for handling lists and relationships between Nodes.

WPGraphQL makes heavy use of Connections to query lists of data and for querying relational data.

[Read more about how WPGraphQL uses connections](/docs/connections/).

### Mutations

GraphQL itself has no opinions on how Mutations are designed, but this freedom can also come at a cost. Without constraints, it's possible you might design a Mutation that would hardly be useful in real-life situations.

The Relay Specification has opinions on how Mutations should be designed in a GraphQL Schema, and WPGraphQL follows the [guidelines set forth](https://relay.dev/docs/guided-tour/updating-data/graphql-mutations/).

[Learn more about Mutations in WPGraphQL](/docs/wpgraphql-mutations/).

## Hooks: Actions and Filters

Actions and Filters are types of "Hooks", a convention provided by WordPress, that allow plugins and themes to hook in to certain parts of WPGraphQL Execution and do things.

- **[Actions](/actions/)**: Actions are hooks that execute at a specific time and allow functions to execute with certain context.
- **[Filters](/filters/)**: Filters are hooks that pass data through and allow 3rd party code to modify the data and except something to be returned.

## The Model Layer

WPGraphQL has a "Model Layer", or a series of PHP Classes that take WordPress objects, such as `WP_Post`, `WP_Comment`, etc and normalize the data to a GraphQL-friendly shape, and also applies logic to ensure the requested data is allowed to be seen be the requesting user.

For example, WordPress uses `snake_case` naming conventions, but GraphQL fields use `camelCase` naming conventions. WordPress also makes use of prefixes that don't necessarily make sense in the context of an API. For example, WordPress `$post->post_content` becomes $post->`content` in the WPGraphQL Post Model.

And fields that are sensitive, for example user email addresses, get checked in the Model Layer to ensure the user requesting the field has proper capabilities to see the data.

Let's take the following request, for example:

```graphql
{
  users {
    nodes {
      id
      name
      email
    }
  }
}
```

The WPGraphQL User Model will determine whether each user that was returned from the underlying WP\_User\_Query is allowed to be viewed by the user making the request. If the request is public, all users that are not published authors would be removed from the results, while published authors would still be included.

Then, the `email` field would return a null, even for the published authors, because that field is limited to logged in users.

So, a public request might get results like so, where published authors are returned, but their email address is excluded:

```json
{
  "data": {
    "users": {
      "nodes": [
        {
          "id": "dXNlcjo4",
          "name": "John Doe",
          "email": null
        },
        {
          "id": "dXNlcjoxMQ==",
          "name": "Jane Doe",
          "email": null
        },
     ]
  }
}
```

But, an authenticated request might return the following:

```json
{
  "data": {
    "users": {
      "nodes": [
        {
          "id": "dXNlcjo4",
          "name": "John Doe",
          "email": "john@example.com"
        },
        {
          "id": "dXNlcjoxMQ==",
          "name": "Jane Doe",
          "email": "jane@example.com"
        },
     ]
  }
}
```
