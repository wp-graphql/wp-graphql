import gql from "graphql-tag"
import PostPreview, {
  PostPreviewFragment,
} from "components/Preview/PostPreview"
import SiteLayout from "components/Site/SiteLayout"

export default function Category({ data }) {
  if (!data) {
    return null
  }

  if (!data.category) {
    return null
  }

  const { category } = data

  return (
    <SiteLayout>
      <main className="content px-6 max-w-lg mx-auto md:max-w-5xl mb-10">
        <div className="space-y-2 pt-6 pb-8 md:space-y-5">
          <h1 className="text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
            Category: {category?.name ?? null}
          </h1>
          <p className="text-lg leading-7 text-muted-foreground">
            {category?.description ?? null}
          </p>
        </div>
        <ul className="grid gap-8 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 auto-rows-fr">
          {category?.posts?.nodes?.map((post, index) => (
            <li key={post.id} className={`flex w-full ${index === 0 ? "xl:col-span-2" : ""}`}>
              <PostPreview post={post} isLatest={index === 0} />
            </li>
          ))}
        </ul>
      </main>
    </SiteLayout>
  )
}

Category.queries = {
  category: {
    query: gql`
      query Category_Node($id: ID!) {
        category(id: $id, idType: URI) {
          name
          description
          posts {
            nodes {
              ...PostPreview
            }
          }
        }
      }
      ${PostPreviewFragment}
    `,
    variables: ({ seed }) => ({ id: seed?.uri }),
  },
}
