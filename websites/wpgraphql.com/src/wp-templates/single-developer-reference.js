import gql from "graphql-tag"

import SiteLayout from "components/Site/SiteLayout"

export default function SingleDeveloperReference({ data }) {
  const { post } = data
  if (!post) {
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
                  <h1 className="col-span-full break-words text-center text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
                    {post.title}
                  </h1>
                </div>
              </header>
              <div className="mx-auto py-12 px-4 max-w-7xl sm:px-6 lg:px-8 lg:py-12">
                <div
                  id="content"
                  className="prose"
                  dangerouslySetInnerHTML={{ __html: post.content }}
                />
              </div>
            </article>
          </main>
        </div>
      </div>
    </SiteLayout>
  )
}

SingleDeveloperReference.queries = {
  post: {
    query: gql`
      query SingleDeveloperReference_Post($uri: ID!) {
        post(id: $uri, idType: URI) {
          id
          title
          uri
          content
        }
      }
    `,
    variables: ({ seed }) => ({ uri: seed?.uri }),
  },
}
