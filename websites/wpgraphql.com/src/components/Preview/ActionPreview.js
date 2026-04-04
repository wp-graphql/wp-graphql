import { gql } from "@apollo/client"
import Link from "next/link"

export const ActionPreviewFragment = gql`
  fragment ActionPreview on Action {
    id
    title
    content
    uri
  }
`

export default function ActionPreview({ node }) {
  const paragraphs = node?.content ? node.content.split("</p>") : null
  const excerpt = paragraphs ? paragraphs[0] + "</p>" : null

  return (
    <div className="mb-10 pt-10">
      <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
        {node.title}
      </h2>
      <div className="py-5">
        <div
          className="prose dark:prose-dark"
          dangerouslySetInnerHTML={{ __html: excerpt }}
        />
      </div>

      <div className="text-base font-medium leading-6">
        <Link href={node.uri}>
          <a className="btn-primary-sm">
            <span className="pr-2">View Action →</span>
          </a>
        </Link>
      </div>
    </div>
  )
}
