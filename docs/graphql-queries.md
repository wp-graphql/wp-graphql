---
uri: "/docs/graphql-queries/"
title: "Querying with GraphQL"
---

Building on the concepts from our [Intro to GraphQL](/docs/intro-to-graphql/), this guide provides practical examples of querying data with WPGraphQL.

## Basic Query Structure

### Simple Queries

The most basic query structure:
```graphql
{
  posts {
    nodes {
      id
      title
    }
  }
}
```

### Named Operations

While the shorthand syntax above works, it's best practice to use named operations:
```graphql
# ✅ Better: Using the query keyword and operation name
query GetPosts {
  posts {
    nodes {
      id
      title
    }
  }
}
```

Named operations help with:
- Debugging and logging
- Code organization
- Query reusability
- Required for variables

### Using Arguments

Arguments allow you to filter and customize your query:
```graphql
# Adding arguments to our named query
query GetPublishedPosts {
  posts(where: { status: PUBLISH }) {
    nodes {
      id
      title
      date
    }
  }
}
```

### Using Aliases

Aliases let you rename fields in your query response. This is particularly useful when querying the same field with different arguments:

```graphql
# Basic alias usage
query GetPosts {
  publishedPosts: posts(where: { status: PUBLISH }) {
    nodes {
      id
      title
    }
  }
  draftPosts: posts(where: { status: DRAFT }) {
    nodes {
      id
      title
    }
  }
}
```

Aliases can also help match your data structure to your component needs:

```graphql
query GetPostData {
  post(id: "123") {
    postTitle: title      # Will be returned as "postTitle" in response
    publishDate: date     # Will be returned as "publishDate" in response
    author {
      authorName: name    # Will be returned as "authorName" in response
    }
  }
}
```

You can combine aliases with variables for dynamic queries:
```graphql
query GetMultiplePosts($firstId: ID!, $secondId: ID!) {
  firstPost: post(id: $firstId) {
    id
    title
    date
  }
  secondPost: post(id: $secondId) {
    id
    title
    date
  }
}
```

> [!TIP]
> Aliases become even more powerful when combined with [fragments](#using-fragments) to reduce repetition in your queries.

> [!NOTE]
> Aliases only affect the response structure. They don't change how the field is queried or what data is returned.

## Working with Connections

WPGraphQL uses the Relay specification for connections, which provides a standardized way to handle pagination and relationships between objects.

### Understanding Nodes vs Edges

When querying connected data, you have two options: `nodes` or `edges`:

```graphql
# Using nodes (simpler approach)
query GetPosts {
  posts(first: 3) {
    nodes {
      id
      title
    }
  }
}

# Using edges (when you need edge fields)
query GetPosts {
  posts(first: 3) {
    edges {
      # Edge-specific fields
      cursor
      # The connected node
      node {
        id
        title
      }
    }
  }
}
```

- **nodes**: Direct access to the connected items
  - Simpler, cleaner queries
  - Use when you only need the connected items
  - Most common approach

- **edges**: Access to both the item and its connection metadata
  - Provides connection-specific data like cursors
  - Necessary for implementing certain pagination patterns
  - Useful when you need metadata about the connection itself
  - Useful when conditional relational data about nodes is needed and only exposed as an edge field

### Pagination

Connections support cursor-based pagination:

```graphql
# First page
query GetPosts {
  posts(first: 10) {
    nodes {
      id
      title
    }
    # Pagination information
    pageInfo {
      hasNextPage
      endCursor
      hasPreviousPage
      startCursor
    }
  }
}

# Next page using the cursor
query GetMorePosts($after: String) {
  posts(first: 10, after: $after) {
    nodes {
      id
      title
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
```

### Connection Arguments

Common arguments for connections:
- `first`: Number of items to fetch (forward pagination)
- `after`: Cursor to fetch items after (forward pagination)
- `last`: Number of items to fetch (backward pagination)
- `before`: Cursor to fetch items before (backward pagination)

> [!NOTE]
> Backward pagination (`last`/`before`) can be less performant than forward pagination (`first`/`after`).

For a deeper dive into connections, including advanced usage patterns and best practices, see our [Connections Guide](/docs/connections/).

## Working with Variables

### Basic Variables
```graphql
# ✅ Using variables instead of hard-coded values
query GetPost($id: ID!) {
  post(id: $id) {
    title
    date
  }
}
```

Variables are passed separately from the query:
```json
{
  "id": "cG9zdDox"
}
```

When making a request, both the query and variables are sent together:
```javascript
// Example fetch request
fetch('/graphql', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    query: `
      query GetPost($id: ID!) {
        post(id: $id) {
          title
          date
        }
      }
    `,
    variables: {
      id: "cG9zdDox"
    }
  })
})
```

> [!TIP]
> For more detailed examples of making GraphQL requests, including using different clients and authentication, see our [Interacting with WPGraphQL](/docs/interacting-with-wpgraphql/) guide.

### Multiple Variables
```graphql
query GetPosts($status: PostStatusEnum, $first: Int) {
  posts(where: { status: $status }, first: $first) {
    nodes {
      id
      title
    }
  }
}
```

## Using Fragments

### Basic Fragment
```graphql
# Define a reusable fragment
fragment PostFields on Post {
  id
  title
  date
  author {
    node {
      name
    }
  }
}

# Basic usage of the fragment
query GetPosts {
  posts(first: 3) {
    nodes {
      ...PostFields
    }
  }
}
```

### Reusing Fragments
```graphql
# Using the same fragment multiple times
query GetPostsAndAuthorPosts {
  # Get main posts
  posts(first: 3) {
    nodes {
      ...PostFields
      # Get other posts by the same author
      author {
        node {
          # Show the author's other recent posts
          posts(first: 3) {
            nodes {
              ...PostFields
            }
          }
        }
      }
    }
  }
}
```

This demonstrates how the same fragment can be reused to:
1. Get the main posts
2. Get other recent posts by each post's author
All while maintaining consistent field selection.

### Fragments and Components

Fragments are particularly valuable when working with components in your frontend application. A common pattern is to couple a fragment with a specific component:

```graphql
# Define fragment for a PostCard component
fragment PostCardFields on Post {
  id
  title
  excerpt
  date
  featuredImage {
    node {
      sourceUrl
      altText
    }
  }
}

# Define fragment for a PostAuthorBio component
fragment PostAuthorBioFields on User {
  id
  name
  description
  avatar {
    url
  }
}

# Use component fragments in your query
query GetPost($id: ID!) {
  post(id: $id) {
    ...PostCardFields
    author {
      node {
        ...PostAuthorBioFields
      }
    }
  }
}
```

This pattern provides several benefits:
1. **Component-Data Coupling**: The fragment clearly defines what data a component needs
2. **Maintainability**: When a component's data requirements change, you only need to update its fragment
3. **Reusability**: The same fragment can be used anywhere the component is used
4. **Consistency**: Ensures components always receive the data they expect
5. **Developer Experience**: Reduces cognitive load of tracking data requirements across components

> [!TIP]
> Consider co-locating your fragments with your components. This makes it clear what data each component needs and makes maintenance easier.

## Directives

Directives provide a way to modify how fields are executed and returned. In GraphQL, directives are preceded by the `@` symbol and can affect the behavior of fields, fragments, or operations.

### Core Directives

WPGraphQL supports two core GraphQL directives:

#### @include

The `@include` directive includes a field only if the provided condition is true:

```graphql
query GetPost($includeComments: Boolean!) {
  post(id: "1", idType: DATABASE_ID) {
    id
    title
    comments @include(if: $includeComments) {
      nodes {
        content
        author {
          node {
            name
          }
        }
      }
    }
  }
}
```

Variables:
```json
{
  "includeComments": true
}
```

#### @skip

The `@skip` directive excludes a field if the provided condition is true:

```graphql
query GetPost($skipAuthor: Boolean!) {
  posts {
    nodes {
      id
      title
      author {
        node @skip(if: $skipAuthor) {
          name
          email
        }
      }
    }
  }
}
```

Variables:
```json
{
  "skipAuthor": false
}
```

### Using Directives with Fragments

Directives can also be applied to entire fragments:

```graphql
query GetPost($includeAuthor: Boolean!) {
  post(id: "1", idType: DATABASE_ID) {
    id
    title
    ...AuthorFragment @include(if: $includeAuthor)
  }
}

fragment AuthorFragment on Post {
  author {
    node {
      name
      email
      description
    }
  }
}
```

### Common Use Cases

Directives are particularly useful for:

- Conditionally including data based on user permissions (or other criteria)
- Optimizing query performance by excluding unnecessary fields
- Implementing feature flags in your GraphQL queries
- Managing different view states in your application

> [!TIP]
> Use directives to make your queries more flexible and efficient. Instead of maintaining multiple similar queries, you can use directives to conditionally include or exclude fields based on your needs.

### Best Practices

> [!IMPORTANT]
> - Use meaningful variable names that indicate the purpose of the condition
> - Consider the performance impact of fields behind directives
> - Handle both true and false conditions in your client code
> - Document the expected behavior of directive conditions

For more information about GraphQL directives, see the [GraphQL Specification](https://spec.graphql.org/October2021/#sec-Language.Directives).

## Introspection Queries

GraphQL provides introspection capabilities that allow you to query the schema itself. This is a powerful way to explore available types, fields, and operations.

### Basic Schema Exploration
```graphql
# Get all types in the schema
query IntrospectTypes {
  __schema {
    types {
      name
      description
    }
  }
}

# Get details about a specific type
query IntrospectPostType {
  __type(name: "Post") {
    name
    description
    fields {
      name
      type {
        name
      }
    }
  }
}
```

### Common Introspection Use Cases

1. **Field Discovery**
```graphql
# Find available fields on the Post type
query GetPostFields {
  __type(name: "Post") {
    fields {
      name
      description
      type {
        name
        kind
      }
    }
  }
}
```

2. **Enum Value Exploration**
```graphql
# Get possible values for post status
query GetPostStatusEnum {
  __type(name: "PostStatusEnum") {
    enumValues {
      name
      description
    }
  }
}
```

> [!TIP]
> Instead of writing introspection queries manually, you can use the [WPGraphiQL IDE](/docs/wp-graphiql/) which provides a visual interface for exploring the schema.

> [!NOTE]
> Introspection might be disabled in production environments for security reasons. See our [Security Guide](/docs/security/) for more information.

## Best Practices

1. **Always Name Your Operations**
   - Helps with debugging
   - Required for variables
   - Better error messages

2. **Use Variables Instead of String Interpolation**
   - Prevents injection attacks
   - Better caching
   - Cleaner code

3. **Request Only What You Need**
   - Improves performance
   - Reduces response size
   - Better caching

4. **Use Fragments for Reusable Fields**
   - Reduces duplication
   - Easier maintenance
   - Better organization