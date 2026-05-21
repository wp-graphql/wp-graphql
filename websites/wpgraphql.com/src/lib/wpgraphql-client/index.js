// Aggregate public API. The library is organized into three layers, each
// extractable as its own package later:
//
//   ./core   — framework-agnostic (fetch + Web Crypto + GraphQL)
//   ./react  — React context for layout data
//   ./next   — Next.js getStaticProps adapter
//
// Consumers can import the aggregated surface from "lib/wpgraphql-client"
// or pull from a specific layer (e.g. "lib/wpgraphql-client/core") if they
// don't need React or Next.js.

export {
  configure,
  getRegistry,
  request,
  setFetch,
  getGraphqlEndpoint,
  sha256,
  printQuery,
  getOperation,
  SEED_QUERY,
  normalizeSeed,
  resolveTemplateName,
  buildCandidateNames,
  resolveTemplate,
  getLayoutData,
  runQueries,
} from "./core/index.js"

export { LayoutProvider, useLayoutData } from "./react/index.js"

export { getTemplateStaticProps } from "./next/index.js"
