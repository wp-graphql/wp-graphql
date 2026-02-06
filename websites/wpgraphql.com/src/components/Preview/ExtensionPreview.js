import { gql } from "@apollo/client"
import { ArrowTopRightOnSquareIcon } from "@heroicons/react/24/outline"

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
    <div className="mb-10 pt-10">
      <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
        {extension.title}
      </h2>
      <div className="py-5">
        <div
          className="prose dark:prose-dark"
          dangerouslySetInnerHTML={{ __html: extension?.content }}
        />
      </div>
      <div className="text-base font-medium leading-6">
        <a
          href={extension.extensionFields.pluginLink}
          target="_blank"
          rel="noreferrer"
          className="btn-primary-sm"
        >
          <span className="pr-2">View Extension</span>
          <ArrowTopRightOnSquareIcon className="h-4 w-4" />
        </a>
      </div>
    </div>
  )
}
