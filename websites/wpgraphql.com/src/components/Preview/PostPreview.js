import gql from "graphql-tag"
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
      className={`flex w-full flex-col justify-between rounded-xl border border-border bg-card p-6 transition-all hover:border-primary/40 hover:shadow-glow-sm ${
        isLatest ? "xl:col-span-2 xl:row-span-2" : ""
      }`}
    >
      <div className="mb-4 flex items-center">
        <img
          className="mr-4 h-10 w-10 rounded-full border border-border"
          src={post.author.node.avatar.url}
          alt={post.author.node.name}
        />
        <div className="text-sm">
          <p className="leading-none text-foreground">{post.author.node.name}</p>
          <time className="font-mono text-xs text-muted-foreground" dateTime={post.date}>
            {date}
          </time>
        </div>
      </div>
      <div>
        <h2 className="mb-2 text-2xl font-bold leading-8 tracking-tight">
          <Link href={post.uri} legacyBehavior>
            <a className="text-foreground hover:text-primary transition-colors">{post.title}</a>
          </Link>
        </h2>
      </div>
    </article>
  );
}
