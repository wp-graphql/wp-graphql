import gql from "graphql-tag"
import PostPreview, {
  PostPreviewFragment,
} from "components/Preview/PostPreview"
import SiteLayout from "components/Site/SiteLayout"
import Image from "next/image"

export default function Author({ data }) {
  return (
    <SiteLayout>
      <div className="overflow-hidden">
        <div className="mx-auto px-4 pb-28  sm:px-6 md:px-8 xl:px-12 xl:max-w-6xl">
          <main className="content space-y-6 divide-y divide-border">
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
                        className="mr-3 h-10 w-10 rounded-full border border-border bg-muted"
                      />
                    </dd>
                    <dd className="text-center items-center">
                      <h1 className="col-span-full break-words text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
                        {data?.user?.name}
                      </h1>
                    </dd>
                  </div>
                </dl>
              </div>

              <div
                className="prose mx-auto max-w-3xl text-center text-lg leading-7"
                dangerouslySetInnerHTML={{
                  __html: data?.user?.description ?? "",
                }}
              />
            </div>
            <div className="max-w-3xl mx-auto space-y-6">
              <ul className="divide-y divide-border">
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

Author.queries = {
  user: {
    query: gql`
      query Author_User($id: ID!) {
        user(id: $id, idType: URI) {
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
      }
      ${PostPreviewFragment}
    `,
    variables: ({ seed }) => ({ id: seed?.uri }),
  },
}
