# wpgraphql-client

A small library for building WPGraphQL-backed sites with WordPress-style template resolution, server-side multi-query data fetching, and cache-friendly transport.

It's organized as three layers — a framework-agnostic core and thin adapters for React and Next.js — so any framework with a server-side data fetching primitive (Nuxt's `asyncData`, SvelteKit's `load`, Astro's frontmatter, Remix's `loader`, etc.) can sit on top of the same core.

## What it provides

- **Template hierarchy** — map a URI to the most specific template (e.g. `single-post-{slug}` → `single-post` → `single` → `singular` → `index`), driven by a seed query against WPGraphQL. Mirrors [WordPress's template hierarchy](https://developer.wordpress.org/themes/basics/template-hierarchy/). [Full hierarchy →](./docs/hierarchy.md)
- **Multi-query templates** — each template declares one or more named queries with independent variables. All queries run in parallel server-side, and their top-level GraphQL fields spread into a single flat `data` prop. [Templates guide →](./docs/templates.md)
- **Server-only data fetching** — there is no `useQuery` hook in the public API. Pages render with all their data already in props.
- **Cache-friendly transport** — queries go out as `GET ?queryId=<sha256>&variables=...` so [WPGraphQL Smart Cache](https://github.com/wp-graphql/wp-graphql-smart-cache) (or any HTTP cache in front of WPGraphQL) can cache them by URL. APQ-style POST fallback registers new query hashes; subsequent requests are pure GETs. [Transport details →](./docs/transport.md)
- **Layout queries** — chrome data shared across pages (nav menu, footer, site settings) is registered once on the layout and exposed through framework-native context. [Layout guide →](./docs/layout.md)

## Architecture in one paragraph

`core/` is a framework-agnostic data layer (`fetch` + Web Crypto + GraphQL). `react/` is a thin React context for layout data. `next/` is a thin `getStaticProps` wrapper around the core's `resolveTemplate()`. The three layers are extractable as separate packages later (`@wpgraphql/client`, `@wpgraphql/react`, `@wpgraphql/next`) and other framework adapters slot in alongside (`@wpgraphql/nuxt`, `@wpgraphql/sveltekit`, etc.). [Architecture details →](./docs/architecture.md)

## Quickstart

### 1. Configure

```js
// src/lib/wpgraphql-client-config.js
import { configure } from "./wpgraphql-client"
import templates from "wp-templates"
import { Layout } from "components/Site/SiteLayout"

configure({ templates, Layout })
```

### 2. Set the endpoint

```sh
NEXT_PUBLIC_WPGRAPHQL_URL=https://your-wp-site.com/graphql
```

### 3. Wire the catch-all (Next.js)

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

export const getStaticPaths = async () => ({ paths: [], fallback: "blocking" })
```

For non-Next.js frameworks, build a thin equivalent over `resolveTemplate()` from `core/`. See [Architecture → Adapter sketches](./docs/architecture.md#adapter-sketches).

### 4. Write a template

```js
// src/wp-templates/archive-post.js
import gql from "graphql-tag"

export default function ArchivePost({ data }) {
  return <ul>{data.posts.nodes.map((p) => <li key={p.id}>{p.title}</li>)}</ul>
}

ArchivePost.queries = {
  posts: {
    query: gql`query ArchivePost_Posts($first: Int) { posts(first: $first) { nodes { id title } } }`,
    variables: () => ({ first: 100 }),
  },
}
```

[Full templates guide →](./docs/templates.md)

## Documentation

- **[Architecture](./docs/architecture.md)** — the three layers, future packages, per-layer exports, framework adapter sketches
- **[Templates](./docs/templates.md)** — writing templates, the `queries` shape, the `data` prop shape
- **[Layout](./docs/layout.md)** — `Layout.queries`, `useLayoutData`, custom (non-template) pages
- **[Hierarchy](./docs/hierarchy.md)** — the WordPress template hierarchy as candidate-list resolution
- **[Transport](./docs/transport.md)** — `request()`, the seed query, GET+queryId, APQ, server-only design
- **[Testing](./docs/testing.md)** — `node:test` setup, coverage areas, file layout, requirements

## Roadmap

- **[Preview](./docs/preview.md)** — design for an Application Password + shared secret flow that lets WordPress editors preview drafts on the headless site. Not implemented yet; doc captures the intended library API, site wiring, and WP-side setup so we can build to it.
