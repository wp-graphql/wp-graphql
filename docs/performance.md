---
uri: "/docs/performance/"
title: "Performance"
---

# Performance Best Practices

WPGraphQL is designed to be performant, but there are several best practices you can follow to ensure optimal performance in your implementation.

## Query Optimization

### Request Only What You Need

One of GraphQL's key advantages is the ability to request specific fields. Unlike REST, where endpoints often return fixed data sets, GraphQL allows precise field selection:

```graphql
query GetPostsWithAuthorAndAuthorsPosts {
  posts {
    nodes {
      id
      title
      date
      author {
        node {
          id
          name
          description
          posts {
            nodes {
              title
            }
          }
        }
      }
    }
  }
}
```

While it's convenient to be able to query nested relationships and fields, unless you have an actual need for the data, don't query it. 

While you might think that it's best to query fields "just in case" you might need it at some point, you're just creating more load on the server. Only request what you actually need, and change your queries and fragments as your needs change. 

### Include Global IDs in Queries and Fragments

While it's not a requirement to query for global IDs on nodes, it can provide benefits.

When using client-side GraphQL libraries like Apollo Client or Relay, including the `id` and `databaseId` fields in your queries can improve caching and data management:

```graphql
query GetPosts {
  posts {
    nodes {
      # Including both IDs helps with caching
      id        # Global ID used by GraphQL clients
      databaseId # WordPress database ID
      title
      # ... other fields
    }
  }
}
```

Many GraphQL clients use these IDs for:
- Query result caching and deduplication
- Automatic cache updates
- Entity normalization
- Optimistic updates

### Minimize Connection Nesting

While WPGraphQL's connection model allows for deep nesting of related data, excessive nesting can lead to performance issues due to multiple database joins.

Consider these strategies:

#### Split or Conditionally Load Nested Data

For data that's only needed sometimes, consider:

- Using `@skip` and `@include` directive for conditional loading
- Splitting into separate queries

```graphql
query GetPostsWithOptionalAuthorPosts($includeAuthorPosts: Boolean!) {
  posts {
    nodes {
      id
      title
      author {
        node {
          name
          # Only load author's posts when needed. 
          # Application state can determine the boolean value to send to the variable.
          posts @include(if: $includeAuthorPosts) {
            nodes {
              title
            }
          }
        }
      }
    }
  }
}
```

or

```
query GetPostsWithOptionalAuthorPosts($includeAuthorPosts: Boolean!) {
  posts {
    nodes {
      id
      title
      author {
        node {
          name
        }
      }
    }
  }
}
```

Get the results of the first query and execute the next query only as needed:

```graphql
query GetPostsByAuthor($authorId: Int) {
  posts(where: {author: $authorId}) {
    nodes {
      id
      title
      author {
        node {
          databaseId
          id
          name
        }
      }
    }
  }
}
```


## Pagination

WPGraphQL implements Relay-style cursor-based pagination, which is more efficient than offset-based pagination for large datasets.

Best practices for pagination:
- Use reasonable `first`/`last` limits (10-100 items per request)
- Implement infinite scroll (or load more buttons) rather than loading all items at once
- Utilize cursor-based navigation with `first & after`/`last & before` parameters

```graphql
query GetPosts{
  posts(first: 10, after: "cursor") {
    pageInfo {
      hasNextPage
      endCursor
      hasPreviousPage
      startCursor
    }
    nodes {
      id
      title
    }
  }
}
```

## Caching

### WPGraphQL Smart Cache

WPGraphQL provides an extension plugin, WPGraphQL Smart Cache, which is designed to work with network/edge caching (i.e. Varnish, Cloudflare, Fastly). 

WPGraphQL returns an X-GraphQL-Keys header that contains tags relevant to the executed query. 

Hosts that support it, will cache the GraphQL response and tag the cached document with the tags returned in the X-GraphQL-Keys header.

WPGraphQL Smart Cache will then listen for events such as posts (of any post type, and terms, users, comments, etc) being published, updated or deleted, and will emit an event indicating which "tags" have been impacted, allowing cached documents with the corresponding tag to be purged from the cache.

Learn more about [WPGraphQL Smart Cache](https://github.com/wp-graphql/wp-graphql-smart-cache). 

### GET vs POST Requests

While GraphQL typically uses POST requests, WPGraphQL supports GET requests which can be beneficial for:
- CDN caching
- Browser caching
- Proxy caching
- WPGraphQL Smart Cache

To use GET requests:
1. Make sure your client sends the request as GET instead of POST
2. Ensure your queries are idempotent (read-only) - mutations will not work over GET.
3. Be mindful of URL length limitations
  - WPGraphQL Smart Cache has a "Persisted Queries" feature that can help overcome this limitation.

## Request Strategies

### Multiple Small Requests vs. Single Large Request

Consider these approaches:

**Multiple Small Requests:**
- ✅ Better cache utilization
- ✅ Parallel loading possible
- ✅ Smaller payload per request
- ❌ Multiple network roundtrips
- ❌ More HTTP overhead

**Single Large Request:**
- ✅ Single network roundtrip
- ✅ Less HTTP overhead
- ❌ All-or-nothing cache invalidation
- ❌ Larger initial payload
- ❌ Potentially slower time-to-first-byte

Choose based on your specific use case:
- Use multiple requests for independent components that can load in parallel
- Use single requests for tightly coupled data needed all at once

## Performance Monitoring

Monitor your GraphQL performance using:
- Browser Developer Tools Network tab
- WPGraphQL Query Logging and Trace data (enabled under WPGraphQL > Settings)
- Server-side logging
- Application Performance Monitoring (APM) tools

## Additional Tips

1. **Use Field Aliases** to request the same field with different arguments
2. **Implement Batching** for multiple similar queries (only works with HTTP POST, not GET)
3. **Consider Fragment Usage** for reusable field selections
4. **Use Persistent Operations** for production environments (provided by WPGraphQL Smart Cache)
5. **Implement Error Boundaries** in your client applications

Remember to test performance in production-like environments and monitor real-world usage patterns to identify optimization opportunities.