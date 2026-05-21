import { describe, it, beforeEach } from "node:test"
import assert from "node:assert/strict"
import { setFetch } from "../core/client.js"
import { configure } from "../core/templates.js"
import { resolveTemplate } from "../core/resolve-template.js"

const ENDPOINT = "https://example.com/graphql"

function makeFetchSpy(responder) {
  const calls = []
  const fn = async (url, init) => {
    calls.push({ url, init })
    const body = await responder({ url, init, callIndex: calls.length - 1 })
    return { ok: true, status: 200, json: async () => body }
  }
  return { fn, calls }
}

beforeEach(() => {
  process.env.NEXT_PUBLIC_WPGRAPHQL_URL = ENDPOINT
})

describe("resolveTemplate", () => {
  it("returns { template, data, layoutData, uri, seed } on a hit", async () => {
    const responses = [
      {
        data: {
          node: {
            __typename: "ContentType",
            id: "ct1",
            uri: "/blog/",
            name: "post",
            graphqlSingleName: "post",
          },
          generalSettings: { title: "Site", description: "" },
        },
      },
      { data: { posts: { nodes: [{ id: "p1", title: "Hello" }] } } },
      { data: { menu: { menuItems: { nodes: [] } } } },
    ]
    const { fn } = makeFetchSpy(({ callIndex }) => responses[callIndex])
    setFetch(fn)

    const ArchivePost = () => null
    ArchivePost.queries = {
      posts: { query: `query Posts { posts { nodes { id } } }`, variables: () => ({}) },
    }
    const Layout = {
      queries: {
        navMenu: { query: `query Nav { menu { menuItems { nodes { id } } } }`, variables: () => ({}) },
      },
    }
    configure({ templates: { "archive-post": ArchivePost, index: () => null }, Layout })

    const out = await resolveTemplate({ uri: "/blog/", params: { wordpressNode: ["blog"] } })

    assert.equal(out.template, "archive-post")
    assert.equal(out.uri, "/blog/")
    assert.equal(out.seed.typename, "ContentType")
    assert.equal(out.data.posts.nodes[0].title, "Hello")
    assert.deepEqual(out.layoutData.menu.menuItems.nodes, [])
  })

  it("returns { notFound: true, ... } when seed.node is null and not the front page", async () => {
    const { fn } = makeFetchSpy(() => ({ data: { node: null, generalSettings: null } }))
    setFetch(fn)

    configure({ templates: { index: () => null }, Layout: { queries: {} } })

    const out = await resolveTemplate({ uri: "/missing/", params: {} })
    assert.equal(out.notFound, true)
    assert.equal(out.uri, "/missing/")
  })

  it("does not return notFound for the front page even when nodeByUri is null", async () => {
    const responses = [
      { data: { node: null, generalSettings: null } },
      // template + layout calls won't fire because front-page.queries is empty
    ]
    const { fn } = makeFetchSpy(({ callIndex }) => responses[callIndex] ?? { data: {} })
    setFetch(fn)

    const FrontPage = () => null
    FrontPage.queries = {}
    configure({
      templates: { "front-page": FrontPage, index: () => null },
      Layout: { queries: {} },
    })

    const out = await resolveTemplate({ uri: "/", params: {} })
    assert.equal(out.template, "front-page")
    assert.equal(out.uri, "/")
  })

  it("throws when uri is not a string", async () => {
    setFetch(async () => ({ ok: true, json: async () => ({}) }))
    await assert.rejects(() => resolveTemplate({}), /uri must be a string/)
  })
})
