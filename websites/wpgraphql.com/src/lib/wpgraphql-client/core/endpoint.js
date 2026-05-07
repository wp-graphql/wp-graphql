export function getGraphqlEndpoint() {
  const raw = process.env.NEXT_PUBLIC_WPGRAPHQL_URL || process.env.WPGRAPHQL_URL || ""
  const trimmed = raw.replace(/\/+$/, "")
  if (!trimmed) {
    throw new Error(
      "wpgraphql-client: NEXT_PUBLIC_WPGRAPHQL_URL or WPGRAPHQL_URL must be set"
    )
  }
  return trimmed
}
