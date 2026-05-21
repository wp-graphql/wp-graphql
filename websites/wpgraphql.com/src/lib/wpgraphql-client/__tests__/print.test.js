import { describe, it } from "node:test"
import assert from "node:assert/strict"
import { parse } from "graphql"
import { printQuery, getOperation } from "../core/print.js"

describe("printQuery", () => {
  it("returns the same string for an already-printed query", () => {
    const src = "query Foo { foo }"
    assert.equal(printQuery(src), src)
  })

  it("prints a DocumentNode to a stable string", () => {
    const doc = parse(`
      query Foo($x: Int) {
        foo(x: $x) { id }
      }
    `)
    const out = printQuery(doc)
    assert.match(out, /query Foo\(\$x: Int\)/)
    assert.match(out, /foo\(x: \$x\)/)
  })

  it("normalizes whitespace differences via parse+print round-trip", () => {
    const a = parse("query A { foo  }")
    const b = parse("query A {\n  foo\n}")
    assert.equal(printQuery(a), printQuery(b))
  })

  it("throws on non-document non-string input", () => {
    assert.throws(() => printQuery({ random: "object" }), /DocumentNode or string/)
  })
})

describe("getOperation", () => {
  it("returns 'query' for a query", () => {
    assert.equal(getOperation(parse("query Q { x }")), "query")
  })

  it("returns 'mutation' for a mutation", () => {
    assert.equal(getOperation(parse("mutation M { doIt }")), "mutation")
  })

  it("returns 'subscription' for a subscription", () => {
    assert.equal(getOperation(parse("subscription S { e }")), "subscription")
  })

  it("returns the first operation if multiple are present", () => {
    const doc = parse("query A { x } mutation B { y }")
    assert.equal(getOperation(doc), "query")
  })

  it("throws on non-document input", () => {
    assert.throws(() => getOperation("query A { x }"), /parsed DocumentNode/)
  })
})
