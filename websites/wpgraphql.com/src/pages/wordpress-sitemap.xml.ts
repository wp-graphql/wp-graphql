import { GetServerSideProps } from "next"
import { getServerSideSitemapLegacy } from "next-sitemap"

import { request } from "lib/wpgraphql-client"

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL

const SITEMAP_QUERY = /* GraphQL */ `
  query SitemapQuery($after: String) {
    contentNodes(
      where: {
        contentTypes: [
          CODE_SNIPPETS
          POST
          PAGE
          CODE_SNIPPETS
          EXTENSTION_PLUGINS
          FILTERS
          FUNCTIONS
        ]
      }
      first: 50
      after: $after
    ) {
      pageInfo {
        hasNextPage
        endCursor
      }
      nodes {
        uri
        modifiedGmt
      }
    }
  }
`

async function getAllWPContent(
  after: string | null = null,
  acc: any[] = []
): Promise<any[]> {
  const result = await request({
    query: SITEMAP_QUERY,
    variables: { after },
  })
  const data = (result as any)?.data ?? {}
  acc = [...acc, ...(data.contentNodes?.nodes ?? [])]

  if (data.contentNodes?.pageInfo?.hasNextPage) {
    acc = await getAllWPContent(data.contentNodes.pageInfo.endCursor, acc)
  }

  return acc
}

export default function WPSitemap() {}

export const getServerSideProps: GetServerSideProps = async (ctx) => {
  const nodes = await getAllWPContent()

  const allRoutes = nodes.reduce((acc: any[], node: any) => {
    if (!node.uri) return acc

    // Sitemap <loc> values must be absolute URLs; the WP-relative uri broke
    // every consumer that honors the spec (notably the Algolia crawler,
    // which failed on each entry and blocked its crawls).
    //
    // WordPress URIs carry a trailing slash, but the site's canonical URLs
    // don't (Next redirects the slash variants), so the slash is stripped to
    // keep crawlers from bouncing through a redirect per entry.
    const uri = node.uri === "/" ? node.uri : node.uri.replace(/\/+$/, "")
    const lastmod = node.modifiedGmt ? new Date(node.modifiedGmt) : null
    acc.push({
      loc: `${SITE_URL}${uri}`,
      ...(lastmod && !Number.isNaN(lastmod.getTime())
        ? { lastmod: lastmod.toISOString() }
        : {}),
    })
    return acc
  }, [])

  return await getServerSideSitemapLegacy(ctx, allRoutes)
}
