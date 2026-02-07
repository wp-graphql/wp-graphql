import Link from "next/link"
import Image from "next/image"

import { gql } from "@apollo/client"

import SiteLayout, { NavMenuFragment } from "components/Site/SiteLayout"

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
                  <dd className="absolute top-0 inset-x-0 text-navy text-center dark:text-slate-400">
                    <time dateTime={post?.date}>{date}</time>
                  </dd>
                </dl>
                <div className="space-y-6">
                  <h1 className="col-span-full break-words text-3xl sm:text-4xl text-center xl:mb-5 font-extrabold tracking-tight text-navy dark:text-slate-200">
                    {post.title}
                  </h1>
                  <div className="flex flex-wrap justify-center">
                    {post?.categories?.nodes?.map((category, i) => (
                      <Link key={i} href={category.uri}>
                        <a className="text-base font-semibold tracking-wider text-purple-600 dark:text-purple-400 px-3">
                          {category.name}
                        </a>
                        {/* <a className="mr-3 text-sm font-medium uppercase text-sky-500 dark:text-sky-300 hover:text-primary-600 dark:hover:text-sky-400">
                          {category.name}
                        </a> */}
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
                          className="mr-3 w-10 h-10 rounded-full bg-slate-50 dark:bg-slate-800"
                        />
                      </dd>
                      <dd className="text-center items-center">
                        <Link href={post?.author?.node?.uri}>
                          <a className="text-sky-500 dark:text-sky-300 dark:hover:text-sky-400 hover:text-sky-600 dark:text-sky-400 pt-5 text-center">
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

Singlar.variables = ({ uri }) => {
  return {
    uri,
  }
}

Singlar.query = gql`
  query GetSingularNode($uri: ID!) {
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
    ...NavMenu
  }
  ${NavMenuFragment}
`
