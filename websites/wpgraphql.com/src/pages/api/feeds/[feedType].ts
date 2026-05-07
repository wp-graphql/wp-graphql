import { createHash } from "crypto"
import { Temporal } from "@js-temporal/polyfill"

import { request } from "lib/wpgraphql-client"
import { StatusCodes, getReasonPhrase } from "http-status-codes"

import { FEED_QUERY, createFeed } from "../../../lib/feed"

import type { NextApiRequest, NextApiResponse } from "next"

type ResponseData = {
  content_type: string
  body: string
}

export default async function HandleFeeds(
  req: NextApiRequest,
  res: NextApiResponse
) {
  console.log(`[feeds] handler invoked: feedType=${String(req.query.feedType)} url=${req.url}`)
  try {
    // Get needed Reqest info
    const if_modified_since: string = req.headers["if-modified-since"]
    const if_none_match = req.headers["if-none-match"]
    const { feedType } = req.query

    //Fetch content from WP
    console.log("[feeds] running BlogFeedQuery against", process.env.NEXT_PUBLIC_WPGRAPHQL_URL || process.env.WPGRAPHQL_URL)
    const result = await request({ query: FEED_QUERY })
    console.log("[feeds] result keys:", Object.keys((result as any) ?? {}))
    if ((result as any)?.errors?.length) {
      console.error("[feeds] GraphQL errors:", JSON.stringify((result as any).errors, null, 2))
      throw new Error("GraphQL errors fetching feed data")
    }
    const feed_data = (result as any)?.data ?? {}
    console.log(
      "[feeds] data shape:",
      Object.keys(feed_data),
      "posts.nodes:", feed_data?.posts?.nodes?.length,
      "last_modified.nodes:", feed_data?.last_modified?.nodes?.length
    )

    if (!feed_data?.last_modified?.nodes?.[0]?.modifiedGmt) {
      console.error("[feeds] feed query returned no posts; result:", JSON.stringify(result, null, 2))
      throw {
        status: StatusCodes.NOT_FOUND,
        body: getReasonPhrase(StatusCodes.NOT_FOUND),
      }
    }

    const last_modified = Temporal.PlainDateTime.from(
      feed_data.last_modified.nodes[0].modifiedGmt,
      {
        overflow: "constrain",
      }
    )

    //Create feed
    const feed = createFeed({ feed_data, last_modified })

    // Genrate Response Body and Content-Type
    let resp: ResponseData

    switch (feedType) {
      case "feed.json":
        resp = {
          content_type: "application/feed+json",
          body: feed.json1(),
        }
        break
      case "feed.atom":
        resp = {
          content_type: "application/atom+xml",
          body: feed.atom1(),
        }
        break
      case "rss.xml":
        resp = {
          content_type: "application/rss+xml",
          body: feed.rss2(),
        }
        break
      default:
        throw {
          status: StatusCodes.NOT_FOUND,
          body: getReasonPhrase(StatusCodes.NOT_FOUND),
        }
    }

    // Spec specifies hash bieng in quotes
    const etag_for_body = `"${createHash("md5")
      .update(resp.body)
      .digest("hex")}"`

    res.setHeader("Vary", "if-modified-since, if-none-match")
    res.setHeader(
      "Cache-Control",
      "max-age=0, must-revalidate, stale-if-error=86400"
    )
    res.setHeader("Content-Type", resp.content_type)
    res.setHeader("ETag", etag_for_body)
    res.setHeader("Last-Modified", last_modified.toString())

    if (
      // Checks if the `if_none_match` header matches current respones' etag
      if_none_match === etag_for_body ||
      // Checks `if_modified_since` is after `last_modified`
      (if_modified_since &&
        Temporal.PlainDateTime.compare(last_modified, if_modified_since) < 0)
    ) {
      res.status(StatusCodes.NOT_MODIFIED)
      res.end()
    } else {
      res.status(StatusCodes.OK)
      res.end(resp.body)
    }
  } catch (e) {
    if (e.status && e.body) {
      res.status(e.status)
      res.send(e.body)
    } else {
      console.error("[feeds] handler error:", e)
      res.status(StatusCodes.INTERNAL_SERVER_ERROR)
      res.send(getReasonPhrase(StatusCodes.INTERNAL_SERVER_ERROR))
    }
  }
}
