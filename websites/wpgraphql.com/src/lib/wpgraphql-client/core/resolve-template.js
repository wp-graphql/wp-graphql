import { request } from "./client.js"
import { resolveTemplateName } from "./hierarchy.js"
import { SEED_QUERY, normalizeSeed } from "./seed-query.js"
import { getRegistry } from "./templates.js"
import { runQueries } from "./run-queries.js"

/**
 * Framework-agnostic orchestrator: given a URI, run the seed query, resolve a
 * template via the hierarchy, then run all template + layout queries in
 * parallel. Returns the rendered shape without any framework-specific wrapping.
 *
 * @param {object} args
 * @param {string} args.uri              — the URI to resolve (e.g. "/blog/")
 * @param {object} [args.params]         — extra params passed to query variables
 * @returns {Promise<
 *   | { notFound: true, uri: string, seed: object }
 *   | { template: string, data: object, layoutData: object, uri: string, seed: object }
 * >}
 */
export async function resolveTemplate({ uri, params = {} } = {}) {
  if (typeof uri !== "string") {
    throw new TypeError("resolveTemplate: uri must be a string")
  }

  const seedResponse = await request({
    query: SEED_QUERY,
    variables: { uri },
    operationName: "WpGraphQLClientSeed",
  })
  const seed = normalizeSeed(seedResponse, uri)

  if (!seed.node && !seed.isFrontPage) {
    return { notFound: true, uri, seed }
  }

  const { templates, Layout } = getRegistry()
  const templateName = resolveTemplateName(seed, templates)
  const Template = templates[templateName]
  const reqCtx = { uri, seed, params }

  const [data, layoutData] = await Promise.all([
    runQueries(Object.entries(Template.queries ?? {}), reqCtx),
    runQueries(Object.entries(Layout?.queries ?? {}), reqCtx),
  ])

  return { template: templateName, data, layoutData, uri, seed }
}
