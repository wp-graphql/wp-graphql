import { describe, it } from "node:test"
import assert from "node:assert/strict"
import { configure, getRegistry } from "../templates.js"

describe("configure / getRegistry", () => {
  it("stores templates and Layout passed to configure()", () => {
    const A = () => null
    const Layout = { queries: { foo: {} } }
    configure({ templates: { a: A }, Layout })
    const r = getRegistry()
    assert.deepEqual(Object.keys(r.templates), ["a"])
    assert.equal(r.Layout, Layout)
  })

  it("defaults Layout to an empty queries object when omitted", () => {
    configure({ templates: {} })
    const r = getRegistry()
    assert.deepEqual(r.Layout, { queries: {} })
  })

  it("defaults templates to an empty object when omitted", () => {
    configure({})
    const r = getRegistry()
    assert.deepEqual(r.templates, {})
  })

  it("can be re-called to swap the registry (useful for tests)", () => {
    const A = () => null
    const B = () => null
    configure({ templates: { a: A } })
    configure({ templates: { b: B } })
    const r = getRegistry()
    assert.deepEqual(Object.keys(r.templates), ["b"])
  })
})
