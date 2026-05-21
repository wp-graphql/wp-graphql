# Transport

The library's `request()` function is the only place that talks to the network. Templates, layout queries, the seed query, and arbitrary calls from custom pages all flow through it.

## `request({ query, variables, operationName, endpoint })`

Behavior:

1. Print the query to a stable string (via `graphql/language/printer`).
2. Compute `queryId = sha256(printed)`.
3. If the parsed AST is a mutation → POST `{ query, variables, operationName }`. Done.
4. Otherwise build `GET ${endpoint}?queryId=${queryId}&variables=${enc}&operationName=...`.
5. If the GET URL would exceed 6000 chars, fall back to POST with `{ queryId, query, variables }` (correct, but not network-cacheable).
6. Send the GET. If the response contains a GraphQL error matching `PersistedQueryNotFound`, retry as POST with both `queryId` and `query` so the server registers the document. Subsequent GETs hit.

`setFetch(fn)` is provided for tests; the default uses `globalThis.fetch`.

## Why GET + `queryId`?

[WPGraphQL Smart Cache](https://github.com/wp-graphql/wp-graphql-smart-cache) (and any HTTP cache in front of WPGraphQL) caches GET requests by URL. Sending the full query string in every GET is fine for small queries but quickly hits URL length limits.

Persisted-query IDs solve this:

- Hash the query once (sha256 of the printed AST).
- Send only the hash on subsequent requests: `GET /graphql?queryId=<hash>&variables=...`.
- Smart Cache looks up the hash → known query → executes and caches by URL.

The first request for a new hash returns `PersistedQueryNotFound`. The client transparently retries as POST with both `queryId` and `query`; Smart Cache stores the mapping and the next GET hits.

## Endpoint resolution

`getGraphqlEndpoint()` reads:

1. `NEXT_PUBLIC_WPGRAPHQL_URL` (preferred — available to the client bundle if needed)
2. `WPGRAPHQL_URL` (server-only fallback)

Trailing slashes are trimmed. If neither is set, the function throws.

`request()` calls `getGraphqlEndpoint()` automatically. Pass an explicit `endpoint` argument to override (mostly for tests).

## Mutations

Mutations always POST with the full query, never GET. The library detects mutations by parsing the document and checking `OperationDefinition.operation === "mutation"`.

## URL-length guard

If the GET URL (endpoint + queryId + JSON-encoded variables + operationName) would exceed 6000 bytes, the client falls back to POST with the queryId. This is correct but not network-cacheable. In practice this only triggers for queries with very large variable payloads (e.g. multi-kilobyte search filters); most queries fit comfortably under the limit because the `queryId` replaces what would otherwise be a long query string.

## Seed query

Before resolving a template, `resolveTemplate()` runs `SEED_QUERY` against WPGraphQL to identify the node behind the URI:

```graphql
query WpGraphQLClientSeed($uri: String!) {
  node: nodeByUri(uri: $uri) {
    __typename id uri
    ... on ContentNode { databaseId slug isPreview isRestricted
      contentType { node { name graphqlSingleName } }
      ... on NodeWithTitle { title } }
    ... on TermNode { slug taxonomyName }
    ... on User { slug }
    ... on ContentType { name label graphqlSingleName }
  }
  generalSettings { title description }
}
```

`normalizeSeed(response, uri)` flattens the response into the shape the [hierarchy resolver](./hierarchy.md) expects:

```ts
{
  uri:             string | null,
  node:            object | null,
  typename:        string | null,
  id:              string | null,
  slug:            string | null,
  postType:        string | null,
  taxonomy:        string | null,
  isFrontPage:     boolean,
  isPostsPage:     boolean,
  generalSettings: object | null,
}
```

The seed query is itself sent as a GET-with-queryId, so it benefits from the same caching as template queries.

## Server-only by design

The library never exports a `useQuery` hook. Templates and components consume data from `data` props or `useLayoutData()` — both already populated server-side. To enforce this in CI / code review:

- Templates and components don't import `request()` from a `useEffect` (verifiable with grep)
- The 6000-byte URL guard prevents oversized GETs
- All queries run from `getStaticProps` (or `getServerSideProps` / API routes) — never from `useEffect`

This is a design choice, not a Smart Cache requirement: client-side queries would still cache via Smart Cache. Going server-only means:

- Faster first paint (no waterfall)
- No client-side GraphQL bundle
- Simpler mental model — pages either have their data or they 404
