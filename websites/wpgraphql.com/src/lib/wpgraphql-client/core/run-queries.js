import { request } from "./client.js"

function dataFrom(result) {
  return result && Object.prototype.hasOwnProperty.call(result, "data")
    ? (result.data ?? null)
    : (result ?? null)
}

export async function runQueries(entries, reqCtx) {
  const filtered = entries.filter(([, e]) => !e.skip?.(reqCtx))
  const promises = filtered.map(([, e]) =>
    request({ query: e.query, variables: e.variables?.(reqCtx) ?? {} })
  )
  const results = await Promise.all(promises)
  const out = {}
  for (const result of results) {
    const data = dataFrom(result)
    if (data && typeof data === "object") Object.assign(out, data)
  }
  return out
}
