import { gql } from "@apollo/client"
import Link from "next/link"

export const RecipePreviewFragment = gql`
  fragment RecipePreview on CodeSnippet {
    id
    title
    content
    uri
  }
`

export default function RecipePreview({ recipe }) {
  const paragraphs = recipe?.content ? recipe.content.split("</p>") : null
  const excerpt = paragraphs ? paragraphs[0] + "</p>" : null

  return (
    <div className="mb-10 pt-10">
      <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
        {recipe.title}
      </h2>
      <div className="py-5">
        <div
          className="prose dark:prose-dark"
          dangerouslySetInnerHTML={{ __html: excerpt }}
        />
      </div>

      <div className="text-base font-medium leading-6">
        <Link href={recipe.uri}>
          <a className="btn-primary-sm">
            <span className="pr-2">View Recipe →</span>
          </a>
        </Link>
      </div>
    </div>
  )
}
