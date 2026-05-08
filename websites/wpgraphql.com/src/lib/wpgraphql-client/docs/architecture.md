# Architecture

`wpgraphql-client` is organized as three layers, each with a clear scope and dependency footprint:

```
wpgraphql-client/
  core/    framework-agnostic   fetch + Web Crypto + GraphQL
  react/   React adapter        LayoutProvider / useLayoutData
  next/    Next.js adapter      getStaticProps wrapper
  index.js aggregate            re-exports the three layers
```

## Future package layout

Each layer can be extracted as its own package later. The current in-tree organization is the same shape, so the extraction is mechanical.

| in-tree                  | future package          | depends on                         |
|--------------------------|-------------------------|------------------------------------|
| `wpgraphql-client/core`  | `@wpgraphql/client`     | `graphql`, `graphql-tag` (peer)    |
| `wpgraphql-client/react` | `@wpgraphql/react`      | `@wpgraphql/client`, `react`       |
| `wpgraphql-client/next`  | `@wpgraphql/next`       | `@wpgraphql/client`, `next`        |
| (new)                    | `@wpgraphql/nuxt`       | `@wpgraphql/client`, `vue`         |
| (new)                    | `@wpgraphql/sveltekit`  | `@wpgraphql/client`, `svelte`      |
| (new)                    | `@wpgraphql/astro`      | `@wpgraphql/client`                |

## Picking imports

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

## Adapter sketches

`resolveTemplate()` returns plain data; framework adapters shape it into the framework's preferred load/route/get-props convention.

### Nuxt 3

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

### SvelteKit

```ts
// +page.server.ts
import { resolveTemplate } from "@wpgraphql/client"
import { error } from "@sveltejs/kit"

export async function load({ params }) {
  const uri = "/" + (params.slug ?? "") + "/"
  const result = await resolveTemplate({ uri, params })
  if (result.notFound) throw error(404)
  return result
}
```

### Astro

```astro
---
// src/pages/[...slug].astro
import { resolveTemplate } from "@wpgraphql/client"

const slug = Astro.params.slug ?? ""
const result = await resolveTemplate({ uri: "/" + slug + "/", params: Astro.params })
if (result.notFound) return Astro.redirect("/404")
---
<Layout data={result.layoutData}>
  <Template data={result.data} />
</Layout>
```

The pattern is the same in each: extract URI from the framework's route params, call `resolveTemplate()`, branch on `notFound`, then hand the result to the framework's render mechanism.
