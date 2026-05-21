import gql from "graphql-tag"
import SiteLayout from "components/Site/SiteLayout"
import PostPreview, { PostPreviewFragment } from "components/Preview/PostPreview"

export default function ArchivePost({ data }) {
  const posts = data?.posts?.nodes

  return (
    <SiteLayout>
      <main className="content px-6 max-w-lg mx-auto md:max-w-5xl mb-10">
        <div className="space-y-2 pt-6 pb-8 md:space-y-5">
          <h1 className="text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
            Blog
          </h1>
          <p className="text-lg leading-7 text-muted-foreground">
            Read the latest posts from the WPGraphQL team
          </p>
        </div>
        <ul className="grid gap-8 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 auto-rows-fr">
          {posts.map((post, index) => (
            <li key={post.id} className={`flex w-full ${index === 0 ? "xl:col-span-2" : ""}`}>
              <PostPreview post={post} isLatest={index === 0} />
            </li>
          ))}
        </ul>
      </main>
    </SiteLayout>
  )
}

ArchivePost.queries = {
  posts: {
    query: gql`
      query ArchivePost_Posts($first: Int) {
        posts(first: $first) {
          nodes {
            ...PostPreview
          }
        }
      }
      ${PostPreviewFragment}
    `,
    variables: () => ({ first: 100 }),
  },
}
