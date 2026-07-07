import { MDXRemote } from "next-mdx-remote"

import DocsLayout from "components/Docs/DocsLayout"
import Breadcrumbs from "components/Docs/Breadcrumbs"
import PrevNext from "components/Docs/PrevNext"
import { getLayoutData, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"

import { getParsedDoc, listDocSlugs } from "lib/parse-mdx-docs"
import { siblingNav } from "lib/sibling-nav"
import getDeveloperReferenceNav from "lib/developer-reference-nav"

import components from "components/Docs/MdxComponents"

function toActionDocSlug(slugParam) {
  if (!slugParam || (Array.isArray(slugParam) && slugParam.length === 0)) {
    return "actions/index"
  }

  if (Array.isArray(slugParam)) {
    return `actions/${slugParam.join("/")}`
  }

  if (typeof slugParam === "string") {
    return `actions/${slugParam}`
  }

  return null
}

export default function ActionDocPage({
  source,
  toc,
  docsNavData,
  layoutData,
  docSlug,
  hasMarkdownH1,
  nav,
}) {
  const isIndex = docSlug === "actions/index"
  const currentLabel =
    source?.frontmatter?.title || docSlug?.split("/").pop() || ""
  const breadcrumbItems = [
    { label: "Developer Reference", href: "/developer-reference" },
    { label: "Actions", href: "/actions" },
    ...(isIndex ? [] : [{ label: currentLabel }]),
  ]

  return (
    <LayoutProvider value={layoutData}>
      <DocsLayout toc={toc} docsNavData={docsNavData}>
        <Breadcrumbs items={breadcrumbItems} />
        <div
          id="content-wrapper"
          className="relative z-20 mt-8 max-w-none prose"
        >
          {source?.frontmatter?.title && !hasMarkdownH1 && (
            <header className="relative z-20">
              <h1>{source.frontmatter.title}</h1>
            </header>
          )}
          <MDXRemote {...source} components={components} />
          {!isIndex && <PrevNext prev={nav?.prev} next={nav?.next} />}
        </div>
      </DocsLayout>
    </LayoutProvider>
  )
}

export async function getStaticProps({ params }) {
  const docSlug = toActionDocSlug(params?.slug)

  if (!docSlug) {
    return { notFound: true }
  }

  try {
    const { source, toc, hasMarkdownH1 } = await getParsedDoc(docSlug)
    const docsNavData = getDeveloperReferenceNav()
    const layoutData = await getLayoutData()

    let nav = { prev: null, next: null }
    if (docSlug !== "actions/index") {
      const slugs = await listDocSlugs("actions")
      const items = slugs.map((s) => ({
        slug: s,
        label: s,
        href: `/actions/${s}`,
      }))
      nav = siblingNav(items, docSlug.split("/").pop())
    }

    return {
      props: {
        toc,
        source,
        docsNavData,
        layoutData,
        docSlug,
        hasMarkdownH1,
        nav,
      },
      revalidate: 30,
    }
  } catch (e) {
    if (e.notFound) {
      // Include revalidate so a transient build-time fetch failure can't
      // permanently cache a 404.
      return { notFound: true, revalidate: 30 }
    }

    throw e
  }
}

export async function getStaticPaths() {
  return {
    paths: [{ params: { slug: [] } }],
    fallback: "blocking",
  }
}
