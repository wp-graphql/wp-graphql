# FAQ

## How to use Custom Post Types and Custom Taxonomies?

It's pretty common to want to expose Custom Post Type and Custom Taxonomies in WPGraphQL. We have tutorials written up on how to do this!

[Learn more about using Custom Post Types & Taxonomies with WPGraphQL](tutorials/custom-post-types-and-taxonomies.md)

## How to override a field's resolver?

Sometimes you need granular control over how a specific field resolves. Field resolvers can easily be filtered so that you can control what is returned.

[Learn more about overriding Field resolvers](tutorials/override-field-resolvers.md)

## What are "edges" and "nodes"?

GraphQL itself makes no mandates on the shape of a Schema. This is great, but can also be problematic as a poorly defined schema can be difficult to use, extend, and version.

The shape of the WPGraphQL Schema is inspired heavily by the [Relay spec](https://facebook.github.io/relay/docs/graphql-relay-specification.html).

The Relay spec introduces the concept of [Connections](https://facebook.github.io/relay/docs/graphql-connections.html). 

Let's compare what a naiive schema and a schema with Relay connections looks like.

### Naive Schema


```
{
  posts {
    id
    title
    date
  }
}
```

### Connection Schema

```
{
  posts {
    edges {
      node {
        id
        title
        date
      }
    }
  }
}
```

The _edges_ serve as a place for where "edge" data can be exposed. The most common example is pagination cursors. 

### Pagination

In the [naiive schema](#naive-schema), we just get a list of posts back. There's no data exposed that references where each
post belongs in the larger data set. For pagination to work well, we need to be able to pass data back in our next query
to indicate what posts we want to query next. 

The cursor for a connection can be queried like so:

```
{
  posts {
    pageInfo {
      hasNextPage
      hasPreviousPage
      startCursor
      endCursor
    }
    edges {
      cursor
      node {
        id
        title
        date
      }
    }
  }
}
```

Here we ask for `pageInfo` on the connection itself, where we ask for the `startCursor` and `endCursor`. Additionally we ask for a cursor
for each "node" in the connection. 

The cursor can be used as a reference to where in the overall data-set we are, and can be used to tell WPGraphQL where to start
the next query.

For example, if we queried for 10 items, and wanted to paginate through asking for the next 10 items, we could do so by first asking for 10 posts:

```
{
   posts( first: 10 ) {
       pageInfo {
         hasNextPage
         hasPreviousPage
         startCursor
         endCursor
       }
       edges {
         cursor
         node {
           id
           title
           date
         }
       }
   }
}
```

Then, we would store the `endCursor` as a variable `$endCursor = query.posts.pageInfo.endCursor` and use that to tell WPGraphQL where to start the next query, like so:

```
{
   posts( first: 10, after: $endCursor ) {
       pageInfo {
         hasNextPage
         hasPreviousPage
         startCursor
         endCursor
       }
       edges {
         cursor
         node {
           id
           title
           date
         }
       }
   }
}
```

WPGraphQL takes the `after` argument on the connection, and uses that to determine where to start the query and get the 10 posts after that point in the data set.

!!! note "Variables with GraphQL"
    This is a basic example showing variables in GraphQL Query. As you work with GraphQL more, there are better ways to handle variables in your GraphQL requests. [Learn more about using GraphQL variables](http://graphql.org/learn/queries/#variables)

### Other Edge Data
Pagination is an important part of the "connection" shape of the Schema, but "edges" provide a great spot for other info
that's not a property of either "node" in a connection. 

One hypothetical example of edge data, would be a `friendsSince` field on a connection between 2 friends in a Schema. A field like that isn't
a property of either user, it's a property that exists only in the context of the connection. A naiive schema wouldn't have a good way
to display this "edge" data, but the Relay connection spec provides the nested edges/node shape to make it easy to query for "edge" data when needed.

!!! info "Food for thought"
    Possibly WordPress will one day have a formal way to store Term_Relationship_Meta or Object_Relationship_Meta, where data can be stored when info about specific connections is needed. This would be perfect for displaying as "edge" data in the GraphQL Schema.

## What is a GraphQL Client?

A GraphQL client is anything that makes requests to a GraphQL server. Anything that can make an HTTP POST request (CURL, Postman, basically any programming language) can be a GraphQL client.

It's most common to use GraphQL in the context of JavaScript single page apps or native iOS and Android apps, and in that case, our recommendation is to use [Apollo Client](https://www.apollographql.com/client/). 

Apollo Client is a caching GraphQL client that eases managing GraphQL requests and caches and normalized the responses a GraphQL server provides. Apollo client is community driven and is used by industry leaders including The New York Times, Qz.com, Intuit, IBM, KLM and more.

## Why is the HTTP status 403?

Unlike REST, where each endpoint represents a single resource, GraphQL allows for multiple resources to be fetched at once. Some data might be public, and some might not be. Some might resolve fine, and some might have errors.

Because of this, WPGraphQL will resolve as much of the data as it can and return it, and will return errors associated with the data it couldn't resolve.

The 403 status is default to indicate that the request is not authenticated. It doesn't mean there is anything wrong. WPGraphQL will still resolve the data that
doesn't require any type of authentication (which in many cases is all the data that was requested). 

However, for requests for private data or Mutations where authentication is required, it becomes more important to be able to identify when the server properly identifies authentication and when it doesn't.

By returning a 403 status for non-authenticated requests and a 200 status for authenticated requests, clients (such as Apollo) can use the status to ask users to re-authenticate as needed, for example when an Auth token has expired or a Password was changed, or any other reason authentication might fail on subsequent requests.

## How many houses are in Iowa?
Not sure why this is frequently asked, or if it even is, but you won't find the answer here...

## What if I have a new question?
If you have a question that isn't answered in the FAQ or in any of Tutorials or Reference guide, feel free to create an issue in Github, but be as specific and detailed as possible to give us the best chance to help you. 

Additionally, you can reach out to the greater WPGraphQL community on Slack: [join here](https://wpgraphql.com/community)