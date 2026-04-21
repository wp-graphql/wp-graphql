import { gql } from "@apollo/client"
import { MDXRemote } from "next-mdx-remote"

import DocsLayout from "components/Docs/DocsLayout"
import { NavMenuFragment } from "components/Site/SiteHeader"

import { getParsedDoc } from "lib/parse-mdx-docs"
import getDeveloperReferenceNav from "lib/developer-reference-nav"

import components from "components/Docs/MdxComponents"

import { getApolloClient, addApolloState } from "@faustwp/core/dist/mjs/client"

function toFilterDocSlug(slugParam) {
  if (!slugParam || (Array.isArray(slugParam) && slugParam.length === 0)) {
    return "filters/index"
  }

  if (Array.isArray(slugParam)) {
    return `filters/${slugParam.join("/")}`
  }

  if (typeof slugParam === "string") {
    return `filters/${slugParam}`
  }

  return null
}

export default function FilterDocPage({ source, toc, docsNavData, hasMarkdownH1 }) {
  return (
    <DocsLayout toc={toc} docsNavData={docsNavData}>
      <div
        id="content-wrapper"
        className="relative z-20 mt-8 max-w-none prose dark:prose-dark prose-code:before:content-none prose-code:after:content-none"
      >
        {source?.frontmatter?.title && !hasMarkdownH1 && (
          <header className="relative z-20 -mt-8">
            <h1>{source.frontmatter.title}</h1>
          </header>
        )}
        <MDXRemote {...source} components={components} />
      </div>
    </DocsLayout>
  )
}

export async function getStaticProps({ params }) {
  const docSlug = toFilterDocSlug(params?.slug)

  if (!docSlug) {
    return { notFound: true }
  }

  try {
    const { source, toc, hasMarkdownH1 } = await getParsedDoc(docSlug)
    const docsNavData = getDeveloperReferenceNav()
    const apolloClient = getApolloClient()

    await apolloClient.query({
      query: gql`
        query NavQuery {
          ...NavMenu
        }
        ${NavMenuFragment}
      `,
    })

    return addApolloState(apolloClient, {
      props: {
        toc,
        source,
        docsNavData,
        hasMarkdownH1,
      },
      revalidate: 30,
    })
  } catch (e) {
    if (e.notFound) {
      return e
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
