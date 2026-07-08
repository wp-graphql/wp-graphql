import gql from "graphql-tag"

import SiteLayout from "components/Site/SiteLayout"
import PreviewCard from "components/Preview/PreviewCard"
import DocsLayout from "components/Docs/DocsLayout"
import Breadcrumbs from "components/Docs/Breadcrumbs"
import getDeveloperReferenceNav from "lib/developer-reference-nav"
import recipesIndex from "generated/recipes-index.json"

import ExtensionPreview, {
  ExtensionFragment,
} from "components/Preview/ExtensionPreview"
import RecipePreview, {
  RecipePreviewFragment,
} from "components/Preview/RecipePreview"
import FilterPreview, {
  FilterPreviewFragment,
} from "components/Preview/FilterPreview"
import FunctionPreview, {
  FunctionPreviewFragment,
} from "components/Preview/FunctionPreview"
import ActionPreview, {
  ActionPreviewFragment,
} from "components/Preview/ActionPreview"
import ExtensionsArchive from "components/extensions/ExtensionsArchive"
import decodeHtmlEntities from "../../../../scripts/lib/decode-html-entities"

function toPlainText(html) {
  // Strip tags first, then decode entities exactly once. decodeHtmlEntities
  // resolves &amp; last, so a single pass can't double-unescape sequences
  // like &amp;lt; into a real angle bracket.
  return decodeHtmlEntities(String(html ?? "").replace(/<[^>]*>/g, " "))
    .replace(/\u00a0/g, " ")
    .replace(/\s+/g, " ")
    .trim()
}

function getExcerpt(html, maxLength = 180) {
  const text = toPlainText(html)
  if (text.length <= maxLength) {
    return text
  }
  return `${text.slice(0, maxLength).trim()}...`
}

function slugifyHeading(value) {
  let text = String(value ?? "")
    .toLowerCase()
    .trim()

  // Strip tags repeatedly until stable so nested sequences like
  // "<scr<script>ipt>" can't survive a single pass.
  let previous
  do {
    previous = text
    text = text.replace(/<[^>]*>/g, "")
  } while (text !== previous)

  return text
    .replace(/&[a-z0-9#]+;/gi, "")
    .replace(/[^a-z0-9\s-]/g, "")
    .replace(/\s+/g, "-")
    .replace(/-+/g, "-")
}

function normalizeUri(uri) {
  return String(uri ?? "")
    .replace(/\/+$/, "")
    .toLowerCase()
}

export default function Archive({ data }) {
  const { archive } = data
  const nodes = archive?.contentNodes?.nodes ?? []
  // A recipe-tag term archive resolves to the concrete `CodeSnippetTag`
  // type — `TermNode` is an interface and is never a `__typename` value, so
  // matching on it would make this branch dead.
  const isRecipeArchive =
    archive?.uri?.startsWith("/recipes") ||
    (archive?.__typename === "CodeSnippetTag" &&
      nodes.length > 0 &&
      nodes.every((node) => node.__typename === "CodeSnippet"))

  if (isRecipeArchive) {
    const docsNavData = getDeveloperReferenceNav()
    const recipes = nodes.filter((node) => node.__typename === "CodeSnippet")
    const metadataByUri = recipesIndex?.relations?.byUri ?? {}

    const groupedRecipes = recipes.reduce((acc, recipe, index) => {
      const metadata = metadataByUri[normalizeUri(recipe.uri)] || null
      const group = metadata?.group || "Uncategorized"
      if (!acc[group]) {
        acc[group] = []
      }
      acc[group].push({
        ...recipe,
        title: metadata?.title || recipe.title || `Recipe ${index + 1}`,
        summary: metadata?.summary || "",
      })
      return acc
    }, {})

    const orderedGroups = Object.keys(groupedRecipes).sort((a, b) => {
      if (a === "Uncategorized") {
        return 1
      }
      if (b === "Uncategorized") {
        return -1
      }
      return a.localeCompare(b)
    })

    const slugCounts = {}
    const groupToc = orderedGroups.map((groupName) => {
      const base = slugifyHeading(groupName) || "group"
      const count = slugCounts[base] ?? 0
      slugCounts[base] = count + 1
      return {
        id: count === 0 ? base : `${base}-${count}`,
        title: groupName,
        tagName: "h2",
      }
    })

    const toc = [
      { id: "recipes", title: "Recipes", tagName: "h2" },
      ...groupToc,
    ]

    return (
      <DocsLayout docsNavData={docsNavData} toc={toc}>
        <Breadcrumbs
          items={[
            { label: "Developer Reference", href: "/developer-reference" },
            { label: "Recipes" },
          ]}
        />
        <div
          id="content-wrapper"
          className="relative z-20 mt-8 max-w-none prose dark:prose-dark prose-code:before:content-none prose-code:after:content-none"
        >
          <h1>
            {archive?.label ? archive.label : (archive?.name ?? "Recipes")}
          </h1>
          {archive?.description ? (
            <div dangerouslySetInnerHTML={{ __html: archive.description }} />
          ) : (
            <p>
              WPGraphQL recipes are practical snippets that show how to
              customize WPGraphQL with actions, filters, and functions.
            </p>
          )}

          <div id="recipes">
            {orderedGroups.map((groupName, groupIndex) => {
              const groupItems = groupedRecipes[groupName] || []
              const groupHeading = groupToc[groupIndex]

              return (
                <section key={groupName}>
                  <h2 id={groupHeading?.id}>{groupName}</h2>
                  <div className="not-prose grid gap-5">
                    {groupItems.map((recipe) => (
                      <PreviewCard
                        key={recipe.id || recipe.uri}
                        title={recipe.title}
                        excerpt={
                          recipe.summary ||
                          (recipe?.content ? getExcerpt(recipe.content) : "")
                        }
                        href={recipe.uri}
                        cta="View recipe"
                      />
                    ))}
                  </div>
                </section>
              )
            })}
          </div>
        </div>
      </DocsLayout>
    )
  }

  // The Extensions archive (ExtensionPlugin content type) gets a branded
  // featured section on top of the headless community list.
  if (nodes.some((node) => node.__typename === "ExtensionPlugin")) {
    return (
      <SiteLayout>
        <ExtensionsArchive nodes={nodes} />
      </SiteLayout>
    )
  }

  return (
    <SiteLayout>
      <div className="mx-auto max-w-5xl px-6 pb-24 pt-16 sm:pt-20">
        <header className="mx-auto max-w-3xl text-center">
          <h1 className="text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
            {archive?.label ? archive.label : (archive?.name ?? "Archive")}
          </h1>
          {archive?.description && (
            <div
              className="prose mx-auto mt-5 text-base leading-relaxed sm:text-lg"
              dangerouslySetInnerHTML={{ __html: archive.description }}
            />
          )}
        </header>
        <main className="mt-12 grid gap-5 sm:mt-16">
          {archive?.contentNodes?.nodes?.map((node) => {
            switch (node.__typename) {
              case "ExtensionPlugin":
                return <ExtensionPreview key={node.id} extension={node} />
              case "CodeSnippet":
                return <RecipePreview key={node.id} recipe={node} />
              case "Filter":
                return <FilterPreview key={node.id} filter={node} />
              case "Function":
                return <FunctionPreview key={node.id} node={node} />
              case "Action":
                return <ActionPreview key={node.id} node={node} />
              default:
                return (
                  <pre
                    key={node.id}
                    className="overflow-auto rounded-xl border border-border bg-card p-6 text-xs"
                  >
                    {JSON.stringify(node, null, 2)}
                  </pre>
                )
            }
          })}
        </main>
      </div>
    </SiteLayout>
  )
}

Archive.queries = {
  archive: {
    query: gql`
      query Archive_Node($uri: String!) {
        archive: nodeByUri(uri: $uri) {
          __typename
          id
          uri
          ... on ContentType {
            name
            description
            label
            contentNodes(first: 100) {
              nodes {
                __typename
                ...ExtensionPreview
                ...RecipePreview
                ...FilterPreview
                ...FunctionPreview
                ...ActionPreview
              }
            }
          }
          ... on TermNode {
            name
            description
            ... on CodeSnippetTag {
              contentNodes(first: 100) {
                nodes {
                  __typename
                  ...ExtensionPreview
                  ...RecipePreview
                  ...FilterPreview
                  ...FunctionPreview
                  ...ActionPreview
                }
              }
            }
          }
        }
      }
      ${ExtensionFragment}
      ${RecipePreviewFragment}
      ${FilterPreviewFragment}
      ${FunctionPreviewFragment}
      ${ActionPreviewFragment}
    `,
    variables: ({ seed }) => ({ uri: seed?.uri }),
  },
}
