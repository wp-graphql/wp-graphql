# wpgraphql-client

A small library for building WPGraphQL-backed Next.js sites with WordPress-style template resolution and server-side multi-query data fetching.

## What it provides

- **Template hierarchy** — map a URI to the most specific template (e.g. `single-post-{slug}` → `single-post` → `single` → `singular` → `index`), driven by a seed query against WPGraphQL. Mirrors [WordPress's template hierarchy](https://developer.wordpress.org/themes/basics/template-hierarchy/).
- **Multi-query templates** — each template declares one or more named queries with independent variables. All queries run in parallel from `getStaticProps`, and their top-level GraphQL fields spread into a single flat `data` prop.
- **Server-only data fetching** — there is no `useQuery` hook. Pages render with all their data already in props. SSR and SSG pages never make a GraphQL request from the browser.
- **Cache-friendly transport** — queries go out as `GET ?queryId=<sha256>&variables=...` so [WPGraphQL Smart Cache](https://github.com/wp-graphql/wp-graphql-smart-cache) (or any HTTP cache in front of WPGraphQL) can cache them by URL. On the first request for a new hash the server returns `PersistedQueryNotFound`; the client falls back to a single APQ-style POST that registers the document, and subsequent requests are pure GETs.
- **Mutations stay POST** — `request()` parses the document, sees the `mutation` keyword, and POSTs with the full query string.
- **Layout queries** — chrome data shared across pages (nav menu, footer, site settings) is registered once on the layout and exposed through React context.

## Public API

```js
import {
  configure,         // configure({ templates, Layout })
  getTemplateStaticProps,
                     // Next.js getStaticProps for the catch-all route
  getLayoutData,     // run Layout.queries from a non-template page
  LayoutProvider,    // <LayoutProvider value={layoutData}>
  useLayoutData,     // hook — returns the flat layoutData object
  request,           // low-level GraphQL client
  resolveTemplateName,
                     // pure hierarchy resolver (seed + registry → template name)
  getGraphqlEndpoint,
                     // env-var-based endpoint resolution
  SEED_QUERY,        // the URI seed query (advanced use)
  normalizeSeed,     // GraphQL response → flat seed object
} from "lib/wpgraphql-client"
```

## Setup

### 1. Configure the registry

Call `configure()` once at module load. The site's catch-all and any custom pages should import this side-effect module:

```js
// src/lib/wpgraphql-client-config.js
import { configure } from "./wpgraphql-client"
import templates from "wp-templates"
import { Layout } from "components/Site/SiteLayout"

configure({ templates, Layout })
```

### 2. Set the endpoint

Set one of these env vars to the WPGraphQL endpoint:

- `NEXT_PUBLIC_WPGRAPHQL_URL` — preferred, available to the client bundle
- `WPGRAPHQL_URL` — server-only fallback

Trailing slashes are stripped. If neither is set, `request()` throws.

### 3. Wire the catch-all route

```js
// src/pages/[...wordpressNode].js
import { getTemplateStaticProps, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"
import templates from "wp-templates"

export default function Page({ template, data, layoutData, seed, uri }) {
  const Template = templates[template]
  if (!Template) return null
  return (
    <LayoutProvider value={layoutData}>
      <Template data={data} seed={seed} uri={uri} />
    </LayoutProvider>
  )
}

export const getStaticProps = (ctx) =>
  getTemplateStaticProps(ctx, { revalidate: 5 })

export const getStaticPaths = async () => ({
  paths: [],
  fallback: "blocking",
})
```

For the front page, use the same body without `getStaticPaths`.

## Templates

A template is a React component that renders a `data` prop. Its `queries` static field declares what to fetch.

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

### `Template.queries` shape

| key | type | notes |
|---|---|---|
| `query` | `string \| DocumentNode` | The GraphQL query. Use `gql` from `graphql-tag` so fragments interpolate. |
| `variables` | `(reqCtx) => object` | Optional. Receives `{ uri, seed, params }`. Defaults to `{}`. |
| `skip` | `(reqCtx) => boolean` | Optional. Return `true` to skip this query for the current request. |

### How `data` is shaped

Each query's `data` envelope is **spread** into the template's `data` prop. So:

```graphql
query ArchivePost_Posts { posts { nodes { ... } } }
```

surfaces as `data.posts.nodes`. If you declare two queries that each return distinct top-level fields, both fields appear on `data` side by side. If two queries declare the same top-level field, the later entry wins — use field aliases to disambiguate.

## Layout (chrome) queries

`Layout.queries` follow the same shape as `Template.queries` but live on the layout component. They run in parallel with the template's queries and the result is exposed via context.

```js
// src/components/Site/SiteLayout.js
import gql from "graphql-tag"
import Header, { NavMenuFragment } from "./SiteHeader"
import Footer from "./SiteFooter"

export default function SiteLayout({ children }) {
  return (<><Header />{children}<Footer /></>)
}

export const Layout = {
  queries: {
    navMenu: {
      query: gql`
        query Layout_NavMenu { ...NavMenu }
        ${NavMenuFragment}
      `,
      variables: () => ({}),
    },
  },
}
```

```js
// src/components/Site/SiteHeader.js
import { useLayoutData } from "lib/wpgraphql-client"

export default function SiteHeader() {
  const layoutData = useLayoutData()
  const items = layoutData?.menu?.menuItems?.nodes ?? []
  // …
}
```

Layout queries are spread into `layoutData` the same way template queries are spread into `data`.

## Custom (non-template) pages

For pages outside the catch-all that still want shared chrome, pull layout data in `getStaticProps` and wrap the page in `LayoutProvider`:

```js
import { getLayoutData, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"
import SiteLayout from "components/Site/SiteLayout"

export default function Community({ layoutData }) {
  return (
    <LayoutProvider value={layoutData}>
      <SiteLayout>{/* … */}</SiteLayout>
    </LayoutProvider>
  )
}

export async function getStaticProps() {
  const layoutData = await getLayoutData()
  return { props: { layoutData }, revalidate: 30 }
}
```

For pages with their own data needs, call `request()` directly:

```js
import { request } from "lib/wpgraphql-client"

const NOT_FOUND_QUERY = /* GraphQL */ `
  query NotFoundQuery { menu(id: "Primary Nav", idType: NAME) { /* … */ } }
`

export async function getStaticProps() {
  const result = await request({ query: NOT_FOUND_QUERY })
  return { props: { menu: result?.data?.menu ?? null }, revalidate: 30 }
}
```

`request()` accepts strings or `DocumentNode`s. Use it for `getStaticPaths`, API routes, sitemap generation, RSS feeds, etc.

## Hierarchy resolution

Given a normalized seed object, `resolveTemplateName(seed, registry)` walks this candidate list (most specific first) and returns the first key that exists in the registry:

| candidate | when |
|---|---|
| `front-page` | `seed.isFrontPage` |
| `home` | `seed.isPostsPage` |
| `single-{postType}-{slug}` → `single-{postType}` → `single` → `singular` | content node singletons |
| `page-{slug}` → `page` → `singular` | `__typename === "Page"` |
| `category-{slug}` → `category` → `archive` | `__typename === "Category"` |
| `tag-{slug}` → `tag` → `archive` | `__typename === "Tag"` |
| `taxonomy-{tax}-{slug}` → `taxonomy-{tax}` → `taxonomy` → `archive` | other terms |
| `author-{slug}` → `author` → `archive` | `__typename === "User"` |
| `archive-{postType}` → `archive` | `__typename === "ContentType"` |
| `404` | no node and not the front page |
| `index` | final fallback |

Slugs, post type names, and taxonomy names are normalized to lowercase. The candidate list is deduplicated. Adding new specificity slots is just adding entries to the registry — no code changes.

## Seed query

Before resolving a template, `getTemplateStaticProps` runs `SEED_QUERY` to identify the node behind the URI:

```graphql
query WpGraphQLClientSeed($uri: String!) {
  node: nodeByUri(uri: $uri) {
    __typename id uri
    ... on ContentNode { databaseId slug isPreview isRestricted
      contentType { node { name graphqlSingleName } }
      ... on NodeWithTitle { title } }
    ... on TermNode { slug taxonomyName }
    ... on User { slug }
    ... on ContentType { name label graphqlSingleName }
  }
  generalSettings { title description }
}
```

`normalizeSeed(response, uri)` flattens the response into the shape the hierarchy resolver expects:

```ts
{
  uri, node, typename, id, slug, postType, taxonomy,
  isFrontPage, isPostsPage, generalSettings,
}
```

The seed query is itself sent as a GET-with-queryId, so it benefits from the same caching as the template queries.

## GraphQL client

`request({ query, variables, operationName, endpoint })` is the low-level entry point. Behavior:

1. Print the query to a stable string (via `graphql/language/printer`).
2. Compute `queryId = sha256(printed)`.
3. If the parsed AST is a mutation → POST `{ query, variables, operationName }`. Done.
4. Otherwise build `GET ${endpoint}?queryId=${queryId}&variables=${enc}&operationName=...`.
5. If the GET URL would exceed 6000 chars, fall back to POST with `{ queryId, query, variables }` (correct, but not network-cacheable).
6. Send the GET. If the response contains a GraphQL error matching `PersistedQueryNotFound`, retry as POST with both `queryId` and `query` so the server registers the document. Subsequent GETs hit.

`setFetch(fn)` is provided for tests; the default uses `globalThis.fetch`.

## Server-only by design

The library never exports a `useQuery` hook. Templates and components consume data from `data` / `useLayoutData()` props rendered server-side. To enforce this in CI:

- Templates and components don't import from `wpgraphql-client/client` (verifiable with grep)
- A 6000-byte GET URL guard prevents oversized variable payloads
- All queries run from `getStaticProps` (or `getServerSideProps` / API routes) — never from `useEffect`

## Testing

Unit tests use `node:test` (Node 22+ built-in). Run them with:

```sh
npm run test:unit
```

Coverage areas:

- `endpoint.test.js` — env-var precedence, trimming, missing-var error
- `print.test.js` / `hash.test.js` — AST printing, sha256 known-vector digests
- `client.test.js` — GET URL shape, APQ retry, mutation always POSTs, URL-length guard
- `hierarchy.test.js` — every hierarchy slot, slug normalization, registry fallthrough
- `seed-query.test.js` — response → normalized seed for Page / Post / Category / ContentType / front-page / missing-node
- `get-template-static-props.test.js` — happy path, notFound, front-page routing, revalidate, skip predicates
- `templates.test.js` — registry configure/getRegistry

## Layout of the library

```
wpgraphql-client/
  index.js                      public exports
  client.js                     fetch client
  endpoint.js                   env-var endpoint resolver
  hash.js                       sha256 (Web Crypto)
  print.js                      gql AST → string
  seed-query.js                 SEED_QUERY + normalizeSeed
  hierarchy.js                  pure resolveTemplateName
  templates.js                  configure / getRegistry
  layout.js                     LayoutProvider / useLayoutData
  get-template-static-props.js  Next.js adapter + getLayoutData
  package.json                  type:module so node:test sees ESM
  __tests__/                    node:test unit tests
```

## Requirements

- Next.js 15+ (`pages/` router)
- Node 22+ (Web Crypto + `node:test`)
- A WPGraphQL endpoint, optionally with WPGraphQL Smart Cache for the GET caching benefit
