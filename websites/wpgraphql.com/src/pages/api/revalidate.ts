import { timingSafeEqual } from "crypto"

import { StatusCodes, getReasonPhrase } from "http-status-codes"

import type { NextApiRequest, NextApiResponse } from "next"

const SECRET_HEADER = "x-wpgraphql-revalidate-secret"
const MAX_PATHS_PER_REQUEST = 100

type RevalidateBody = { paths?: unknown }

type RevalidateResponse =
  | { revalidated: string[]; failed: { path: string; error: string }[] }
  | { error: string }

function secretsMatch(provided: string, expected: string): boolean {
  if (provided.length !== expected.length) return false
  return timingSafeEqual(Buffer.from(provided), Buffer.from(expected))
}

function isValidPath(value: unknown): value is string {
  return typeof value === "string" && value.length > 0 && value.startsWith("/")
}

export default async function HandleRevalidate(
  req: NextApiRequest,
  res: NextApiResponse<RevalidateResponse>
) {
  if (req.method !== "POST") {
    res.setHeader("Allow", "POST")
    return res
      .status(StatusCodes.METHOD_NOT_ALLOWED)
      .json({ error: getReasonPhrase(StatusCodes.METHOD_NOT_ALLOWED) })
  }

  const expected = process.env.WPGRAPHQL_REVALIDATE_SECRET
  if (!expected) {
    console.error("[revalidate] WPGRAPHQL_REVALIDATE_SECRET is not set; refusing all requests")
    return res
      .status(StatusCodes.INTERNAL_SERVER_ERROR)
      .json({ error: "revalidation not configured" })
  }

  const provided = req.headers[SECRET_HEADER]
  if (typeof provided !== "string" || !secretsMatch(provided, expected)) {
    return res
      .status(StatusCodes.UNAUTHORIZED)
      .json({ error: getReasonPhrase(StatusCodes.UNAUTHORIZED) })
  }

  const body = (req.body ?? {}) as RevalidateBody
  if (!Array.isArray(body.paths) || body.paths.length === 0) {
    return res
      .status(StatusCodes.BAD_REQUEST)
      .json({ error: "expected non-empty `paths` array" })
  }
  if (body.paths.length > MAX_PATHS_PER_REQUEST) {
    return res
      .status(StatusCodes.BAD_REQUEST)
      .json({ error: `too many paths (max ${MAX_PATHS_PER_REQUEST})` })
  }
  if (!body.paths.every(isValidPath)) {
    return res
      .status(StatusCodes.BAD_REQUEST)
      .json({ error: "every entry in `paths` must be a string starting with /" })
  }

  const paths = Array.from(new Set(body.paths as string[]))

  const revalidated: string[] = []
  const failed: { path: string; error: string }[] = []

  for (const path of paths) {
    try {
      await res.revalidate(path)
      revalidated.push(path)
    } catch (err) {
      const message = err instanceof Error ? err.message : String(err)
      console.error(`[revalidate] failed for ${path}:`, message)
      failed.push({ path, error: message })
    }
  }

  console.log(
    `[revalidate] revalidated=${revalidated.length} failed=${failed.length}`
  )

  return res.status(StatusCodes.OK).json({ revalidated, failed })
}
