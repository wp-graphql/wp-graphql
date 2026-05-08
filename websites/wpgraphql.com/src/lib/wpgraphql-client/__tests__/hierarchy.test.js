import { describe, it } from "node:test"
import assert from "node:assert/strict"
import { buildCandidateNames, resolveTemplateName } from "../core/hierarchy.js"

const Stub = () => null
const fullRegistry = {
  "front-page": Stub,
  home: Stub,
  "single-post-hello-world": Stub,
  "single-post": Stub,
  single: Stub,
  singular: Stub,
  "page-about": Stub,
  page: Stub,
  "category-news": Stub,
  category: Stub,
  "tag-foo": Stub,
  tag: Stub,
  "taxonomy-genre-jazz": Stub,
  "taxonomy-genre": Stub,
  taxonomy: Stub,
  "author-alice": Stub,
  author: Stub,
  "archive-post": Stub,
  archive: Stub,
  404: Stub,
  index: Stub,
}

describe("buildCandidateNames", () => {
  it("front page wins when isFrontPage is true", () => {
    const names = buildCandidateNames({ isFrontPage: true, typename: "Page", slug: "home" })
    assert.equal(names[0], "front-page")
  })

  it("home is preferred for isPostsPage when not the front page", () => {
    const names = buildCandidateNames({ isPostsPage: true, typename: "Page", slug: "blog" })
    assert.equal(names[0], "home")
  })

  it("orders single-{postType}-{slug} before single-{postType}, then single, singular", () => {
    const names = buildCandidateNames({ typename: "Post", postType: "post", slug: "hello-world" })
    assert.deepEqual(names.slice(0, 4), [
      "single-post-hello-world",
      "single-post",
      "single",
      "singular",
    ])
  })

  it("orders page-{slug} before page, then singular", () => {
    const names = buildCandidateNames({ typename: "Page", slug: "about" })
    assert.deepEqual(names.slice(0, 3), ["page-about", "page", "singular"])
  })

  it("orders category-{slug} before category, then archive", () => {
    const names = buildCandidateNames({ typename: "Category", slug: "news" })
    assert.deepEqual(names.slice(0, 3), ["category-news", "category", "archive"])
  })

  it("orders tag-{slug} before tag, then archive", () => {
    const names = buildCandidateNames({ typename: "Tag", slug: "foo" })
    assert.deepEqual(names.slice(0, 3), ["tag-foo", "tag", "archive"])
  })

  it("orders taxonomy-{tax}-{slug} before taxonomy-{tax}, taxonomy, archive", () => {
    const names = buildCandidateNames({
      typename: "Genre",
      taxonomy: "genre",
      slug: "jazz",
    })
    assert.deepEqual(names.slice(0, 4), [
      "taxonomy-genre-jazz",
      "taxonomy-genre",
      "taxonomy",
      "archive",
    ])
  })

  it("orders author-{slug} before author, then archive", () => {
    const names = buildCandidateNames({ typename: "User", slug: "alice" })
    assert.deepEqual(names.slice(0, 3), ["author-alice", "author", "archive"])
  })

  it("orders archive-{postType} before archive for ContentType nodes", () => {
    const names = buildCandidateNames({ typename: "ContentType", postType: "post" })
    assert.deepEqual(names.slice(0, 2), ["archive-post", "archive"])
  })

  it("appends 404 then index when there's no node and it's not the front page", () => {
    const names = buildCandidateNames({ node: null, typename: null })
    assert.deepEqual(names.slice(-2), ["404", "index"])
  })

  it("always ends with index as the final fallback", () => {
    const names = buildCandidateNames({ typename: "Post", postType: "post", slug: "x" })
    assert.equal(names.at(-1), "index")
  })

  it("dedupes repeated entries", () => {
    const names = buildCandidateNames({ typename: "Page", slug: "page" })
    const counts = names.reduce((m, n) => ((m[n] = (m[n] ?? 0) + 1), m), {})
    for (const n of Object.keys(counts)) assert.equal(counts[n], 1, `dup ${n}`)
  })

  it("normalizes slug + postType + taxonomy to lowercase", () => {
    const names = buildCandidateNames({
      typename: "Post",
      postType: "POST",
      slug: "Hello-World",
    })
    assert.ok(names.includes("single-post-hello-world"))
    assert.ok(names.includes("single-post"))
  })

  it("emits both kebab-case and lowered variants for camelCase post types", () => {
    const names = buildCandidateNames({
      typename: "CodeSnippet",
      postType: "CodeSnippet",
      slug: "page-siblings-connection",
    })
    // kebab form first
    assert.ok(
      names.includes("single-code-snippet-page-siblings-connection"),
      "expected kebab single-{slug} variant"
    )
    assert.ok(names.includes("single-code-snippet"), "expected kebab variant")
    // also the lowered-compact form
    assert.ok(names.includes("single-codesnippet"), "expected lowered variant")
  })

  it("emits both variants for snake_case post types", () => {
    const names = buildCandidateNames({
      typename: "Post",
      postType: "code_snippet",
      slug: "x",
    })
    assert.ok(names.includes("single-code-snippet"))
    assert.ok(names.includes("single-code_snippet"))
  })

  it("emits archive-{type} variants for ContentType nodes", () => {
    const names = buildCandidateNames({
      typename: "ContentType",
      postType: "CodeSnippet",
    })
    assert.ok(names.includes("archive-code-snippet"))
    assert.ok(names.includes("archive-codesnippet"))
    assert.ok(names.includes("archive"))
  })

  it("returns an empty array for null seed", () => {
    assert.deepEqual(buildCandidateNames(null), [])
  })
})

describe("resolveTemplateName", () => {
  it("returns front-page when seed.isFrontPage and registry has it", () => {
    assert.equal(
      resolveTemplateName({ isFrontPage: true, typename: "Page" }, fullRegistry),
      "front-page"
    )
  })

  it("falls through to less-specific entries when more-specific are missing", () => {
    const partial = { single: Stub, singular: Stub, index: Stub }
    assert.equal(
      resolveTemplateName(
        { typename: "Post", postType: "post", slug: "hello-world" },
        partial
      ),
      "single"
    )
  })

  it("returns singular as a final fallback for an unknown content type", () => {
    const r = { singular: Stub, index: Stub }
    assert.equal(resolveTemplateName({ typename: "Whatever" }, r), "singular")
  })

  it("returns 404 when no node and registry has 404", () => {
    assert.equal(resolveTemplateName({ node: null }, fullRegistry), "404")
  })

  it("returns index when nothing matches but index exists", () => {
    const r = { index: Stub }
    assert.equal(resolveTemplateName({ typename: "Whatever" }, r), "index")
  })

  it("throws when no candidate is in the registry", () => {
    assert.throws(
      () => resolveTemplateName({ typename: "Post", postType: "post" }, {}),
      /no matching template found/
    )
  })

  it("throws when registry is not an object", () => {
    assert.throws(
      () => resolveTemplateName({ typename: "Post" }, null),
      /must be an object/
    )
  })

  it("matches the wpgraphql.com registry shape", () => {
    const liveLikeRegistry = {
      category: Stub,
      author: Stub,
      archive: Stub,
      "archive-post": Stub,
      singular: Stub,
      "single-code-snippet": Stub,
      "single-function": Stub,
      "single-action": Stub,
      "single-filter": Stub,
      "front-page": Stub,
    }

    assert.equal(
      resolveTemplateName({ isFrontPage: true, typename: "Page" }, liveLikeRegistry),
      "front-page"
    )
    assert.equal(
      resolveTemplateName({ typename: "Category", slug: "news" }, liveLikeRegistry),
      "category"
    )
    assert.equal(
      resolveTemplateName({ typename: "ContentType", postType: "post" }, liveLikeRegistry),
      "archive-post"
    )
    assert.equal(
      resolveTemplateName({ typename: "User", slug: "alice" }, liveLikeRegistry),
      "author"
    )
    assert.equal(
      resolveTemplateName(
        { typename: "Post", postType: "post", slug: "x" },
        liveLikeRegistry
      ),
      "singular"
    )
    // Custom post types resolve to their dedicated single-* slot via kebab variant
    assert.equal(
      resolveTemplateName(
        { typename: "CodeSnippet", postType: "CodeSnippet", slug: "x" },
        liveLikeRegistry
      ),
      "single-code-snippet"
    )
    assert.equal(
      resolveTemplateName(
        { typename: "Function", postType: "Function", slug: "x" },
        liveLikeRegistry
      ),
      "single-function"
    )
  })
})
