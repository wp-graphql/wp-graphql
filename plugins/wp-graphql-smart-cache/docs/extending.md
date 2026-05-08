# Extending / Customizing WPGraphQL Smart Cache

In this document you will find information related to extending and custimizing the default behaviors of the WPGraphQL Smart Cache plugin.

- [Customizing the X-GraphQL-Keys Header](#customizing-the-x-graphql-keys-header)
- [Purging in response to a custom event](#purging-in-response-to-a-custom-event)

## Customizing the X-GraphQL-Keys Header

@todo


## Purging in response to a custom event

WPGraphQL Smart Cache fires the `graphql_purge` action whenever a tracked event invalidates a cache entry. You can listen for this action to run your own logic — for example, sending a webhook to a static frontend so it can rebuild only the affected pages.

For a complete walk-through (including key formats, batching, authentication, and a Next.js example), see [On-Demand Revalidation](./on-demand-revalidation.md).
