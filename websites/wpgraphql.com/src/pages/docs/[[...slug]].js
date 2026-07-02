import { MDXRemote } from "next-mdx-remote"

import DocsLayout from "components/Docs/DocsLayout"
import { getLayoutData, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"

import { getAllDocUri, getDocsNav, getParsedDoc } from "lib/parse-mdx-docs"

import components from "components/Docs/MdxComponents"

function toDocSlug(slugParam) {
  if (Array.isArray(slugParam)) {
    return slugParam.join("/")
  }

  if (typeof slugParam === "string") {
    return slugParam
  }

  return null
}

function toSlugParams(uri) {
  if (typeof uri !== "string") {
    return null
  }

  const normalized = uri.replace(/^\/+|\/+$/g, "")
  if (!normalized.startsWith("docs/")) {
    return null
  }

  const slug = normalized.replace(/^docs\//, "")
  if (!slug) {
    return { params: { slug: [] } }
  }

  return { params: { slug: slug.split("/") } }
}

export default function Doc({ source, toc, docsNavData, layoutData, hasMarkdownH1 }) {
  return (
    <LayoutProvider value={layoutData}>
      <DocsLayout toc={toc} docsNavData={docsNavData}>
        <div
          id="content-wrapper"
          className="relative z-20 mt-8 prose"
        >
          {source?.frontmatter?.title && !hasMarkdownH1 && (
            <header className="relative z-20 -mt-8">
              <h1>{source.frontmatter.title}</h1>
            </header>
          )}
          <MDXRemote {...source} components={components} />
        </div>
      </DocsLayout>
    </LayoutProvider>
  )
}

export async function getStaticProps({ params }) {
  const docSlug = toDocSlug(params?.slug)

  if (!docSlug) {
    return { notFound: true }
  }

  try {
    const { source, toc, hasMarkdownH1 } = await getParsedDoc(docSlug)
    const docsNavData = await getDocsNav()
    const layoutData = await getLayoutData()

    return {
      props: {
        toc,
        source,
        docsNavData,
        hasMarkdownH1,
        layoutData,
      },
      revalidate: 30,
    }
  } catch (e) {
    if (e.notFound) {
      console.error(params, e)
      // Include revalidate so a transient build-time fetch failure can't
      // permanently cache a 404 — without this, ISR never retries the page
      // even after the underlying .md file becomes reachable again.
      return { notFound: true, revalidate: 30 }
    }

    throw e
  }
}

export async function getStaticPaths() {
  // Pre-render paths sourced from the actual .md files in the docs folder,
  // not from the WordPress Primary Nav menu. The menu only references ~4 docs
  // out of ~50, and any drift between menu URIs and real files produced
  // permanent static 404s for the menu-linked docs.
  let paths = []
  try {
    const uris = await getAllDocUri()
    paths = uris.map((uri) => toSlugParams(uri)).filter(Boolean)
  } catch (e) {
    console.error("getStaticPaths: failed to enumerate docs from GitHub", e)
  }

  return {
    paths,
    fallback: "blocking",
  }
}
