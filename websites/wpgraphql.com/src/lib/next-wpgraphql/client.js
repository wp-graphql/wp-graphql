import { parse } from "graphql"
import { getGraphqlEndpoint } from "./endpoint.js"
import { sha256 } from "./hash.js"
import { printQuery, getOperation } from "./print.js"

const URL_LENGTH_GUARD = 6000

let _fetch = globalThis.fetch?.bind(globalThis)

export function setFetch(fn) {
  _fetch = fn
}

function asDocument(query) {
  if (typeof query === "string") return parse(query)
  if (query && query.kind === "Document") return query
  throw new TypeError("request: query must be a string or DocumentNode")
}

function isPersistedQueryNotFound(errors) {
  if (!Array.isArray(errors)) return false
  return errors.some((e) => {
    const msg = typeof e?.message === "string" ? e.message : ""
    return msg.includes("PersistedQueryNotFound")
  })
}

function buildGetUrl(endpoint, { queryId, variables, operationName }) {
  const params = new URLSearchParams()
  params.set("queryId", queryId)
  if (variables && Object.keys(variables).length > 0) {
    params.set("variables", JSON.stringify(variables))
  }
  if (operationName) params.set("operationName", operationName)
  return `${endpoint}?${params.toString()}`
}

async function postJson(endpoint, body) {
  if (!_fetch) {
    throw new Error("next-wpgraphql/client: no fetch implementation available")
  }
  const res = await _fetch(endpoint, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  })
  return res.json()
}

async function getJson(url) {
  if (!_fetch) {
    throw new Error("next-wpgraphql/client: no fetch implementation available")
  }
  const res = await _fetch(url, {
    method: "GET",
    headers: { Accept: "application/json" },
  })
  return res.json()
}

/**
 * @param {object} args
 * @param {string|object} args.query
 * @param {Record<string, unknown>} [args.variables]
 * @param {string} [args.operationName]
 * @param {string} [args.endpoint]
 */
export async function request({ query, variables = {}, operationName, endpoint } = {}) {
  if (!query) throw new TypeError("request: query is required")

  const document = asDocument(query)
  const operation = getOperation(document)
  const printed = printQuery(document)
  const url = endpoint || getGraphqlEndpoint()

  if (operation === "mutation") {
    return postJson(url, { query: printed, variables, operationName })
  }

  const queryId = await sha256(printed)
  const getUrl = buildGetUrl(url, { queryId, variables, operationName })

  if (getUrl.length > URL_LENGTH_GUARD) {
    return postJson(url, { queryId, query: printed, variables, operationName })
  }

  const result = await getJson(getUrl)

  if (result && isPersistedQueryNotFound(result.errors)) {
    return postJson(url, { queryId, query: printed, variables, operationName })
  }

  return result
}
