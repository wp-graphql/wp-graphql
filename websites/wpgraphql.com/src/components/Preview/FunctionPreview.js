import gql from "graphql-tag"
import PreviewCard from "./PreviewCard"

export const FunctionPreviewFragment = gql`
  fragment FunctionPreview on Function {
    id
    title
    content
    uri
  }
`

export default function FunctionPreview({ node }) {
  const paragraphs = node?.content ? node.content.split("</p>") : null
  const excerpt = paragraphs ? paragraphs[0] + "</p>" : null

  return (
    <PreviewCard
      title={node.title}
      excerpt={excerpt}
      href={node.uri}
      cta="View Function"
    />
  )
}
