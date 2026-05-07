import { describe, it } from "node:test"
import assert from "node:assert/strict"

function shouldUseNextWpGraphQL(prefixes, ctx) {
  if (!prefixes || prefixes.length === 0) return false
  const node = ctx?.params?.wordpressNode ?? []
  const segments = Array.isArray(node) ? node : [node]
  const path = "/" + segments.join("/")
  return prefixes.some((prefix) => path === prefix || path.startsWith(prefix + "/"))
}

describe("shouldUseNextWpGraphQL", () => {
  it("returns false when prefixes is empty", () => {
    assert.equal(shouldUseNextWpGraphQL([], { params: { wordpressNode: ["blog"] } }), false)
  })

  it("matches when path equals prefix exactly", () => {
    assert.equal(
      shouldUseNextWpGraphQL(["/blog"], { params: { wordpressNode: ["blog"] } }),
      true
    )
  })

  it("matches when path starts with prefix + '/'", () => {
    assert.equal(
      shouldUseNextWpGraphQL(["/blog"], {
        params: { wordpressNode: ["blog", "page", "2"] },
      }),
      true
    )
  })

  it("does not match unrelated paths", () => {
    assert.equal(
      shouldUseNextWpGraphQL(["/blog"], { params: { wordpressNode: ["docs"] } }),
      false
    )
  })

  it("does not match prefix-as-substring (no '/')", () => {
    assert.equal(
      shouldUseNextWpGraphQL(["/blog"], { params: { wordpressNode: ["blogger"] } }),
      false
    )
  })

  it("supports multiple prefixes", () => {
    const prefixes = ["/blog", "/news"]
    assert.equal(shouldUseNextWpGraphQL(prefixes, { params: { wordpressNode: ["news", "x"] } }), true)
    assert.equal(shouldUseNextWpGraphQL(prefixes, { params: { wordpressNode: ["docs"] } }), false)
  })

  it("handles missing/empty wordpressNode (treated as '/')", () => {
    const prefixes = ["/blog"]
    assert.equal(shouldUseNextWpGraphQL(prefixes, { params: {} }), false)
    assert.equal(shouldUseNextWpGraphQL(prefixes, {}), false)
  })
})
