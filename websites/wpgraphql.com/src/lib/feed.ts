import { Temporal } from "@js-temporal/polyfill"

import { gql } from "@apollo/client"
import { Feed } from "feed"
import type { Category } from "feed/lib/typings"

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL

export const FEED_QUERY = gql`
  query BlogFeedQuery {
    generalSettings {
      title
      description
      timezone
    }
    posts(where: { orderby: { field: DATE, order: DESC } }, first: 10) {
      nodes {
        title
        uri
        excerpt
        content
        dateGmt
        modifiedGmt
        author {
          node {
            name
            url
          }
        }
        categories {
          nodes {
            name
            slug
            uri
          }
        }
      }
      pageInfo {
        endCursor
        startCursor
        hasPreviousPage
        hasNextPage
      }
    }
    last_modified: posts(
      where: { orderby: { field: MODIFIED, order: DESC } }
      first: 1
    ) {
      nodes {
        modifiedGmt
      }
    }
  }
`

export function createFeed({ feed_data, last_modified }) {
  const feed = new Feed({
    title: `${feed_data.generalSettings.title} Blog`,
    description: feed_data.generalSettings.description,
    id: `${SITE_URL}/blog/`,
    link: `${SITE_URL}/blog/`,
    language: "en",
    image: `${SITE_URL}/logo-wpgraphql.png`,
    favicon: `${SITE_URL}/favicon.ico`,
    copyright: Temporal.Now.plainDateISO(
      feed_data.generalSettings.timezone
    ).year.toString(),
    updated: new Date(last_modified.toString()),
    feedLinks: {
      json: `${SITE_URL}/api/feeds/feed.json`,
      atom: `${SITE_URL}/api/feeds/feed.atom`,
      rss: `${SITE_URL}/api/feeds/rss.xml`,
    },
  })

  feed_data?.posts?.nodes?.forEach((post) => {
    const author = post.author.node
    const categories = post.categories.nodes

    feed.addItem({
      id: post.id,
      title: post.title,
      link: `${SITE_URL}${post.uri}`,
      description: post.excerpt,
      content: post.content,
      date: new Date(post.modifiedGmt),
      published: new Date(post.dateGmt),
      author: [
        {
          name: author.name,
          link: author.url,
        },
      ],
      category: categories.map((category): Category => {
        const link = `${SITE_URL}${category.uri}`
        return {
          term: category.slug,
          scheme: link,
          domain: link,
          name: category.name,
        }
      }),
    })
  })

  return feed
}
