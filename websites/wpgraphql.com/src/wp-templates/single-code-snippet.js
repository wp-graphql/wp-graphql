import { gql } from "@apollo/client"
import SiteLayout, { NavMenuFragment } from "components/Site/SiteLayout"
import Link from "next/link"

export default function SingleRecipe({ data }) {
  const { node } = data
  if (!node) {
    return null
  }

  return (
    <SiteLayout>
      <div className="overflow-hidden">
        <div className="mx-auto mt-10 px-4 pb-6 sm:mt-16 sm:px-6 md:px-8 xl:px-12 xl:max-w-6xl">
          <main className="content">
            <article className="relative pt-10 max-w-3xl mx-auto">
              <header>
                <div className="space-y-6">
                  {node.title ? (
                    <h1 className="col-span-full break-words text-3xl sm:text-4xl text-center xl:mb-5 font-extrabold tracking-tight text-slate-900 dark:text-slate-200">
                      {node.title}
                    </h1>
                  ) : null}
                  <div className="flex flex-wrap justify-center">
                    {node?.recipeTags?.nodes?.map((tag, i) => (
                      <Link key={i} href={tag.uri}>
                        <a className="mr-3 text-sm font-medium uppercase text-sky-500 dark:text-sky-300 hover:text-primary-600 dark:hover:text-sky-400">
                          {tag.name}
                        </a>
                      </Link>
                    ))}
                  </div>
                </div>
              </header>
              <div className="mx-auto py-12 px-4 max-w-7xl sm:px-6 lg:px-8 lg:py-12">
                {node.content ? (
                  <div
                    id="content"
                    className="prose dark:prose-dark"
                    dangerouslySetInnerHTML={{ __html: node.content }}
                  />
                ) : null}
              </div>
            </article>
          </main>
        </div>
      </div>
    </SiteLayout>
  )
}

SingleRecipe.query = gql`
  query GetRecipe($uri: ID!) {
    node: contentNode(id: $uri, idType: URI) {
      id
      ... on NodeWithTitle {
        title
      }
      uri
      ... on NodeWithContentEditor {
        content
      }
      ... on CodeSnippet {
        recipeTags: codeSnippetTags {
          nodes {
            id
            name
            uri
          }
        }
      }
    }
    ...NavMenu
  }
  ${NavMenuFragment}
`

SingleRecipe.variables = ({ uri }) => {
  return {
    uri,
  }
}
