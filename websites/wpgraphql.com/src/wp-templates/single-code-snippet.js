import gql from "graphql-tag"
import SiteLayout from "components/Site/SiteLayout"
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
                    <h1 className="col-span-full break-words text-center text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
                      {node.title}
                    </h1>
                  ) : null}
                  <div className="flex flex-wrap justify-center">
                    {node?.recipeTags?.nodes?.map((tag, i) => (
                      <Link key={i} href={tag.uri} legacyBehavior>
                        <a className="mr-3 font-mono text-xs font-medium uppercase tracking-widest text-primary hover:text-orange-wpg-200">
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
                    className="prose"
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

SingleRecipe.queries = {
  node: {
    query: gql`
      query SingleCodeSnippet_Node($uri: ID!) {
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
      }
    `,
    variables: ({ seed }) => ({ uri: seed?.uri }),
  },
}
