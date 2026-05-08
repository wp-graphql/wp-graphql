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
      <div className="overflow-hidden">
        <div className="mx-auto mt-10 px-4 pb-6 sm:mt-16 sm:px-6 md:px-8 xl:px-12 xl:max-w-4xl">
          <header className="text-center">
            <h1 className="mb-6 text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
              {archive?.label ? archive.label : archive?.name ?? "Archive"}
            </h1>
            <p className="prose mx-auto max-w-3xl text-lg leading-7">
              <span
                dangerouslySetInnerHTML={{ __html: archive?.description }}
              />
            </p>
          </header>
          <main className="content relative pt-10 max-w-3xl mx-auto mb-10">
            <ul className="divide-y divide-border">
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
                      <li key={node.id} className="py-12">
                        <pre>{JSON.stringify(node, null, 2)}</pre>
                      </li>
                    )
                }
              })}
            </ul>
          </main>
        </div>
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
