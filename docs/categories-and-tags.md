---
uri: "/docs/categories-and-tags/"
title: "Categories and Tags"
---

WPGraphQL provides support for querying Categories, Tags and Custom Taxonomies in various ways.

## Introduction
Categories and Tags are WordPress taxonomies used to organize and classify content:
- **Categories** are hierarchical (can have parent-child relationships)
- **Tags** are flat (no hierarchy)
- Both can be used to organize and filter content

This guide assumes familiarity with [GraphQL Concepts](/docs/intro-to-graphql/) and [writing GraphQL Queries](/docs/intro-to-graphql/#queries-and-mutation).

## Querying Categories and Tags

Categories and Tags are built-in taxonomies in WordPress, and WPGraphQL supports querying them out of the box.

Below are some common examples of querying Categories and Tags with WPGraphQL.

### Lists of Categories

Below is an example of querying a list of Category terms. In this example, we ask for `categories`, and then on the `categories` field we ask for `edges`, `node` then the `id`, and `name` of each `node` (each category term).

```graphql
query GetCategoryEdges {
  categories {
    edges {
      node {
        id
        name
      }
    }
  }
}
```

![Screenshot of a query for a list of Categories, edges and nodes](./images/categories-query-edges-nodes.png)

Below is the same query, but asking for nodes directly, skipping the `edges` field.

```graphql
query GetCategoryNodes {
  categories {
    nodes {
      id
      name
    }
  }
}
```

![Screenshot of a query for a list of Categories nodes](./images/categories-query-nodes.png)

>[!TIP] **Edges? Nodes? Huh?** If you're not yet familiar with GraphQL Connections, edges and nodes will look like a foreign concept. To get a better understanding of Edges, Nodes and other conventions of GraphQL Connections: [Read the GraphQL Connections Guide](/docs/connections/)

### Lists of Tags

Querying Tags is almost identical to querying Categories. You could execute the same queries as above, but swap out `categories` for `tags` and it would work.

For example:

```graphql
query GetTagNodes {
  tags {
    nodes {
      id
      name
    }
  }
}
```

![Screenshot of a query for Tags nodes](./images/tags-query-nodes.png)

### Hierarchical vs Flat Taxonomies

One of the primary differences between Categories and Tags is that Categories are Hierarchical (Category terms can have parent-child relationships with other Category terms) and Tags are flat with no parent-child relationships.

[Read more about working with hierarchical data in WPGraphQL](/docs/hierarchical-data/).

### Filtering a List of Terms

>[!NOTE] The following examples will use Categories and Tags interchangeable, as they operate largely the same. Behind the scenes, they are both Taxonomy Terms and WordPress and WPGraphQL both treat them nearly identically.

Queries for [Connections](/docs/connections/) (lists of nodes) can be filtered. Below are some examples of filtering using the `where` argument. There are many arguments available for filtering Terms.

#### Query Tags with similar name

The following example shows querying tags with a name like "Gatsby".

```graphql
query GetTagNodes {
  tags(where: {nameLike: "Gatsby"}) {
    nodes {
      id
      name
    }
  }
}
```

![Screenshot of a GraphQL query filtering Tags by nameLike](./images/tags-query-by-name.png)

## Available Fields
Both Categories and Tags share these common fields:

```graphql
{
  categories {
    nodes {
      id          # Global ID of the term
      databaseId  # WordPress database ID
      name        # Term name
      slug        # URL-friendly slug
      description # Term description
      uri         # Full path to term archive
      count       # Number of posts with this term
      link        # Full URL to term archive
    }
  }
}
```

Categories have additional hierarchical fields:
```graphql
{
  categories {
    nodes {
      parentId    # Parent term's ID
      ancestors   # List of ancestor terms
      children    # List of child terms
    }
  }
}
```

## Common Use Cases

### Building Navigation Menus
Fetch top-level categories with their children:
```graphql
{
  categories(where: { parent: 0 }) {
    nodes {
      name
      uri
      children {
        nodes {
          name
          uri
        }
      }
    }
  }
}
```

### Tag Clouds
Get the most-used tags:
```graphql
{
  tags(first: 20, where: { orderby: COUNT, order: DESC }) {
    nodes {
      name
      count
      uri
    }
  }
}
```

## Single Term by Global ID

Below is an example of querying a single term (of any Taxonomy) using the `termNode` field and passing the GraphQL [Global ID](/docs/wpgraphql-concepts/).

>[!NOTE] The `termNode` field returns a TermNode [Interface](/docs/interfaces/) Type, which allows for terms of *any* Taxonomy to be queried.

```graphql
{
  category: termNode( id: "dGVybTozNA==" ) {
    __typename
    id
    name
  }
  tag: termNode( id: "dGVybTo0MA==" ) {
    __typename
    id
    name
  }
}
```

![Screenshot of a Query for a Category and a Tag using the termNode field](./images/categories-query-by-global-id.png)

### Single term by URI

The following example shows a query for a single Tag using the uri (the url path) to the term.

```graphql
{
  tag( id: "/tag/gatsby/", idType: URI ) {
    __typename
    id
    name
    uri
  }
}
```

![Screenshot of a GraphQL query for a tag using the URI](./images/tags-query-by-uri.png)

## Mutations

WPGraphQL provides mutations (the ability to change data through GraphQL) for Categories, Tags and Custom Taxonomy Terms that are configured to show in the GraphQL Schema.

WPGraphQL adheres to WordPress access control rights, ensuring data can only be created, updated or deleted by users that have proper permission to do so. Learn more in the [WPGraphQL Security Guide](/docs/security/).

## Create a Category

Below is an example of a GraphQL Mutation to create a Category.

The only required field to create a Category is the `name`.

```graphql
mutation {
  createCategory(input: {name: "New Category"}) {
    category {
      id
      name
    }
  }
}
```

This mutation will only succeed for users with proper capabilities to create Terms in the Category Taxonomy.

### Successful Mutation

If the user executing the mutation is authenticated and has proper capabilities to create a Category, the category will be created and the fields asked for in response will be returned.

![Screenshot of a successful GraphQL Mutation to create a category](./images/categories-mutation-create-success.png)

### Unuccessful Mutation

If the user executing the mutation is not authenticated, or does not have proper capabilities to create a Category, no data will change in WordPress and an error will be returned.

![Screenshot of an unsuccessful GraphQL mutation to create a Category term](./images/categories-mutation-create-not-allowed.png)

## Update Category

Below is an example of a GraphQL Mutation to update a Category. In order to update a Category, the ID of the Category must be known and provided to the input.

```graphql
mutation {
  updateCategory(input: {id: "dGVybTo0NA==", description: "New description..."}) {
    category {
      id
      name
      description
    }
  }
}
```

### Successful Mutation

If the user making the request is authenticated and has proper capabilities to update the Category term, the term will be updated and the specified fields will be returned in the response.

![Screenshot of a successful GraphQL mutation to update a Category](./images/categories-mutation-update-success.png)

### Unuccessful Mutation

If the user making the request is not authenticated, or does not have proper capabilities to update Category terms, no data will change in WordPress and an error will be returned.

![Screenshot of an unsuccessful GraphQL Mutation to update a category](./images/categories-mutation-update-not-allowed.png)

### Delete Category

Below is an example of a GraphQL Mutation to delete a Category term. In order to delete a Category term, the ID of the Category term must be known and provided to the input.

```graphql
mutation {
  deleteCategory(input: {id: "dGVybTo0NA=="}) {
    category {
      id
      name
      description
    }
  }
}
```

### Successful Mutation

If the user making the request is authenticated and has proper capabilities to delete the Category term, the term will be deleted from WordPress and the specified fields will be returned in the response.

![](./images/categories-delete-success.png)

### Unuccessful Mutation

If the user making the request is not authenticated, or does not have proper capabilities to delete Category terms, no data will change in WordPress and an error will be returned.

![Screenshot of an unsuccessful GraphQL Mutation to delete a Category](./images/categories-delete-not-allowed.png)

## Performance Considerations
When working with large sets of terms:
- Use pagination with `first`/`after` or `last`/`before` arguments
- Limit the fields you request to only what you need
- Consider caching responses on the client side

## Related Resources
- [Querying Posts by Taxonomy Terms](/docs/posts/)
- [Working with Custom Taxonomies](/docs/custom-taxonomies/)
- [Pagination and Connections](/docs/connections/)
- [Hierarchical Data in WPGraphQL](/docs/hierarchical-data/)