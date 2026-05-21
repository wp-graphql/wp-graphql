import { describe, it } from "node:test"
import assert from "node:assert/strict"
import { parse } from "graphql"
import { SEED_QUERY, normalizeSeed } from "../core/seed-query.js"

describe("SEED_QUERY", () => {
  it("is a parseable GraphQL document", () => {
    const doc = parse(SEED_QUERY)
    assert.equal(doc.kind, "Document")
  })

  it("declares a $uri: String! variable", () => {
    const doc = parse(SEED_QUERY)
    const op = doc.definitions[0]
    const v = op.variableDefinitions.find((v) => v.variable.name.value === "uri")
    assert.ok(v, "expected $uri variable")
    assert.equal(v.type.kind, "NonNullType")
    assert.equal(v.type.type.name.value, "String")
  })
})

describe("normalizeSeed — Page", () => {
  it("normalizes a Page node with slug + content type", () => {
    const seed = normalizeSeed(
      {
        data: {
          node: {
            __typename: "Page",
            id: "cG9zdDox",
            uri: "/about/",
            slug: "about",
            databaseId: 1,
            contentType: { node: { name: "page", graphqlSingleName: "page" } },
            title: "About",
          },
          generalSettings: { title: "Site", description: "Desc" },
        },
      },
      "/about/"
    )
    assert.equal(seed.typename, "Page")
    assert.equal(seed.slug, "about")
    assert.equal(seed.postType, "page")
    assert.equal(seed.uri, "/about/")
    assert.equal(seed.isFrontPage, false)
    assert.equal(seed.generalSettings.title, "Site")
  })
})

describe("normalizeSeed — Post (singular)", () => {
  it("extracts postType from contentType.node.graphqlSingleName", () => {
    const seed = normalizeSeed(
      {
        data: {
          node: {
            __typename: "Post",
            id: "x",
            uri: "/hello-world/",
            slug: "hello-world",
            contentType: { node: { name: "post", graphqlSingleName: "post" } },
          },
        },
      },
      "/hello-world/"
    )
    assert.equal(seed.typename, "Post")
    assert.equal(seed.postType, "post")
    assert.equal(seed.slug, "hello-world")
  })
})

describe("normalizeSeed — Category", () => {
  it("normalizes a Category term", () => {
    const seed = normalizeSeed(
      {
        data: {
          node: {
            __typename: "Category",
            id: "x",
            uri: "/category/news/",
            slug: "news",
            taxonomyName: "category",
          },
        },
      },
      "/category/news/"
    )
    assert.equal(seed.typename, "Category")
    assert.equal(seed.slug, "news")
    assert.equal(seed.taxonomy, "category")
    assert.equal(seed.postType, null)
  })
})

describe("normalizeSeed — ContentType (archive)", () => {
  it("uses graphqlSingleName as postType for ContentType nodes", () => {
    const seed = normalizeSeed(
      {
        data: {
          node: {
            __typename: "ContentType",
            id: "x",
            uri: "/blog/",
            name: "post",
            graphqlSingleName: "post",
            label: "Posts",
          },
        },
      },
      "/blog/"
    )
    assert.equal(seed.typename, "ContentType")
    assert.equal(seed.postType, "post")
  })
})

describe("normalizeSeed — front page", () => {
  it("sets isFrontPage true when uri is '/'", () => {
    const seed = normalizeSeed(
      { data: { node: { __typename: "Page", id: "x", slug: "home" } } },
      "/"
    )
    assert.equal(seed.isFrontPage, true)
  })

  it("sets isFrontPage true when uri is empty string", () => {
    const seed = normalizeSeed({ data: { node: null } }, "")
    assert.equal(seed.isFrontPage, true)
  })

  it("sets isFrontPage false for any non-root uri", () => {
    const seed = normalizeSeed(
      { data: { node: { __typename: "Page", slug: "about" } } },
      "/about/"
    )
    assert.equal(seed.isFrontPage, false)
  })
})

describe("normalizeSeed — missing node", () => {
  it("returns null typename and node when nodeByUri returned null", () => {
    const seed = normalizeSeed({ data: { node: null } }, "/missing/")
    assert.equal(seed.node, null)
    assert.equal(seed.typename, null)
    assert.equal(seed.slug, null)
    assert.equal(seed.isFrontPage, false)
  })
})

describe("normalizeSeed — response shape tolerance", () => {
  it("accepts a response without the data wrapper", () => {
    const seed = normalizeSeed(
      { node: { __typename: "Page", slug: "about" } },
      "/about/"
    )
    assert.equal(seed.typename, "Page")
    assert.equal(seed.slug, "about")
  })
})
