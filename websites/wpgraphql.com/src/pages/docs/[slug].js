import { gql } from "@apollo/client"
import { MDXRemote } from "next-mdx-remote"

import DocsLayout from "components/Docs/DocsLayout"
import { NavMenuFragment } from "components/Site/SiteHeader"

import { getParsedDoc, getDocsNav } from "lib/parse-mdx-docs"

import components from "components/Docs/MdxComponents"

import { getApolloClient, addApolloState } from "@faustwp/core/dist/mjs/client"

export default function Doc({ source, toc, docsNavData }) {
  return (
    <DocsLayout toc={toc} docsNavData={docsNavData}>
      <div
        id="content-wrapper"
        className="relative z-20 prose mt-8 prose dark:prose-dark"
      >
        {source?.frontmatter?.title && (
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
  try {
    const { source, toc } = await getParsedDoc(params.slug)
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
  const docs_menu_paths = data?.menu?.menuItems?.nodes?.reduce(
    (acc, menu_item) => {
      if (
        menu_item.parentDatabaseId != 0 &&
        menu_item.uri.startsWith("/docs")
      ) {
        acc.push(menu_item.uri)
      }

      return acc
    },
    []
  )

  return {
    paths: docs_menu_paths ?? [],
    fallback: "blocking",
  }
}
