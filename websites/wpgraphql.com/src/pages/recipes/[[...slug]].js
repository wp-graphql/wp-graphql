import { MDXRemote } from "next-mdx-remote"
import Link from "next/link"

import DocsLayout from "components/Docs/DocsLayout"
import Breadcrumbs from "components/Docs/Breadcrumbs"
import PreviewCard from "components/Preview/PreviewCard"
import components from "components/Docs/MdxComponents"
import { getLayoutData, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"

import { getParsedDoc } from "lib/parse-mdx-docs"
import getDeveloperReferenceNav from "lib/developer-reference-nav"
import recipesIndex from "generated/recipes-index.json"

// Recipes are rendered directly from the repo-backed markdown in
// plugins/wp-graphql/docs/recipes/ (this route overrides the WordPress
// catch-all for /recipes). This is the interim step toward the longer-term
// dogfooding ideal — WordPress as the CMS, markdown as the content store, and
// WPGraphQL as the API for the headless front-end — so the WordPress
// wp-templates are intentionally left in place for that future hybrid.

function normalizeUri(uri) {
  return String(uri ?? "")
    .replace(/\/+$/, "")
    .toLowerCase()
}

function slugifyHeading(value) {
  return (
    String(value ?? "")
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9\s-]/g, "")
      .replace(/\s+/g, "-")
      .replace(/-+/g, "-") || "group"
  )
}

function RecipesIndex({ layoutData }) {
  const docsNavData = getDeveloperReferenceNav()
  const recipes = Array.isArray(recipesIndex?.recipes) ? recipesIndex.recipes : []

  const grouped = recipes.reduce((acc, recipe) => {
    const group = recipe.group || "Uncategorized"
    if (!acc[group]) {
      acc[group] = []
    }
    acc[group].push(recipe)
    return acc
  }, {})

  const orderedGroups = Object.keys(grouped).sort((a, b) => {
    if (a === "Uncategorized") return 1
    if (b === "Uncategorized") return -1
    return a.localeCompare(b)
  })

  const slugCounts = {}
  const groupToc = orderedGroups.map((groupName) => {
    const base = slugifyHeading(groupName)
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
    <LayoutProvider value={layoutData}>
      <DocsLayout docsNavData={docsNavData} toc={toc}>
        <Breadcrumbs
          items={[
            { label: "Developer Reference", href: "/developer-reference" },
            { label: "Recipes" },
          ]}
        />
        <div
          id="content-wrapper"
          className="relative z-20 mt-8 max-w-none prose"
        >
          <h1 id="recipes">Recipes</h1>
          <p>
            WPGraphQL recipes are practical snippets that show how to customize
            WPGraphQL with actions, filters, and functions.
          </p>

          {orderedGroups.map((groupName, groupIndex) => (
            <section key={groupName}>
              <h2 id={groupToc[groupIndex]?.id}>{groupName}</h2>
              <div className="not-prose grid gap-5">
                {grouped[groupName]
                  .slice()
                  .sort((a, b) => a.title.localeCompare(b.title))
                  .map((recipe) => (
                    <PreviewCard
                      key={recipe.uri}
                      title={recipe.title}
                      excerpt={recipe.summary || ""}
                      href={recipe.uri}
                      cta="View recipe"
                    />
                  ))}
              </div>
            </section>
          ))}
        </div>
      </DocsLayout>
    </LayoutProvider>
  )
}

function RelatedApis({ related }) {
  const groups = [
    { key: "relatedActions", label: "Actions", base: "/actions" },
    { key: "relatedFilters", label: "Filters", base: "/filters" },
    { key: "relatedFunctions", label: "Functions", base: "/functions" },
  ]
  return (
    <section id="related-apis">
      <h2>Related APIs</h2>
      {groups.map(({ key, label, base }) => {
        const names = Array.isArray(related?.[key]) ? related[key] : []
        if (names.length === 0) {
          return null
        }
        return (
          <div key={key}>
            <h3>{label}</h3>
            <ul>
              {names.map((name) => (
                <li key={`${key}-${name}`}>
                  <Link href={`${base}/${name}`}>{name}</Link>
                </li>
              ))}
            </ul>
          </div>
        )
      })}
    </section>
  )
}

function RecipeSingle({ source, toc, hasMarkdownH1, title, related, layoutData }) {
  const docsNavData = getDeveloperReferenceNav()
  const hasRelatedApis =
    (related?.relatedActions?.length ?? 0) > 0 ||
    (related?.relatedFilters?.length ?? 0) > 0 ||
    (related?.relatedFunctions?.length ?? 0) > 0
  const pageToc = [
    { id: "overview", title: "Overview", tagName: "h2" },
    ...(hasRelatedApis
      ? [{ id: "related-apis", title: "Related APIs", tagName: "h2" }]
      : []),
    ...(Array.isArray(toc) ? toc : []),
  ]

  return (
    <LayoutProvider value={layoutData}>
      <DocsLayout docsNavData={docsNavData} toc={pageToc}>
        <Breadcrumbs
          items={[
            { label: "Developer Reference", href: "/developer-reference" },
            { label: "Recipes", href: "/recipes" },
            ...(title ? [{ label: title }] : []),
          ]}
        />
        <div
          id="content-wrapper"
          className="relative z-20 mt-8 max-w-none prose"
        >
          <article>
            {title && !hasMarkdownH1 ? (
              <header>
                <h1>{title}</h1>
              </header>
            ) : null}
            <div id="overview">
              <MDXRemote {...source} components={components} />
            </div>
            {hasRelatedApis ? <RelatedApis related={related} /> : null}
          </article>
        </div>
      </DocsLayout>
    </LayoutProvider>
  )
}

export default function RecipesPage(props) {
  if (props.isIndex) {
    return <RecipesIndex layoutData={props.layoutData} />
  }
  return <RecipeSingle {...props} />
}

export async function getStaticProps({ params }) {
  const slugParts = params?.slug
  const isIndex = !slugParts || (Array.isArray(slugParts) && slugParts.length === 0)
  const layoutData = await getLayoutData()

  if (isIndex) {
    return { props: { isIndex: true, layoutData }, revalidate: 30 }
  }

  const slug = Array.isArray(slugParts) ? slugParts.join("/") : slugParts

  try {
    const { source, toc, hasMarkdownH1 } = await getParsedDoc(`recipes/${slug}`)
    const meta = recipesIndex?.relations?.byUri?.[normalizeUri(`/recipes/${slug}`)] || null

    return {
      props: {
        isIndex: false,
        source,
        toc,
        hasMarkdownH1,
        title: meta?.title || source?.frontmatter?.title || slug,
        related: {
          relatedActions: meta?.relatedActions ?? [],
          relatedFilters: meta?.relatedFilters ?? [],
          relatedFunctions: meta?.relatedFunctions ?? [],
        },
        layoutData,
      },
      revalidate: 30,
    }
  } catch (e) {
    if (e.notFound) {
      return { notFound: true, revalidate: 30 }
    }
    throw e
  }
}

export async function getStaticPaths() {
  const recipePaths = (recipesIndex?.recipes ?? [])
    .map((recipe) => recipe.slug)
    .filter(Boolean)
    .map((slug) => ({ params: { slug: slug.split("/") } }))

  return {
    // `slug: []` renders the /recipes index.
    paths: [{ params: { slug: [] } }, ...recipePaths],
    fallback: "blocking",
  }
}
