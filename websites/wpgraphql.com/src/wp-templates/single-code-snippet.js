import { gql } from "@apollo/client"
import DocsLayout from "components/Docs/DocsLayout"
import { NavMenuFragment } from "components/Site/SiteLayout"
import Link from "next/link"
import getDeveloperReferenceNav from "lib/developer-reference-nav"
import recipesIndex from "generated/recipes-index.json"
import decodeHtmlEntities from "../../../../scripts/lib/decode-html-entities"

function slugifyHeading(value) {
  return String(value ?? "")
    .toLowerCase()
    .trim()
    .replace(/<[^>]+>/g, "")
    .replace(/&[a-z0-9#]+;/gi, "")
    .replace(/[^a-z0-9\s-]/g, "")
    .replace(/\s+/g, "-")
    .replace(/-+/g, "-")
}

function toPlainText(html) {
  return String(html ?? "")
    .replace(/<[^>]*>/g, " ")
    .replace(/&nbsp;/gi, " ")
    .replace(/&amp;/gi, "&")
    .replace(/&lt;/gi, "<")
    .replace(/&gt;/gi, ">")
    .replace(/\s+/g, " ")
    .trim()
}

function getExcerpt(html, maxLength = 200) {
  const text = decodeHtmlEntities(toPlainText(html))
  if (text.length <= maxLength) {
    return text
  }
  return `${text.slice(0, maxLength).trim()}...`
}

function addHeadingIdsAndBuildToc(html) {
  const slugCounts = {}
  const toc = []

  const content = String(html ?? "").replace(
    /<h([23])([^>]*)>([\s\S]*?)<\/h\1>/gi,
    (_match, level, attrs, innerHtml) => {
      const existingIdMatch = attrs.match(/\sid=(["'])(.*?)\1/i)
      const headingText = toPlainText(innerHtml)
      const baseSlug = slugifyHeading(existingIdMatch?.[2] || headingText) || "section"
      const count = slugCounts[baseSlug] ?? 0
      slugCounts[baseSlug] = count + 1
      const id = count === 0 ? baseSlug : `${baseSlug}-${count}`
      const tagName = Number(level) === 2 ? "h2" : "h3"

      toc.push({
        id,
        title: headingText || "Section",
        tagName,
      })

      const cleanedAttrs = attrs.replace(/\sid=(["']).*?\1/i, "")
      return `<h${level}${cleanedAttrs} id="${id}">${innerHtml}</h${level}>`
    }
  )

  return { content, toc }
}

function normalizeUri(uri) {
  return String(uri ?? "")
    .replace(/\/+$/, "")
    .toLowerCase()
}

export default function SingleRecipe({ data }) {
  const { node } = data
  if (!node) {
    return null
  }

  const { content, toc } = addHeadingIdsAndBuildToc(node.content)
  const docsNavData = getDeveloperReferenceNav()
  const excerpt = getExcerpt(node.content)
  const recipeMeta = recipesIndex?.relations?.byUri?.[normalizeUri(node.uri)] || null
  const relatedActions = Array.isArray(recipeMeta?.relatedActions)
    ? recipeMeta.relatedActions
    : []
  const relatedFilters = Array.isArray(recipeMeta?.relatedFilters)
    ? recipeMeta.relatedFilters
    : []
  const relatedFunctions = Array.isArray(recipeMeta?.relatedFunctions)
    ? recipeMeta.relatedFunctions
    : []
  const hasRelatedApis =
    relatedActions.length > 0 || relatedFilters.length > 0 || relatedFunctions.length > 0
  const pageToc = [
    { id: "overview", title: "Overview", tagName: "h2" },
    ...(hasRelatedApis ? [{ id: "related-apis", title: "Related APIs", tagName: "h2" }] : []),
    ...toc,
  ]

  return (
    <DocsLayout docsNavData={docsNavData} toc={pageToc}>
      <div
        id="content-wrapper"
        className="relative z-20 mt-8 max-w-none prose dark:prose-dark prose-code:before:content-none prose-code:after:content-none"
      >
        <article>
          <header>
            {node.title ? <h1>{node.title}</h1> : null}
            {excerpt ? <p>{excerpt}</p> : null}
            {node?.recipeTags?.nodes?.length ? (
              <ul className="not-prose m-0 mb-6 flex list-none flex-wrap gap-2 p-0">
                {node.recipeTags.nodes.map((tag) => (
                  <li key={tag.id}>
                    <Link
                      href={tag.uri}
                      className="inline-flex items-center rounded-md border border-slate-300 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700 hover:border-sky-500 hover:text-sky-500 dark:border-slate-600 dark:text-slate-300 dark:hover:border-sky-300 dark:hover:text-sky-300"
                    >
                      {tag.name}
                    </Link>
                  </li>
                ))}
              </ul>
            ) : null}
          </header>
          <div id="overview">
            {node.content ? (
              <div id="content" dangerouslySetInnerHTML={{ __html: content }} />
            ) : null}
          </div>
          {hasRelatedApis ? (
            <section id="related-apis">
              <h2>Related APIs</h2>
              {relatedActions.length > 0 ? (
                <div>
                  <h3>Actions</h3>
                  <ul>
                    {relatedActions.map((name) => (
                      <li key={`action-${name}`}>
                        <Link href={`/actions/${name}`}>{name}</Link>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
              {relatedFilters.length > 0 ? (
                <div>
                  <h3>Filters</h3>
                  <ul>
                    {relatedFilters.map((name) => (
                      <li key={`filter-${name}`}>
                        <Link href={`/filters/${name}`}>{name}</Link>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
              {relatedFunctions.length > 0 ? (
                <div>
                  <h3>Functions</h3>
                  <ul>
                    {relatedFunctions.map((name) => (
                      <li key={`function-${name}`}>
                        <Link href={`/functions/${name}`}>{name}</Link>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </section>
          ) : null}
        </article>
      </div>
    </DocsLayout>
  )
}

SingleRecipe.query = gql`
  query GetRecipe($uri: ID!) {
    node: contentNode(id: $uri, idType: URI) {
      id
      ... on NodeWithTitle {
        title
      }
      uri
      ... on NodeWithContentEditor {
        content
      }
      ... on CodeSnippet {
        recipeTags: codeSnippetTags {
          nodes {
            id
            name
            uri
          }
        }
      }
    }
    ...NavMenu
  }
  ${NavMenuFragment}
`

SingleRecipe.variables = ({ uri }) => {
  return {
    uri,
  }
}
