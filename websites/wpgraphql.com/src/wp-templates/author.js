import { gql } from "@apollo/client"
import PostPreview, {
  PostPreviewFragment,
} from "components/Preview/PostPreview"
import SiteLayout, { NavMenuFragment } from "components/Site/SiteLayout"
import Image from "next/image"

export default function Author({ data }) {
  return (
    <SiteLayout>
      <div className="overflow-hidden">
        <div className="mx-auto px-4 pb-28  sm:px-6 md:px-8 xl:px-12 xl:max-w-6xl">
          <main className="content space-y-6 divide-y divide-gray-200 dark:divide-gray-700">
            <div className="pt-10 max-w-3xl mx-auto space-y-6 pb-16 ">
              <div className="flex justify-center mb-8">
                <dl>
                  <div className="sm:flex sm:flex-wrap justify-center xl:block">
                    <dt className="sr-only">Author</dt>
                    <dd className="flex justify-center font-medium m-6 sm:mx-3 xl:mx-0">
                      <Image
                        src={data?.user?.avatar?.url}
                        alt={data?.user?.name}
                        width={50}
                        height={50}
                        className="mr-3 w-10 h-10 rounded-full bg-slate-50 dark:bg-slate-800"
                      />
                    </dd>
                    <dd className="text-center items-center">
                      <h1 className="col-span-full break-words text-3xl sm:text-4xl text-center xl:mb-8 font-extrabold tracking-tight text-slate-900 dark:text-slate-200">
                        {data?.user?.name}
                      </h1>
                    </dd>
                  </div>
                </dl>
              </div>

              <div
                className="prose dark:prose-dark text-lg text-center leading-7 max-w-3xl mx-auto"
                dangerouslySetInnerHTML={{
                  __html: data?.user?.description ?? "",
                }}
              />
            </div>
            <div className="max-w-3xl mx-auto space-y-6">
              <ul className="divide-y divide-gray-200 dark:divide-gray-700">
                {data?.user?.posts &&
                  data?.user?.posts.nodes.map((post) => (
                    <li key={post.id} className="py-12">
                      <PostPreview post={post} />
                    </li>
                  ))}
              </ul>
            </div>
          </main>
        </div>
      </div>
    </SiteLayout>
  )
}

Author.query = gql`
  query GetAuthor($id: ID!) {
    user(id: $id) {
      id
      name
      description
      avatar {
        url
      }
      posts {
        nodes {
          ...PostPreview
        }
      }
    }
    ...NavMenu
  }
  ${NavMenuFragment}
  ${PostPreviewFragment}
`

Author.variables = ({ id }) => {
  return {
    id,
  }
}
