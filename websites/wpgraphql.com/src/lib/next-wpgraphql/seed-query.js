export const SEED_QUERY = /* GraphQL */ `
  query NextWpGraphQLSeed($uri: String!) {
    node: nodeByUri(uri: $uri) {
      __typename
      id
      uri
      ... on ContentNode {
        databaseId
        slug
        isPreview
        isRestricted
        contentType {
          node {
            name
            graphqlSingleName
          }
        }
        ... on NodeWithTitle {
          title
        }
      }
      ... on TermNode {
        slug
        taxonomyName
      }
      ... on User {
        slug
      }
      ... on ContentType {
        name
        label
        graphqlSingleName
      }
    }
    generalSettings {
      title
      description
    }
  }
`

function pickPostType(node) {
  if (!node) return null
  if (node.__typename === "ContentType") {
    return node.graphqlSingleName ?? node.name ?? null
  }
  return node.contentType?.node?.graphqlSingleName ?? node.contentType?.node?.name ?? null
}

function isHomeUri(uri) {
  if (!uri) return true
  return uri === "/" || uri === ""
}

export function normalizeSeed(response, uri) {
  const node = response?.data?.node ?? response?.node ?? null
  const generalSettings = response?.data?.generalSettings ?? response?.generalSettings ?? null

  const typename = node?.__typename ?? null
  const slug = node?.slug ?? null
  const postType = pickPostType(node)
  const taxonomy = node?.taxonomyName ?? null

  return {
    uri: uri ?? node?.uri ?? null,
    node,
    typename,
    id: node?.id ?? null,
    slug,
    postType,
    taxonomy,
    isFrontPage: isHomeUri(uri),
    isPostsPage: false,
    generalSettings,
  }
}
