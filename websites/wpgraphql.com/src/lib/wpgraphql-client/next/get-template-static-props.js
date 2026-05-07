import { resolveTemplate } from "../core/resolve-template.js"

const DEFAULT_REVALIDATE = 5

function uriFromCtx(ctx) {
  const node = ctx?.params?.wordpressNode
  if (!node || (Array.isArray(node) && node.length === 0)) return "/"
  const segments = Array.isArray(node) ? node : [node]
  return "/" + segments.join("/") + "/"
}

/**
 * Next.js getStaticProps adapter. Wraps resolveTemplate() and shapes the
 * result into Next's { props, revalidate } / { notFound, revalidate } form.
 *
 * @param {import('next').GetStaticPropsContext} ctx
 * @param {{ revalidate?: number }} [opts]
 */
export async function getTemplateStaticProps(ctx, opts = {}) {
  const revalidate = opts.revalidate ?? DEFAULT_REVALIDATE
  const uri = uriFromCtx(ctx)

  const result = await resolveTemplate({ uri, params: ctx?.params ?? {} })

  if (result.notFound) {
    return { notFound: true, revalidate }
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
