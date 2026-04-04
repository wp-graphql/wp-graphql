import { gql } from "@apollo/client"
import Link from "next/link"

export const PostPreviewFragment = gql`
  fragment PostPreview on Post {
    id
    title
    uri
    date
    author {
      node {
        id
        name
        uri
        avatar {
          url
        }
      }
    }
  }
`

export default function PostPreview({ post, isLatest }) {
  if (!post) {
    return null;
  }

  const date = post?.date
    ? new Date(post.date).toLocaleDateString("en-us", {
        year: "numeric",
        month: "long",
        day: "numeric",
      })
    : null;

  return (
    <article
      className={`bg-gradient-elevate-light dark:bg-gradient-elevate p-6 rounded-lg flex flex-col justify-between w-full ${
        isLatest ? "xl:col-span-2 xl:row-span-2" : ""
      }`}
    >
      <div className="flex items-center mb-4">
        <img
          className="w-10 h-10 rounded-full mr-4"
          src={post.author.node.avatar.url}
          alt={post.author.node.name}
        />
        <div className="text-sm">
          <p className="text-navy dark:text-gray-100 leading-none">{post.author.node.name}</p>
          <time className="text-gray-500 dark:text-gray-300" dateTime={post.date}>
            {date}
          </time>
        </div>
      </div>
      <div>
        <h2 className="text-2xl font-bold leading-8 tracking-tight mb-2">
          <Link href={post.uri}>
            <a className="text-navy dark:text-gray-100">{post.title}</a>
          </Link>
        </h2>
      </div>
    </article>
  );
}
