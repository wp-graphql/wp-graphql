import { gql } from "@apollo/client"
import { MDXRemote } from "next-mdx-remote"

import DocsLayout from "components/Docs/DocsLayout"
import { NavMenuFragment } from "components/Site/SiteHeader"

import { getParsedDoc, getDocsNav, getAllDocUri } from "lib/parse-mdx-docs"

import components from "components/Docs/MdxComponents"

import { getApolloClient, addApolloState } from "@faustwp/core/dist/mjs/client"

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

export default function Doc({ source, toc, docsNavData, hasMarkdownH1 }) {
  return (
    <DocsLayout toc={toc} docsNavData={docsNavData}>
      <div
        id="content-wrapper"
        className="relative z-20 prose mt-8 prose dark:prose-dark prose-code:before:content-none prose-code:after:content-none"
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
  const docSlug = toDocSlug(params?.slug)

  if (!docSlug) {
    return { notFound: true }
  }

  try {
    const { source, toc, hasMarkdownH1 } = await getParsedDoc(docSlug)
    const docsNavData = await getDocsNav()
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
      console.error(params, e)
      return e
    }

    throw e
  }
}

export async function getStaticPaths() {
  const apolloClient = getApolloClient()

  const { data } = await apolloClient.query({
    query: gql`
      query PrebuildDocsQuery {
        menu(id: "Primary Nav", idType: NAME) {
          menuItems {
            nodes {
              parentDatabaseId
              uri
            }
          }
        }
      }
    `,
  })

  // Adds prerendering for Docs linked from main nav menu
  const docsMenuPaths = data?.menu?.menuItems?.nodes?.reduce((acc, menuItem) => {
    if (menuItem.parentDatabaseId !== 0 && menuItem.uri.startsWith("/docs")) {
      acc.push(menuItem.uri)
    }

    return acc
  }, [])

  const generatedDocPaths = await getAllDocUri()
  const allPaths = [...new Set([...(docsMenuPaths ?? []), ...generatedDocPaths])]
  const paths = allPaths.map((uri) => toSlugParams(uri)).filter(Boolean)

  return {
    paths,
    fallback: "blocking",
  }
}
