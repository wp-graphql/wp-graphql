import { describe, it, beforeEach, afterEach } from "node:test"
import assert from "node:assert/strict"
import { getGraphqlEndpoint } from "../core/endpoint.js"

const ENV_KEYS = ["NEXT_PUBLIC_WPGRAPHQL_URL", "WPGRAPHQL_URL"]

function clearEnv() {
  for (const key of ENV_KEYS) delete process.env[key]
}

describe("getGraphqlEndpoint", () => {
  let original
  beforeEach(() => {
    original = Object.fromEntries(ENV_KEYS.map((k) => [k, process.env[k]]))
    clearEnv()
  })
  afterEach(() => {
    clearEnv()
    for (const [k, v] of Object.entries(original)) {
      if (v !== undefined) process.env[k] = v
    }
  })

  it("returns NEXT_PUBLIC_WPGRAPHQL_URL when set", () => {
    process.env.NEXT_PUBLIC_WPGRAPHQL_URL = "https://example.com/graphql"
    assert.equal(getGraphqlEndpoint(), "https://example.com/graphql")
  })

  it("falls back to WPGRAPHQL_URL when NEXT_PUBLIC_WPGRAPHQL_URL is unset", () => {
    process.env.WPGRAPHQL_URL = "https://server-only.example.com/graphql"
    assert.equal(getGraphqlEndpoint(), "https://server-only.example.com/graphql")
  })

  it("prefers NEXT_PUBLIC_WPGRAPHQL_URL over WPGRAPHQL_URL", () => {
    process.env.NEXT_PUBLIC_WPGRAPHQL_URL = "https://public.example.com/graphql"
    process.env.WPGRAPHQL_URL = "https://private.example.com/graphql"
    assert.equal(getGraphqlEndpoint(), "https://public.example.com/graphql")
  })

  it("trims trailing slashes", () => {
    process.env.NEXT_PUBLIC_WPGRAPHQL_URL = "https://example.com/graphql///"
    assert.equal(getGraphqlEndpoint(), "https://example.com/graphql")
  })

  it("throws when neither env var is set", () => {
    assert.throws(() => getGraphqlEndpoint(), /must be set/)
  })

  it("throws when env var is empty string", () => {
    process.env.NEXT_PUBLIC_WPGRAPHQL_URL = ""
    process.env.WPGRAPHQL_URL = ""
    assert.throws(() => getGraphqlEndpoint(), /must be set/)
  })
})
