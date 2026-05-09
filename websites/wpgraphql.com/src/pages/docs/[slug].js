import { MDXRemote } from "next-mdx-remote"

import DocsLayout from "components/Docs/DocsLayout"
import { getLayoutData, LayoutProvider, request } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"

import { getParsedDoc, getDocsNav } from "lib/parse-mdx-docs"

import components from "components/Docs/MdxComponents"

export default function Doc({ source, toc, docsNavData, layoutData }) {
  return (
    <LayoutProvider value={layoutData}>
      <DocsLayout toc={toc} docsNavData={docsNavData}>
        <div
          id="content-wrapper"
          className="relative z-20 mt-8 prose"
        >
          {source?.frontmatter?.title && (
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
  try {
    const { source, toc } = await getParsedDoc(params.slug)
    const docsNavData = await getDocsNav()
    const layoutData = await getLayoutData()

    return {
      props: {
        toc,
        source,
        docsNavData,
        layoutData,
      },
      revalidate: 30,
    }
  } catch (e) {
    if (e.notFound) {
      console.error(params, e)
      return e
    }

    throw e
  }
}

const PREBUILD_DOCS_QUERY = /* GraphQL */ `
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
`

export async function getStaticPaths() {
  const result = await request({ query: PREBUILD_DOCS_QUERY })
  const data = result?.data ?? {}

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
