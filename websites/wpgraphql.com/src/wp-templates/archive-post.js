import { gql } from "@apollo/client"
import SiteLayout, { NavMenuFragment } from "components/Site/SiteLayout"
import PostPreview, { PostPreviewFragment } from "components/Preview/PostPreview"

export default function ArchivePost({ data }) {
  const posts = data?.posts?.nodes

  return (
    <SiteLayout>
      <main className="content px-6 max-w-lg mx-auto md:max-w-5xl mb-10">
        <div className="space-y-2 pt-6 pb-8 md:space-y-5">
          <h1 className="text-3xl font-extrabold leading-9 tracking-tight text-navy dark:text-gray-100 sm:text-4xl sm:leading-10 md:text-6xl md:leading-14">
            Blog
          </h1>
          <p className="text-lg leading-7 text-navy dark:text-white">
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

ArchivePost.variables = (_props) => {
  return {
    first: 100,
  }
}

ArchivePost.query = gql`
  query GetPostsForBlog($first: Int) {
    posts(first: $first) {
      nodes {
        ...PostPreview
      }
    }
    ...NavMenu
  }
  ${PostPreviewFragment}
  ${NavMenuFragment}
`
