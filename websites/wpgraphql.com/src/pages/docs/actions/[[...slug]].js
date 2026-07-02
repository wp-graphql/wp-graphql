import { MDXRemote } from "next-mdx-remote"

import DocsLayout from "components/Docs/DocsLayout"
import { getLayoutData, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"

import { getParsedDoc } from "lib/parse-mdx-docs"
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

export default function ActionDoc({
  source,
  toc,
  docsNavData,
  layoutData,
  hasMarkdownH1,
}) {
  return (
    <LayoutProvider value={layoutData}>
      <DocsLayout toc={toc} docsNavData={docsNavData}>
        <div
          id="content-wrapper"
          className="relative z-20 mt-8 max-w-none prose"
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
  const docSlug = toActionDocSlug(params?.slug)

  if (!docSlug) {
    return { notFound: true }
  }

  try {
    const { source, toc, hasMarkdownH1 } = await getParsedDoc(docSlug)
    const docsNavData = getDeveloperReferenceNav()
    const layoutData = await getLayoutData()

    return {
      props: {
        toc,
        source,
        docsNavData,
        layoutData,
        hasMarkdownH1,
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
