import gql from "graphql-tag"
import PreviewCard from "./PreviewCard"

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
    <PreviewCard
      title={node.title}
      excerpt={excerpt}
      href={node.uri}
      cta="View Action"
    />
  )
}
