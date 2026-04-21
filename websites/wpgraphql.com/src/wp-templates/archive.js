import { gql } from "@apollo/client"
import Link from "next/link"

import SiteLayout, { NavMenuFragment } from "components/Site/SiteLayout"
import DocsLayout from "components/Docs/DocsLayout"
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
import decodeHtmlEntities from "../../../../scripts/lib/decode-html-entities"

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

function getExcerpt(html, maxLength = 180) {
  const text = decodeHtmlEntities(toPlainText(html))
  if (text.length <= maxLength) {
    return text
  }
  return `${text.slice(0, maxLength).trim()}...`
}

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

function normalizeUri(uri) {
  return String(uri ?? "")
    .replace(/\/+$/, "")
    .toLowerCase()
}

export default function Archive({ data }) {
  const { archive } = data
  const nodes = archive?.contentNodes?.nodes ?? []
  const isRecipeArchive =
    archive?.uri?.startsWith("/recipes") ||
    (archive?.__typename === "TermNode" &&
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

    const toc = [{ id: "recipes", title: "Recipes", tagName: "h2" }, ...groupToc]

    return (
      <DocsLayout docsNavData={docsNavData} toc={toc}>
        <div
          id="content-wrapper"
          className="relative z-20 mt-8 max-w-none prose dark:prose-dark prose-code:before:content-none prose-code:after:content-none"
        >
          <h1>{archive?.label ? archive.label : archive?.name ?? "Recipes"}</h1>
          {archive?.description ? (
            <div dangerouslySetInnerHTML={{ __html: archive.description }} />
          ) : (
            <p>
              WPGraphQL recipes are practical snippets that show how to customize
              WPGraphQL with actions, filters, and functions.
            </p>
          )}

          <div id="recipes">
            {orderedGroups.map((groupName, groupIndex) => {
              const groupItems = groupedRecipes[groupName] || []
              const groupHeading = groupToc[groupIndex]

              return (
                <section key={groupName}>
                  <h2 id={groupHeading?.id}>{groupName}</h2>
                  {groupItems.map((recipe) => (
                    <div key={recipe.id || recipe.uri}>
                      <h3>
                        <Link href={recipe.uri}>{recipe.title}</Link>
                      </h3>
                      {recipe.summary ? (
                        <p>{recipe.summary}</p>
                      ) : recipe?.content ? (
                        <p>{getExcerpt(recipe.content)}</p>
                      ) : null}
                      <p>
                        <Link href={recipe.uri}>View recipe</Link>
                      </p>
                    </div>
                  ))}
                </section>
              )
            })}
          </div>
        </div>
      </DocsLayout>
    )
  }

  return (
    <SiteLayout>
      <div className="overflow-hidden">
        <div className="mx-auto mt-10 px-4 pb-6 sm:mt-16 sm:px-6 md:px-8 xl:px-12 xl:max-w-4xl">
          <header className="text-center">
            <h1 className="mb-6 text-3xl font-extrabold leading-9 tracking-tight text-gray-900 dark:text-gray-100 sm:text-4xl sm:leading-10 md:text-6xl md:leading-14">
              {archive?.label ? archive.label : archive?.name ?? "Archive"}
            </h1>
            <p className="text-lg leading-7 prose dark:prose-dark max-w-3xl mx-auto">
              <span
                dangerouslySetInnerHTML={{ __html: archive?.description }}
              />
            </p>
          </header>
          <main className="content relative pt-10 max-w-3xl mx-auto mb-10">
            <ul className="divide-y divide-gray-200 dark:divide-gray-700">
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
                      <li key={node.id} className="py-12">
                        <pre>{JSON.stringify(node, null, 2)}</pre>
                      </li>
                    )
                }
              })}
            </ul>
          </main>
        </div>
      </div>
    </SiteLayout>
  )
}

Archive.variables = ({ uri }) => {
  return {
    uri,
  }
}

Archive.query = gql`
  query GetContentType($uri: String!) {
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
      ...NavMenu
  }
  ${NavMenuFragment}
  ${ExtensionFragment}
  ${RecipePreviewFragment}
  ${FilterPreviewFragment}
  ${FunctionPreviewFragment}
  ${ActionPreviewFragment}
`
