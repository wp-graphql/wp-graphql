---
uri: "/docs/scripts-and-styles/"
title: "Scripts and Styles"
---

This page will be most useful for users who are familiar with [GraphQL Concepts](/docs/intro-to-graphql/) and understand the basics of [writing GraphQL Queries](/docs/intro-to-graphql/#queries-and-mutation).

WordPress themes and plugins register JavaScript and CSS files ("assets") and enqueue the ones a page needs. WPGraphQL exposes these assets so headless front-ends can load the same scripts and stylesheets WordPress would have printed.

## Where Assets Live in the Schema

| Field | Available on | Returns |
| --- | --- | --- |
| `enqueuedScripts` | `ContentNode` (posts, pages, custom post types), `TermNode` (categories, tags, custom taxonomies), `User` | `EnqueuedScript` nodes |
| `enqueuedStylesheets` | `ContentNode`, `TermNode`, `User` | `EnqueuedStylesheet` nodes |
| `registeredScripts` | `RootQuery` | `EnqueuedScript` nodes |
| `registeredStylesheets` | `RootQuery` | `EnqueuedStylesheet` nodes |

Both `EnqueuedScript` and `EnqueuedStylesheet` implement the `EnqueuedAsset` interface, which provides fields such as `handle`, `src`, `version`, `dependencies`, `before` and `after` (inline code to output around the asset), and `group`.

`EnqueuedScript` adds fields such as `strategy` (`ASYNC` or `DEFER`), `groupLocation` (`HEADER` or `FOOTER`) and `extraData`. `EnqueuedStylesheet` adds fields such as `media`, `isRtl`, `path` and `title`.

## Enqueued vs Registered Assets

- **Enqueued** assets (`enqueuedScripts` / `enqueuedStylesheets`) are resolved per node. WPGraphQL sets up the request context for the post, term or user being queried and runs the `wp_enqueue_scripts` action, so the result reflects what themes and plugins enqueue for that specific page. Two posts can return different assets if a plugin only enqueues something on certain content.
- **Registered** assets (`registeredScripts` / `registeredStylesheets`) are site wide. They return every script or stylesheet registered with WordPress, whether or not it is enqueued on any particular page.

Use the enqueued connections to render a specific page. Use the registered connections when you need to look up assets independent of any page, for example to build an asset manifest.

The enqueued connections return the handles that were enqueued directly. Assets those handles depend on are available through each asset's `dependencies` field, so a client should load dependencies before the asset itself.

## Querying Assets for a Post or Page

```graphql
{
  post(id: "hello-world", idType: SLUG) {
    title
    enqueuedScripts(first: 100) {
      nodes {
        handle
        src
        version
        strategy
        groupLocation
        dependencies {
          handle
          src
        }
      }
    }
    enqueuedStylesheets(first: 100) {
      nodes {
        handle
        src
        version
        media
      }
    }
  }
}
```

The same fields are available on pages and custom post types:

```graphql
{
  page(id: "/about/", idType: URI) {
    title
    enqueuedStylesheets {
      nodes {
        handle
        src
      }
    }
  }
}
```

Or on any node fetched by URI, using the `ContentNode` interface:

```graphql
{
  nodeByUri(uri: "/about/") {
    ... on ContentNode {
      enqueuedScripts {
        nodes {
          handle
          src
        }
      }
    }
  }
}
```

## Querying Assets for a Term

Categories, tags and custom taxonomy terms implement `TermNode`, so they expose the same connections:

```graphql
{
  category(id: "news", idType: SLUG) {
    name
    enqueuedScripts {
      nodes {
        handle
        src
      }
    }
    enqueuedStylesheets {
      nodes {
        handle
        src
      }
    }
  }
}
```

## Querying Registered Assets

```graphql
{
  registeredScripts(first: 100) {
    nodes {
      handle
      src
      version
    }
  }
  registeredStylesheets(first: 100) {
    nodes {
      handle
      src
      version
    }
  }
}
```

## Filtering by Handle

All four connections accept a `handlesIn` argument in `where` that limits the results to assets with the given handles:

```graphql
{
  registeredScripts(where: { handlesIn: ["jquery-core", "wp-api-fetch"] }) {
    nodes {
      handle
      src
    }
  }
}
```

It works the same way on the per-node connections:

```graphql
{
  post(id: "hello-world", idType: SLUG) {
    enqueuedStylesheets(where: { handlesIn: ["wp-block-library"] }) {
      nodes {
        handle
        src
      }
    }
  }
}
```

A few details about how `handlesIn` behaves:

- Handles that don't match an asset in the connection are ignored. On the enqueued connections, a handle that is registered but not enqueued for that node won't be returned.
- Omitting `handlesIn`, or passing `null`, leaves the connection unfiltered.
- Passing an empty list (`handlesIn: []`) matches no assets and returns an empty connection.

The difference between the last two matters when the list of handles comes from a variable. If your client builds the list dynamically and it can end up empty, `handlesIn: []` returns nothing rather than every asset.

```graphql
query GetScripts($handles: [String]) {
  registeredScripts(where: { handlesIn: $handles }) {
    nodes {
      handle
    }
  }
}
```

| Variables | Result |
| --- | --- |
| `{ "handles": ["jquery-core"] }` | Only `jquery-core` |
| `{ "handles": [] }` | No assets |
| `{}` or `{ "handles": null }` | All registered scripts |

## Mutations

> WPGraphQL does not support mutations for scripts or styles. Assets are registered and enqueued in code by themes and plugins.
