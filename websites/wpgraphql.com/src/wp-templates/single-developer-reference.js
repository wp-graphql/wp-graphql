import { gql } from "@apollo/client"

import SiteLayout, { NavMenuFragment } from "components/Site/SiteLayout"

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
                  <h1 className="col-span-full break-words text-3xl sm:text-4xl text-center xl:mb-5 font-extrabold tracking-tight text-slate-900 dark:text-slate-200">
                    {post.title}
                  </h1>
                </div>
              </header>
              <div className="mx-auto py-12 px-4 max-w-7xl sm:px-6 lg:px-8 lg:py-12">
                <div
                  id="content"
                  className="prose dark:prose-dark"
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

SingleDeveloperReference.variables = ({ uri }) => {
  return {
    uri,
  }
}

SingleDeveloperReference.query = gql`
  query GetSingularNode($uri: ID!) {
    post(id: $uri, idType: URI) {
      id
      title
      uri
      content
    }
    ...NavMenu
  }
  ${NavMenuFragment}
`
