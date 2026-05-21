import gql from "graphql-tag"
import PreviewCard from "./PreviewCard"

export const ExtensionFragment = gql`
  fragment ExtensionPreview on ExtensionPlugin {
    id
    title
    uri
    content
    extensionFields {
      pluginHost
      pluginLink
      pluginReadmeLink
    }
  }
`

export default function ExtensionPreview({ extension }) {
  return (
    <PreviewCard
      title={extension.title}
      excerpt={extension?.content}
      href={extension.extensionFields?.pluginLink}
      cta="View Extension"
      external
    />
  )
}
