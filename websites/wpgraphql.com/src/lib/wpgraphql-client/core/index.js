// Framework-agnostic core. No React, no Next.js. Could be consumed by any
// JS environment with fetch + Web Crypto (Node 22+, Cloudflare Workers,
// Deno, modern browsers).

export { request, setFetch } from "./client.js"
export { getGraphqlEndpoint } from "./endpoint.js"
export { sha256 } from "./hash.js"
export { printQuery, getOperation } from "./print.js"
export { SEED_QUERY, normalizeSeed } from "./seed-query.js"
export { resolveTemplateName, buildCandidateNames } from "./hierarchy.js"
export { configure, getRegistry } from "./templates.js"
export { resolveTemplate } from "./resolve-template.js"
export { getLayoutData } from "./get-layout-data.js"
export { runQueries } from "./run-queries.js"
