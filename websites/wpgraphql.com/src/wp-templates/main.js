import gql from "graphql-tag"

import SiteLayout from "components/Site/SiteLayout"

export default function Index({ data }) {
  return (
    <SiteLayout>
      <main className="content">
        <h2>INDEX...</h2>
        <pre>{JSON.stringify(data, null, 2)}</pre>
      </main>
    </SiteLayout>
  )
}

Index.nextQueries = {
  posts: {
    query: gql`
      query Index_Posts {
        INDEX: __typename
        posts {
          nodes {
            id
            title
            author {
              node {
                name
                uri
              }
            }
          }
        }
      }
    `,
    variables: () => ({}),
  },
}
