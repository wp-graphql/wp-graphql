# Layout

`Layout.queries` are the same shape as [`Template.queries`](./templates.md), but live on the layout component. They run in parallel with the template's queries and the result is exposed through framework-native context (React `useLayoutData()` today).

## Why a separate layer?

Chrome data — nav menu, footer links, site title, theme settings — is shared across pages. If every template query has to merge a `NavMenuFragment`, every page-specific cache entry depends on the menu. Updating the menu invalidates every page.

Pulling chrome into `Layout.queries` solves both problems:

- Each chrome query is its own GET-with-queryId, cached independently
- Templates don't have to know about chrome data
- One menu invalidation only invalidates the menu cache entry

## Defining `Layout.queries`

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

The `Layout` export is registered via `configure({ templates, Layout })` (see the [Quickstart](../README.md#quickstart)).

## Reading layout data

In React, use `useLayoutData()`:

```js
// src/components/Site/SiteHeader.js
import { useLayoutData } from "lib/wpgraphql-client"

export default function SiteHeader() {
  const layoutData = useLayoutData()
  const items = layoutData?.menu?.menuItems?.nodes ?? []
  // …
}
```

Layout queries are spread into `layoutData` the same way template queries are spread into `data`. So a layout query that selects `menu { menuItems { nodes { ... } } }` exposes `layoutData.menu.menuItems.nodes`.

In other React frameworks, the `LayoutProvider` from `wpgraphql-client/react` works the same way:

```jsx
<LayoutProvider value={layoutData}>
  <App />
</LayoutProvider>
```

For non-React frameworks, the framework adapter would expose layout data via the framework's native context (Vue's `provide`/`inject`, Svelte stores, etc.). See [Architecture → Adapter sketches](./architecture.md#adapter-sketches).

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

`getLayoutData(reqCtx?)` runs only the configured `Layout.queries` and returns the same flat shape as `result.layoutData` from `resolveTemplate()`.

## Custom data needs

For pages that need their own data (not just chrome), call `request()` directly:

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

`request()` accepts strings or `DocumentNode`s and is the same client `Template.queries` and `Layout.queries` use under the hood. Use it for `getStaticPaths`, API routes, sitemap generation, RSS feeds, etc.
