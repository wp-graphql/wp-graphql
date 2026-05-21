import Link from "next/link"
import Image from "next/image"

import gql from "graphql-tag"

import SiteLayout from "components/Site/SiteLayout"

export default function Singlar({ data }) {
  const { post } = data
  if (!post) {
    return null
  }

  const date = post?.date
    ? new Date(post.date).toLocaleDateString("en-us", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    })
    : null

  return (
    <SiteLayout>
      <div className="overflow-hidden">
        <div className="mx-auto mt-10 px-4 pb-6 sm:mt-16 sm:px-6 md:px-8 xl:px-12 xl:max-w-6xl">
          <main className="content">
            <article className="relative pt-10 max-w-3xl mx-auto">
              <header>
                <dl>
                  <dt className="sr-only">Date</dt>
                  <dd className="absolute inset-x-0 top-0 text-center text-sm font-mono text-muted-foreground">
                    <time dateTime={post?.date}>{date}</time>
                  </dd>
                </dl>
                <div className="space-y-6">
                  <h1 className="col-span-full break-words text-center text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
                    {post.title}
                  </h1>
                  <div className="flex flex-wrap justify-center gap-3">
                    {post?.categories?.nodes?.map((category, i) => (
                      <Link key={i} href={category.uri} legacyBehavior>
                        <a className="font-mono text-xs font-medium uppercase tracking-widest text-primary hover:text-orange-wpg-200">
                          {category.name}
                        </a>
                      </Link>
                    ))}
                  </div>
                </div>

                <div className="flex justify-center my-8">
                  <dl>
                    <div className="justify-center">
                      <dt className="sr-only">Author</dt>
                      <dd className="flex justify-center font-medium mt-6 mx-3">
                        <Image
                          src={post?.author?.node?.avatar?.url}
                          alt={post?.author?.node?.name}
                          width={50}
                          height={50}
                          className="mr-3 h-10 w-10 rounded-full border border-border bg-muted"
                        />
                      </dd>
                      <dd className="text-center items-center">
                        <Link href={post?.author?.node?.uri} legacyBehavior>
                          <a className="text-primary hover:text-orange-wpg-200 transition-colors pt-5 text-center">
                            {post?.author?.node?.name}
                          </a>
                        </Link>
                      </dd>
                    </div>
                  </dl>
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

Singlar.queries = {
  post: {
    query: gql`
      query Singular_Post($uri: ID!) {
        post(id: $uri, idType: URI) {
          id
          title
          uri
          date
          content
          author {
            node {
              name
              uri
              avatar {
                url
              }
            }
          }
          categories {
            nodes {
              id
              name
              uri
            }
          }
        }
      }
    `,
    variables: ({ uri }) => ({ uri }),
  },
}
