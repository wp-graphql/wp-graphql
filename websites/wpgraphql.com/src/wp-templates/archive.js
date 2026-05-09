import gql from "graphql-tag"

import SiteLayout from "components/Site/SiteLayout"

import ExtensionPreview, {
  ExtensionFragment,
} from "components/Preview/ExtensionPreview"
import RecipePreview, {
  RecipePreviewFragment,
} from "components/Preview/RecipePreview"
import FilterPreview, {
  FilterPreviewFragment,
} from "components/Preview/FilterPreview"
import FunctionPreview, {
  FunctionPreviewFragment,
} from "components/Preview/FunctionPreview"
import ActionPreview, {
  ActionPreviewFragment,
} from "components/Preview/ActionPreview"

export default function Archive({ data }) {
  const { archive } = data
  return (
    <SiteLayout>
      <div className="mx-auto max-w-5xl px-6 pb-24 pt-16 sm:pt-20">
        <header className="mx-auto max-w-3xl text-center">
          <h1 className="text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
            {archive?.label ? archive.label : archive?.name ?? "Archive"}
          </h1>
          {archive?.description && (
            <div
              className="prose mx-auto mt-5 text-base leading-relaxed sm:text-lg"
              dangerouslySetInnerHTML={{ __html: archive.description }}
            />
          )}
        </header>
        <main className="mt-12 grid gap-5 sm:mt-16">
          {archive?.contentNodes?.nodes?.map((node) => {
            switch (node.__typename) {
              case "ExtensionPlugin":
                return <ExtensionPreview key={node.id} extension={node} />
              case "CodeSnippet":
                return <RecipePreview key={node.id} recipe={node} />
              case "Filter":
                return <FilterPreview key={node.id} filter={node} />
              case "Function":
                return <FunctionPreview key={node.id} node={node} />
              case "Action":
                return <ActionPreview key={node.id} node={node} />
              default:
                return (
                  <pre
                    key={node.id}
                    className="overflow-auto rounded-xl border border-border bg-card p-6 text-xs"
                  >
                    {JSON.stringify(node, null, 2)}
                  </pre>
                )
            }
          })}
        </main>
      </div>
    </SiteLayout>
  )
}

Archive.queries = {
  archive: {
    query: gql`
      query Archive_Node($uri: String!) {
        archive: nodeByUri(uri: $uri) {
          __typename
          id
          uri
          ... on ContentType {
            name
            description
            label
            contentNodes(first: 100) {
              nodes {
                __typename
                ...ExtensionPreview
                ...RecipePreview
                ...FilterPreview
                ...FunctionPreview
                ...ActionPreview
              }
            }
          }
          ... on TermNode {
            name
            description
            ... on CodeSnippetTag {
              contentNodes(first: 100) {
                nodes {
                  __typename
                  ...ExtensionPreview
                  ...RecipePreview
                  ...FilterPreview
                  ...FunctionPreview
                  ...ActionPreview
                }
              }
            }
          }
        }
      }
      ${ExtensionFragment}
      ${RecipePreviewFragment}
      ${FilterPreviewFragment}
      ${FunctionPreviewFragment}
      ${ActionPreviewFragment}
    `,
    variables: ({ seed }) => ({ uri: seed?.uri }),
  },
}
