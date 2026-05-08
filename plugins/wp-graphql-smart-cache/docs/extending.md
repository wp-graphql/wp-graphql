# Extending / Customizing WPGraphQL Smart Cache

In this document you will find information related to extending and custimizing the default behaviors of the WPGraphQL Smart Cache plugin.

- [Customizing the X-GraphQL-Keys Header](#customizing-the-x-graphql-keys-header)
- [Purging in response to a custom event](#purging-in-response-to-a-custom-event)
- [Listening for purge events (on-demand revalidation)](#listening-for-purge-events-on-demand-revalidation)

## Customizing the X-GraphQL-Keys Header

@todo


## Purging in response to a custom event

@todo — how to emit purge events for your own data. WPGraphQL Smart Cache ships listeners for core WordPress events (post saves, term changes, comment transitions, etc.) in [`src/Cache/Invalidation.php`](../src/Cache/Invalidation.php), but plugins with custom tables or custom data lifecycles need to dispatch their own purge events when their data changes. This section will document the public API for doing so (firing `graphql_purge` directly, the `purge_nodes()` helper, and conventions for `list:<type>` / `skipped:<type>` keys).


## Listening for purge events (on-demand revalidation)

WPGraphQL Smart Cache fires the `graphql_purge` action whenever a tracked event invalidates a cache entry. You can listen for this action to run your own logic — for example, sending a webhook to a static frontend so it can rebuild only the affected pages.

For a complete walk-through (including key formats, batching, authentication, and a Next.js example), see [On-Demand Revalidation](./on-demand-revalidation.md).
