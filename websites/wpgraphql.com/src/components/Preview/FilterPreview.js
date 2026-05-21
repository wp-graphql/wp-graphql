import gql from "graphql-tag"
import PreviewCard from "./PreviewCard"

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
    <PreviewCard
      title={filter.title}
      excerpt={excerpt}
      href={filter.uri}
      cta="View Filter"
    />
  )
}
