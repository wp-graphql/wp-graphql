import { resolveTemplate } from "../core/resolve-template.js"

const DEFAULT_REVALIDATE = 5

function uriFromCtx(ctx) {
  const node = ctx?.params?.wordpressNode
  if (!node || (Array.isArray(node) && node.length === 0)) return "/"
  const segments = Array.isArray(node) ? node : [node]
  return "/" + segments.join("/") + "/"
}

function debugEnabled() {
  if (process.env.WPGRAPHQL_CLIENT_DEBUG === "1") return true
  if (process.env.WPGRAPHQL_CLIENT_DEBUG === "0") return false
  return process.env.NODE_ENV !== "production"
}

/**
 * Next.js getStaticProps adapter. Wraps resolveTemplate() and shapes the
 * result into Next's { props, revalidate } / { notFound, revalidate } form.
 *
 * Logs the resolved template + URI in development (or when
 * WPGRAPHQL_CLIENT_DEBUG=1 explicitly). Set WPGRAPHQL_CLIENT_DEBUG=0 to
 * silence even in dev.
 *
 * @param {import('next').GetStaticPropsContext} ctx
 * @param {{ revalidate?: number }} [opts]
 */
export async function getTemplateStaticProps(ctx, opts = {}) {
  const revalidate = opts.revalidate ?? DEFAULT_REVALIDATE
  const uri = uriFromCtx(ctx)
  const debug = debugEnabled()

  const result = await resolveTemplate({ uri, params: ctx?.params ?? {} })

  if (result.notFound) {
    if (debug) {
      console.log(`[wpgraphql-client] uri=${uri} notFound (seed.node was null)`)
    }
    return { notFound: true, revalidate }
  }

  if (debug) {
    const seed = result.seed ?? {}
    const dataKeys = Object.keys(result.data ?? {})
    const layoutKeys = Object.keys(result.layoutData ?? {})
    console.log(
      `[wpgraphql-client] uri=${uri} template=${result.template}` +
      ` typename=${seed.typename ?? "-"} postType=${seed.postType ?? "-"}` +
      ` slug=${seed.slug ?? "-"}` +
      ` data=[${dataKeys.join(", ") || "-"}]` +
      ` layoutData=[${layoutKeys.join(", ") || "-"}]`
    )
  }

  return {
    props: {
      template: result.template,
      data: result.data,
      layoutData: result.layoutData,
      uri: result.uri,
      seed: result.seed,
    },
    revalidate,
  }
}
