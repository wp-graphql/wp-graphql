# Testing

Unit tests use `node:test` (Node 22+ built-in). No Jest, Vitest, or other test framework needed.

## Running tests

```sh
npm run test:unit
```

This runs `node --test src/lib/wpgraphql-client/__tests__` from the website workspace.

## Coverage

| file | what it covers |
|---|---|
| `endpoint.test.js` | env-var precedence, trimming, missing-var error |
| `print.test.js` | AST printing, parse + print round-trip, `getOperation` for query / mutation / subscription |
| `hash.test.js` | known-vector SHA-256 digests, determinism, error on non-string input |
| `client.test.js` | GET URL shape, omitted-when-empty variables, operationName encoding, APQ retry, non-APQ errors pass through, mutation always POSTs, URL-length guard |
| `hierarchy.test.js` | every hierarchy slot, slug/postType/taxonomy normalization, deduplication, registry fallthrough, smoke test against the live wpgraphql.com registry shape |
| `seed-query.test.js` | response → normalized seed for Page / Post / Category / ContentType / front-page / missing-node |
| `resolve-template.test.js` | happy path, notFound, front-page bypass, input validation |
| `get-template-static-props.test.js` | Next.js adapter happy path, notFound + revalidate, skip predicates |
| `templates.test.js` | registry configure / getRegistry / re-configure |

Total: 75 tests at the time of writing.

## File layout

```
wpgraphql-client/
  index.js                      aggregate public API

  core/
    index.js                    re-exports core surface
    client.js                   fetch client
    endpoint.js                 env-var endpoint resolver
    hash.js                     sha256 (Web Crypto)
    print.js                    gql AST → string + getOperation
    seed-query.js               SEED_QUERY + normalizeSeed
    hierarchy.js                pure resolveTemplateName / buildCandidateNames
    templates.js                configure / getRegistry
    run-queries.js              parallel runner used by both
                                resolveTemplate and getLayoutData
    resolve-template.js         framework-agnostic orchestrator
    get-layout-data.js          run-only-Layout-queries helper

  react/
    index.js                    re-exports
    layout.js                   LayoutProvider / useLayoutData

  next/
    index.js                    re-exports
    get-template-static-props.js
                                Next.js getStaticProps wrapper around
                                core/resolveTemplate

  package.json                  type:module so node:test sees ESM
  __tests__/                    node:test unit tests
  docs/                         topic-specific documentation
```

## Requirements

- **Core**: Node 22+ (Web Crypto + `node:test`), or any modern runtime with `fetch` and `globalThis.crypto.subtle`. Peer deps: `graphql`, `graphql-tag`.
- **React adapter**: React 18+.
- **Next.js adapter**: Next.js 15+ (`pages/` router).
- A WPGraphQL endpoint, ideally with WPGraphQL Smart Cache for the GET caching benefit.
