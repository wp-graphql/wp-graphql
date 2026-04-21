import { gql } from "@apollo/client"
import Link from "next/link"

export const FilterPreviewFragment = gql`
  fragment FilterPreview on Filter {
    id
    title
    content
    uri
  }
`

export default function FilterPreview({ filter }) {
  const paragraphs = filter?.content ? filter.content.split("</p>") : null
  const excerpt = paragraphs ? paragraphs[0] + "</p>" : null

  return (
    <div className="mb-10 pt-10">
      <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
        {filter.title}
      </h2>
      <div className="py-5">
        <div
          className="prose dark:prose-dark"
          dangerouslySetInnerHTML={{ __html: excerpt }}
        />
      </div>

      <div className="text-base font-medium leading-6">
        <Link href={filter.uri} className="btn-primary-sm">
          <span className="pr-2">View Filter →</span>
        </Link>
      </div>
    </div>
  )
}
