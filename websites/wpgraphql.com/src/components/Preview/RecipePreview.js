import gql from "graphql-tag"
import PreviewCard from "./PreviewCard"

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
    <PreviewCard
      title={recipe.title}
      excerpt={excerpt}
      href={recipe.uri}
      cta="View Recipe"
    />
  )
}
