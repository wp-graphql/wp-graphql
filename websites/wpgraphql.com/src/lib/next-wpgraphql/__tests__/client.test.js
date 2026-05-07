import { describe, it, beforeEach } from "node:test"
import assert from "node:assert/strict"
import { request, setFetch } from "../client.js"

const ENDPOINT = "https://example.com/graphql"

function makeFetchSpy(responses) {
  const calls = []
  const queue = Array.isArray(responses) ? [...responses] : [responses]
  const fn = async (url, init) => {
    calls.push({ url, init })
    const next = queue.shift() ?? queue[queue.length - 1]
    return {
      ok: true,
      status: 200,
      json: async () => (typeof next === "function" ? next({ url, init }) : next),
    }
  }
  return { fn, calls }
}

beforeEach(() => {
  process.env.NEXT_PUBLIC_WPGRAPHQL_URL = ENDPOINT
})

describe("request — query GET path", () => {
  it("sends a GET with queryId and JSON-encoded variables", async () => {
    const { fn, calls } = makeFetchSpy({ data: { foo: "bar" } })
    setFetch(fn)

    const result = await request({
      query: "query Foo($x: Int) { foo(x: $x) }",
      variables: { x: 1 },
    })

    assert.equal(calls.length, 1)
    assert.equal(calls[0].init.method, "GET")
    const url = new URL(calls[0].url)
    assert.equal(url.origin + url.pathname, ENDPOINT)
    assert.match(url.searchParams.get("queryId"), /^[a-f0-9]{64}$/)
    assert.equal(url.searchParams.get("variables"), '{"x":1}')
    assert.deepEqual(result, { data: { foo: "bar" } })
  })

  it("omits the variables param when variables is empty", async () => {
    const { fn, calls } = makeFetchSpy({ data: {} })
    setFetch(fn)
    await request({ query: "query Foo { foo }" })
    const url = new URL(calls[0].url)
    assert.equal(url.searchParams.has("variables"), false)
  })

  it("includes operationName when provided", async () => {
    const { fn, calls } = makeFetchSpy({ data: {} })
    setFetch(fn)
    await request({ query: "query Foo { foo }", operationName: "Foo" })
    const url = new URL(calls[0].url)
    assert.equal(url.searchParams.get("operationName"), "Foo")
  })
})

describe("request — APQ fallback", () => {
  it("retries as POST with query+queryId on PersistedQueryNotFound", async () => {
    const { fn, calls } = makeFetchSpy([
      { errors: [{ message: "PersistedQueryNotFound" }] },
      { data: { foo: "bar" } },
    ])
    setFetch(fn)

    const result = await request({ query: "query Foo { foo }" })

    assert.equal(calls.length, 2)
    assert.equal(calls[0].init.method, "GET")
    assert.equal(calls[1].init.method, "POST")
    const body = JSON.parse(calls[1].init.body)
    assert.match(body.queryId, /^[a-f0-9]{64}$/)
    assert.match(body.query, /query Foo/)
    assert.deepEqual(result, { data: { foo: "bar" } })
  })

  it("does not retry when errors do not include PersistedQueryNotFound", async () => {
    const { fn, calls } = makeFetchSpy([
      { errors: [{ message: "something else" }] },
    ])
    setFetch(fn)

    const result = await request({ query: "query Foo { foo }" })
    assert.equal(calls.length, 1)
    assert.deepEqual(result.errors, [{ message: "something else" }])
  })
})

describe("request — mutations", () => {
  it("always POSTs mutations with the full query", async () => {
    const { fn, calls } = makeFetchSpy({ data: { doIt: true } })
    setFetch(fn)

    await request({ query: "mutation DoIt { doIt }" })

    assert.equal(calls.length, 1)
    assert.equal(calls[0].init.method, "POST")
    const body = JSON.parse(calls[0].init.body)
    assert.match(body.query, /mutation DoIt/)
    assert.equal(body.queryId, undefined)
  })
})

describe("request — URL length guard", () => {
  it("falls back to POST when the GET URL would exceed 6000 chars", async () => {
    const { fn, calls } = makeFetchSpy({ data: {} })
    setFetch(fn)

    const big = "x".repeat(7000)
    await request({
      query: "query Foo($s: String) { foo(s: $s) }",
      variables: { s: big },
    })

    assert.equal(calls.length, 1)
    assert.equal(calls[0].init.method, "POST")
    const body = JSON.parse(calls[0].init.body)
    assert.match(body.queryId, /^[a-f0-9]{64}$/)
    assert.equal(body.variables.s, big)
  })
})

describe("request — input validation", () => {
  it("throws when query is missing", async () => {
    setFetch(async () => ({ ok: true, json: async () => ({}) }))
    await assert.rejects(() => request({}), /query is required/)
  })

  it("throws when query is not a string or DocumentNode", async () => {
    setFetch(async () => ({ ok: true, json: async () => ({}) }))
    await assert.rejects(() => request({ query: 42 }), /string or DocumentNode/)
  })
})
