import { request } from "./client.js"
import { resolveTemplateName } from "./hierarchy.js"
import { SEED_QUERY, normalizeSeed } from "./seed-query.js"
import { getRegistry } from "./templates.js"

const DEFAULT_REVALIDATE = 5

function uriFromCtx(ctx) {
  const node = ctx?.params?.wordpressNode
  if (!node || (Array.isArray(node) && node.length === 0)) return "/"
  const segments = Array.isArray(node) ? node : [node]
  return "/" + segments.join("/") + "/"
}

function unwrapData(result) {
  const data =
    result && Object.prototype.hasOwnProperty.call(result, "data")
      ? result.data
      : result
  if (!data || typeof data !== "object") return data ?? null
  const keys = Object.keys(data)
  if (keys.length === 1) return data[keys[0]]
  return data
}

async function runQueries(entries, reqCtx) {
  const filtered = entries.filter(([, e]) => !e.skip?.(reqCtx))
  const promises = filtered.map(([, e]) =>
    request({ query: e.query, variables: e.variables?.(reqCtx) ?? {} })
  )
  const results = await Promise.all(promises)
  const out = {}
  filtered.forEach(([key], i) => {
    out[key] = unwrapData(results[i])
  })
  return out
}

export async function getTemplateStaticProps(ctx, opts = {}) {
  const revalidate = opts.revalidate ?? DEFAULT_REVALIDATE
  const uri = uriFromCtx(ctx)

  const seedResponse = await request({
    query: SEED_QUERY,
    variables: { uri },
    operationName: "NextWpGraphQLSeed",
  })
  const seed = normalizeSeed(seedResponse, uri)

  if (!seed.node && !seed.isFrontPage) {
    return { notFound: true, revalidate }
  }

  const { templates, Layout } = getRegistry()

  const templateName = resolveTemplateName(seed, templates)
  const Template = templates[templateName]
  const reqCtx = { uri, seed, params: ctx?.params ?? {} }

  const templateEntries = Object.entries(Template.queries ?? {})
  const layoutEntries = Object.entries(Layout?.queries ?? {})

  const [data, layoutData] = await Promise.all([
    runQueries(templateEntries, reqCtx),
    runQueries(layoutEntries, reqCtx),
  ])

  return {
    props: {
      template: templateName,
      data,
      layoutData,
      uri,
      seed,
    },
    revalidate,
  }
}
