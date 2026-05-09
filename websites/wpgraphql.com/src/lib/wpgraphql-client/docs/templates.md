# Templates

A template is a React component that renders a `data` prop. Its `queries` static field declares what to fetch.

## Example

```js
// src/wp-templates/archive-post.js
import gql from "graphql-tag"
import PostPreview, { PostPreviewFragment } from "components/Preview/PostPreview"
import SiteLayout from "components/Site/SiteLayout"

export default function ArchivePost({ data }) {
  const posts = data?.posts?.nodes ?? []
  return (
    <SiteLayout>
      <ul>{posts.map((p) => <PostPreview key={p.id} post={p} />)}</ul>
    </SiteLayout>
  )
}

ArchivePost.queries = {
  posts: {
    query: gql`
      query ArchivePost_Posts($first: Int) {
        posts(first: $first) { nodes { ...PostPreview } }
      }
      ${PostPreviewFragment}
    `,
    variables: ({ uri, seed, params }) => ({ first: 100 }),
    skip: ({ seed }) => false, // optional
  },
}
```

## `Template.queries` shape

Each entry in `Template.queries` describes one independent GraphQL operation:

| key | type | notes |
|---|---|---|
| `query` | `string \| DocumentNode` | The GraphQL query. Use `gql` from `graphql-tag` so fragments interpolate. |
| `variables` | `(reqCtx) => object` | Optional. Receives `{ uri, seed, params }`. Defaults to `{}`. |
| `skip` | `(reqCtx) => boolean` | Optional. Return `true` to skip this query for the current request. |

`reqCtx` is shaped:

```ts
{
  uri: string,        // e.g. "/blog/page/2/"
  seed: object,       // the normalized seed (see ../docs/transport.md#seed-query)
  params: object,     // raw route params (e.g. { wordpressNode: ["blog", "page", "2"] })
}
```

## How `data` is shaped

Each query's `data` envelope is **spread** into the template's `data` prop. So:

```graphql
query ArchivePost_Posts { posts { nodes { ... } } }
```

surfaces as `data.posts.nodes`. If you declare two queries with distinct top-level fields, both fields appear on `data` side by side:

```js
ArchivePost.queries = {
  posts:    { query: gql`query Q1 { posts { nodes { id } } }` },
  settings: { query: gql`query Q2 { generalSettings { title } }` },
}

// data === { posts: { nodes: [...] }, generalSettings: { title: "..." } }
```

If two queries declare the same top-level field, the **later entry wins**. Use field aliases to disambiguate:

```js
ArchivePost.queries = {
  recent:    { query: gql`query Q1 { recent: posts(first: 5) { nodes { id } } }` },
  trending:  { query: gql`query Q2 { trending: posts(where: { ... }) { nodes { id } } }` },
}

// data === { recent: { nodes: [...] }, trending: { nodes: [...] } }
```

## Why split queries?

WPGraphQL Smart Cache (and any HTTP cache in front of WPGraphQL) caches GET requests by URL. If you put everything into one giant query, any change to any piece of that data invalidates the whole cache entry.

Splitting into one query per concern means each cache entry has its own invalidation lifecycle:

- The `posts` query for `/blog/` invalidates when posts change
- The `navMenu` layout query (see [Layout](./layout.md)) invalidates only when the menu changes
- The `recent` and `trending` archive queries invalidate independently

The cost — one extra HTTP request — is amortized by GET cache hits at the CDN/Smart Cache layer.

## Template hierarchy

Templates are registered by name in a flat object passed to `configure()`. The hierarchy resolver picks the most specific name that exists in the registry. See [Hierarchy](./hierarchy.md) for the candidate-list algorithm and the per-content-type slot order.

## Variables

Use `variables(reqCtx)` to pull values from the request:

```js
Singular.queries = {
  post: {
    query: gql`query Singular_Post($uri: ID!) { post(id: $uri, idType: URI) { id title content } }`,
    variables: ({ uri }) => ({ uri }),
  },
}
```

Or from the seed for cases where the URI shape doesn't match what GraphQL expects:

```js
Category.queries = {
  category: {
    query: gql`query Category_Node($id: ID!) { category(id: $id, idType: URI) { ... } }`,
    variables: ({ seed }) => ({ id: seed?.uri }),
  },
}
```

## Skipping a query

Return `true` from `skip(reqCtx)` to omit the query for that request. Useful when one query covers most pages but a few should opt out:

```js
ArchivePost.queries = {
  posts: { query: gql`...`, variables: () => ({ first: 100 }) },
  featured: {
    query: gql`...`,
    skip: ({ uri }) => !uri.startsWith("/blog/"),
  },
}
```
