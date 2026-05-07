import { describe, it, beforeEach } from "node:test"
import assert from "node:assert/strict"
import { setFetch } from "../core/client.js"
import { configure } from "../core/templates.js"
import { getTemplateStaticProps } from "../next/get-template-static-props.js"

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

function pickOperationFromBody(call) {
  if (call.init?.method === "POST") {
    try {
      const parsed = JSON.parse(call.init.body)
      const m = parsed.query?.match(/(?:query|mutation)\s+(\w+)/)
      return m?.[1] ?? parsed.operationName ?? null
    } catch {
      return null
    }
  }
  const url = new URL(call.url)
  return url.searchParams.get("operationName")
}

beforeEach(() => {
  process.env.NEXT_PUBLIC_WPGRAPHQL_URL = ENDPOINT
})

describe("getTemplateStaticProps", () => {
  it("runs seed query, resolves a template, and returns props with data + layoutData", async () => {
    const responses = {
      WpGraphQLClientSeed: {
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
      ArchivePost_Posts: {
        data: { posts: { nodes: [{ id: "p1", title: "Hello" }] } },
      },
      Layout_NavMenu: {
        data: { menu: { menuItems: { nodes: [{ id: "m1", label: "Home" }] } } },
      },
    }
    const { fn, calls } = makeFetchSpy(({ url, init, callIndex }) => {
      const op = pickOperationFromBody({ url, init })
      const key = op ?? Object.keys(responses)[callIndex]
      return responses[key]
    })
    setFetch(fn)

    const ArchivePost = () => null
    ArchivePost.queries = {
      posts: {
        query: /* GraphQL */ `query ArchivePost_Posts { posts { nodes { id title } } }`,
        variables: () => ({}),
      },
    }
    const Layout = {
      queries: {
        navMenu: {
          query: /* GraphQL */ `query Layout_NavMenu { menu { menuItems { nodes { id label } } } }`,
          variables: () => ({}),
        },
      },
    }
    configure({
      templates: { "archive-post": ArchivePost, index: () => null },
      Layout,
    })

    const result = await getTemplateStaticProps({ params: { wordpressNode: ["blog"] } })

    assert.equal(result.props.template, "archive-post")
    assert.equal(result.props.uri, "/blog/")
    // each query's `data` fields are spread into the flat data prop
    assert.equal(result.props.data.posts.nodes[0].title, "Hello")
    assert.equal(result.props.layoutData.menu.menuItems.nodes[0].label, "Home")
    assert.equal(result.revalidate, 5)
    // 1 seed + 1 template + 1 layout = 3 calls
    assert.equal(calls.length, 3)
  })

  it("returns notFound when seed.node is null and uri is not the front page", async () => {
    const { fn } = makeFetchSpy(() => ({ data: { node: null, generalSettings: null } }))
    setFetch(fn)

    configure({
      templates: { index: () => null },
      Layout: { queries: {} },
    })

    const result = await getTemplateStaticProps({ params: { wordpressNode: ["nope"] } })
    assert.deepEqual(result, { notFound: true, revalidate: 5 })
  })

  it("uses the front-page template when uri is '/'", async () => {
    const responses = [
      {
        data: {
          node: { __typename: "Page", id: "p", slug: "home", uri: "/" },
          generalSettings: null,
        },
      },
      { data: { hello: "world" } },
    ]
    const { fn } = makeFetchSpy(({ callIndex }) => responses[callIndex])
    setFetch(fn)

    const FrontPage = () => null
    FrontPage.queries = {
      hello: { query: `query Hello { hello }`, variables: () => ({}) },
    }
    configure({
      templates: { "front-page": FrontPage, index: () => null },
      Layout: { queries: {} },
    })

    const result = await getTemplateStaticProps({ params: {} })
    assert.equal(result.props.template, "front-page")
    assert.equal(result.props.uri, "/")
    // top-level GraphQL fields are spread directly into props.data
    assert.equal(result.props.data.hello, "world")
  })

  it("respects the revalidate option", async () => {
    const { fn } = makeFetchSpy(() => ({
      data: {
        node: { __typename: "Page", id: "p", slug: "about", uri: "/about/" },
        generalSettings: null,
      },
    }))
    setFetch(fn)

    const Page = () => null
    Page.queries = {}
    configure({
      templates: { page: Page, singular: Page, index: () => null },
      Layout: { queries: {} },
    })

    const result = await getTemplateStaticProps(
      { params: { wordpressNode: ["about"] } },
      { revalidate: 60 }
    )
    assert.equal(result.revalidate, 60)
  })

  it("propagates skip predicates on template queries", async () => {
    const responses = [
      {
        data: {
          node: {
            __typename: "ContentType",
            id: "ct",
            uri: "/blog/",
            name: "post",
            graphqlSingleName: "post",
          },
          generalSettings: null,
        },
      },
      { data: { b: "value-from-b" } },
    ]
    const { fn, calls } = makeFetchSpy(({ callIndex }) => responses[callIndex])
    setFetch(fn)

    const ArchivePost = () => null
    ArchivePost.queries = {
      a: { query: `query A { a }`, variables: () => ({}), skip: () => true },
      b: { query: `query B { b }`, variables: () => ({}), skip: () => false },
    }
    configure({
      templates: { "archive-post": ArchivePost, index: () => null },
      Layout: { queries: {} },
    })

    const result = await getTemplateStaticProps({ params: { wordpressNode: ["blog"] } })

    // 'a' was skipped — its top-level fields aren't in data
    assert.equal("a" in result.props.data, false)
    // 'b' ran — its top-level field is in data
    assert.equal(result.props.data.b, "value-from-b")
    // 1 seed + 1 template (only b) = 2 calls
    assert.equal(calls.length, 2)
  })
})
