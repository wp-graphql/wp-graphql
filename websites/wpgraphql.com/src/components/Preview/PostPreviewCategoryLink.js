import gql from "graphql-tag"
import Link from "next/link"

export const PostPreviewCategoryLinkFragment = gql`
  fragment PostPreviewCategoryLink on Category {
    id
    name
    uri
  }
`

export default function PostPreviewCategoryLink({ category }) {
  return (
    <Link href={category.uri} legacyBehavior>
      <a className="mr-3 font-mono text-xs font-medium uppercase tracking-widest text-primary hover:text-orange-wpg-200">
        {category.name}
      </a>
    </Link>
  )
}
