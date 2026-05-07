# next-wpgraphql

In-tree library that replaces Faust.js for the wpgraphql.com Next.js site.

## What it does

- **Template hierarchy** — given a URI, run a seed query against WPGraphQL, then resolve the most specific template (mimicking WordPress's template hierarchy).
- **Server-only multi-query data fetching** — each template declares one or more named queries with independent variables. Queries run in parallel from `getStaticProps`. No client-side GraphQL on SSR/SSG pages.
- **GET + persisted query IDs** — queries go out as `GET ?queryId=<sha256>` against WPGraphQL Smart Cache, with an APQ-style POST fallback when the server returns `PersistedQueryNotFound`. Mutations always POST.

## Public API

```js
import {
  configure,
  getTemplateStaticProps,
  LayoutProvider,
  useLayoutData,
} from "lib/next-wpgraphql"
```

See `index.d.ts` for full type signatures.

## Status

Phase 0 — library is being built alongside Faust. Templates have not yet migrated.
