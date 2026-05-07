# wpgraphql-client

A small library for building WPGraphQL-backed sites with WordPress-style template resolution, server-side multi-query data fetching, and cache-friendly transport.

It's organized as three layers — a framework-agnostic core and thin adapters for React and Next.js — so any framework with a server-side data fetching primitive (Nuxt's `asyncData`, SvelteKit's `load`, Astro's `getStaticPaths` / frontmatter, Remix's `loader`, etc.) can sit on top of the same core.

## What it provides

- **Template hierarchy** — map a URI to the most specific template (e.g. `single-post-{slug}` → `single-post` → `single` → `singular` → `index`), driven by a seed query against WPGraphQL. Mirrors [WordPress's template hierarchy](https://developer.wordpress.org/themes/basics/template-hierarchy/).
- **Multi-query templates** — each template declares one or more named queries with independent variables. All queries run in parallel server-side, and their top-level GraphQL fields spread into a single flat `data` prop.
- **Server-only data fetching** — there is no `useQuery` hook in the public API. Pages render with all their data already in props. SSR and SSG pages never make a GraphQL request from the browser.
- **Cache-friendly transport** — queries go out as `GET ?queryId=<sha256>&variables=...` so [WPGraphQL Smart Cache](https://github.com/wp-graphql/wp-graphql-smart-cache) (or any HTTP cache in front of WPGraphQL) can cache them by URL. On the first request for a new hash the server returns `PersistedQueryNotFound`; the client falls back to a single APQ-style POST that registers the document, and subsequent requests are pure GETs.
- **Mutations stay POST** — `request()` parses the document, sees the `mutation` keyword, and POSTs with the full query string.
- **Layout queries** — chrome data shared across pages (nav menu, footer, site settings) is registered once on the layout and exposed through framework-native context (React context today; Vue provide/inject, Svelte stores, etc. would each ship their own adapter).

## Architecture

```
wpgraphql-client/
  core/    framework-agnostic   fetch + Web Crypto + GraphQL
  react/   React adapter        LayoutProvider / useLayoutData
  next/    Next.js adapter      getStaticProps wrapper
  index.js aggregate            re-exports the three layers
```

Each layer can be extracted as its own package later:

| in-tree                  | future package          | depends on                     |
|--------------------------|-------------------------|--------------------------------|
| `wpgraphql-client/core`  | `@wpgraphql/client`     | `graphql`, `graphql-tag` (peer)|
| `wpgraphql-client/react` | `@wpgraphql/react`      | `@wpgraphql/client`, `react`   |
| `wpgraphql-client/next`  | `@wpgraphql/next`       | `@wpgraphql/client`, `next`    |
| (new)                    | `@wpgraphql/nuxt`       | `@wpgraphql/client`, `vue`     |
| (new)                    | `@wpgraphql/sveltekit`  | `@wpgraphql/client`, `svelte`  |
| (new)                    | `@wpgraphql/astro`      | `@wpgraphql/client`            |

The aggregate import (`from "lib/wpgraphql-client"`) is convenient for sites that use everything at once. Apps that want to be specific can import directly from a layer:

```js
// just the core, e.g. inside an API route or non-React framework
import { request, resolveTemplate, getLayoutData } from "lib/wpgraphql-client/core"

// just the React adapter
import { LayoutProvider, useLayoutData } from "lib/wpgraphql-client/react"

// just the Next.js adapter
import { getTemplateStaticProps } from "lib/wpgraphql-client/next"
```

## What's in each layer

### `core/` — framework-agnostic

| export | purpose |
|---|---|
| `request({ query, variables, operationName, endpoint })` | Low-level GraphQL client. GET+queryId for queries, POST APQ fallback on `PersistedQueryNotFound`, POST always for mutations, 6KB URL-length guard. |
| `setFetch(fn)` | Inject a fetch implementation (mostly for tests). Defaults to `globalThis.fetch`. |
| `getGraphqlEndpoint()` | Reads `NEXT_PUBLIC_WPGRAPHQL_URL` or `WPGRAPHQL_URL`. Trims trailing slashes; throws if both are unset. |
| `sha256(string)` | Hex SHA-256 via Web Crypto. |
| `printQuery(documentOrString)` | gql AST → stable string (uses `graphql/language/printer`). |
| `getOperation(document)` | Returns `"query"` / `"mutation"` / `"subscription"` from a parsed document. |
| `SEED_QUERY` / `normalizeSeed(response, uri)` | The URI seed query and a normalizer that flattens the GraphQL response into the shape the hierarchy resolver expects. |
| `resolveTemplateName(seed, registry)` / `buildCandidateNames(seed)` | Pure hierarchy resolver. |
| `configure({ templates, Layout })` / `getRegistry()` | Module-level registry of templates and layout. |
| `runQueries(entries, reqCtx)` | Run an array of `[name, { query, variables, skip }]` entries in parallel and spread their `data` envelopes into one flat object. |
| `resolveTemplate({ uri, params })` | Framework-agnostic orchestrator. Runs the seed query, resolves a template, executes its queries + the layout queries in parallel, returns `{ template, data, layoutData, uri, seed }` or `{ notFound, uri, seed }`. |
| `getLayoutData(reqCtx?)` | Run only the configured `Layout.queries`. Useful for non-template pages. |

The core has no React or Next.js imports. It runs anywhere with `fetch` and Web Crypto (Node 22+, Cloudflare Workers, Deno, modern browsers).

### `react/` — React adapter

| export | purpose |
|---|---|
| `LayoutProvider` | `<LayoutProvider value={layoutData}>` — provides layout data to descendants via React context. |
| `useLayoutData()` | Hook returning the flat layout data object. |

Works with any React framework (Next.js, Remix, Astro w/ React, Gatsby) — it's just a thin context wrapper.

### `next/` — Next.js adapter

| export | purpose |
|---|---|
| `getTemplateStaticProps(ctx, opts?)` | Reads URI from `ctx.params.wordpressNode`, calls `resolveTemplate()`, formats the result as Next's `{ props, revalidate }` / `{ notFound, revalidate }`. |

This is the only file in the library that knows about `GetStaticPropsContext`. Other framework adapters would each provide a similar thin wrapper around `resolveTemplate()`.

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

### 3. Wire the catch-all route (Next.js)

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

#### Wiring an adapter for another framework

Build a thin equivalent of `getTemplateStaticProps` over `resolveTemplate()`. Sketch for Nuxt 3:

```ts
// pages/[...slug].vue
<script setup>
import { resolveTemplate } from "@wpgraphql/client"
const route = useRoute()
const uri = "/" + (Array.isArray(route.params.slug)
  ? route.params.slug.join("/") : route.params.slug ?? "") + "/"
const result = await resolveTemplate({ uri, params: route.params })
if (result.notFound) throw createError({ statusCode: 404 })
provide("layoutData", result.layoutData)
</script>
```

Sketch for SvelteKit `+page.server.ts`:

```ts
import { resolveTemplate } from "@wpgraphql/client"

export async function load({ params }) {
  const uri = "/" + (params.slug ?? "") + "/"
  const result = await resolveTemplate({ uri, params })
  if (result.notFound) throw error(404)
  return result
}
```

The point: `resolveTemplate()` returns plain data and the framework adapter shapes it into the framework's preferred load/route/get-props convention.

## Templates

A template is a component that renders a `data` prop. Its `queries` static field declares what to fetch.

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

surfaces as `data.posts.nodes`. If you declare two queries with distinct top-level fields, both fields appear on `data` side by side. If two queries declare the same top-level field, the later entry wins — use field aliases to disambiguate.

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

Before resolving a template, `resolveTemplate()` runs `SEED_QUERY` to identify the node behind the URI:

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

## GraphQL client transport

`request({ query, variables, operationName, endpoint })`:

1. Print the query to a stable string (via `graphql/language/printer`).
2. Compute `queryId = sha256(printed)`.
3. If the parsed AST is a mutation → POST `{ query, variables, operationName }`. Done.
4. Otherwise build `GET ${endpoint}?queryId=${queryId}&variables=${enc}&operationName=...`.
5. If the GET URL would exceed 6000 chars, fall back to POST with `{ queryId, query, variables }` (correct, but not network-cacheable).
6. Send the GET. If the response contains a GraphQL error matching `PersistedQueryNotFound`, retry as POST with both `queryId` and `query` so the server registers the document. Subsequent GETs hit.

`setFetch(fn)` is provided for tests; the default uses `globalThis.fetch`.

## Server-only by design

The library never exports a `useQuery` hook. Templates and components consume data from `data` / `useLayoutData()` props rendered server-side. To enforce this in CI:

- Templates and components don't import `request()` from a `useEffect` (verifiable with grep)
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
- `resolve-template.test.js` — happy path, notFound, front-page bypass, input validation
- `get-template-static-props.test.js` — Next.js adapter happy path, notFound + revalidate, skip predicates
- `templates.test.js` — registry configure/getRegistry

## File layout

```
wpgraphql-client/
  index.js                      aggregate public API

  core/
    index.js                    re-exports core surface
    client.js                   fetch client
    endpoint.js                 env-var endpoint resolver
    hash.js                     sha256 (Web Crypto)
    print.js                    gql AST → string + getOperation
    seed-query.js               SEED_QUERY + normalizeSeed
    hierarchy.js                pure resolveTemplateName / buildCandidateNames
    templates.js                configure / getRegistry
    run-queries.js              parallel runner used by both
                                resolveTemplate and getLayoutData
    resolve-template.js         framework-agnostic orchestrator
    get-layout-data.js          run-only-Layout-queries helper

  react/
    index.js                    re-exports
    layout.js                   LayoutProvider / useLayoutData

  next/
    index.js                    re-exports
    get-template-static-props.js
                                Next.js getStaticProps wrapper around
                                core/resolveTemplate

  package.json                  type:module so node:test sees ESM
  __tests__/                    node:test unit tests
```

## Requirements

- **Core**: Node 22+ (Web Crypto + `node:test`), or any modern runtime with `fetch` and `globalThis.crypto.subtle`. Peer deps: `graphql`, `graphql-tag`.
- **React adapter**: React 18+.
- **Next.js adapter**: Next.js 15+ (`pages/` router).
- A WPGraphQL endpoint, ideally with WPGraphQL Smart Cache for the GET caching benefit.
