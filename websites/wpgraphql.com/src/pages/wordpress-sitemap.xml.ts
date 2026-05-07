import { GetServerSideProps } from "next"
import { getServerSideSitemap } from "next-sitemap"

import { request } from "lib/wpgraphql-client"

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

async function getAllWPContent(after: string | null = null, acc: any[] = []): Promise<any[]> {
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
    acc.push({
      loc: node.uri,
      lastmod: new Date(node.modifiedGmt).toISOString(),
    })
    return acc
  }, [])

  return await getServerSideSitemap(ctx, allRoutes)
}
