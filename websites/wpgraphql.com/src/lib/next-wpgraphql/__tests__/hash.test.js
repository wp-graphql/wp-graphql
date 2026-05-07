import { describe, it } from "node:test"
import assert from "node:assert/strict"
import { sha256 } from "../hash.js"

describe("sha256", () => {
  it("produces the standard SHA-256 hex digest for the empty string", async () => {
    assert.equal(
      await sha256(""),
      "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
    )
  })

  it("produces the standard SHA-256 hex digest for 'abc'", async () => {
    assert.equal(
      await sha256("abc"),
      "ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad"
    )
  })

  it("is deterministic", async () => {
    const a = await sha256("query Foo { foo }")
    const b = await sha256("query Foo { foo }")
    assert.equal(a, b)
  })

  it("produces different digests for different inputs", async () => {
    const a = await sha256("query A { foo }")
    const b = await sha256("query B { foo }")
    assert.notEqual(a, b)
  })

  it("throws on non-string input", async () => {
    await assert.rejects(() => sha256(123), /input must be a string/)
  })
})
