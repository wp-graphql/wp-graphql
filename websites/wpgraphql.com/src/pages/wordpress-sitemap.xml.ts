import { GetServerSideProps } from "next"
import { getServerSideSitemap } from "next-sitemap"

import { gql } from "@apollo/client"
import { getApolloClient } from "@faustwp/core/dist/mjs/client"

const client = getApolloClient()

const SITEMAP_QUERY = gql`
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

async function getAllWPContent(after = null, acc = []) {
  const { data } = await client.query({
    query: SITEMAP_QUERY,
    variables: {
      after,
    },
  })

  console.log(data.contentNodes.nodes)
  acc = [...acc, ...data.contentNodes.nodes]

  if (data.contentNodes.pageInfo.hasNextPage) {
    acc = await getAllWPContent(data.contentNodes.pageInfo.endCursor, acc)
  }

  return acc
}

// Sitemap component
export default function WPSitemap() {}

// collect all the post
export const getServerSideProps: GetServerSideProps = async (ctx) => {
  const nodes = await getAllWPContent()

  const allRoutes = nodes.reduce((acc, node) => {
    if (!node.uri) {
      return acc
    }

    acc.push({
      loc: node.uri,
      lastmod: new Date(node.modifiedGmt).toISOString(),
    })

    return acc
  }, [])

  return await getServerSideSitemap(ctx, allRoutes)
}
