---
uri: "/docs/themes/"
title: "Themes"
---

This page will be most useful for users what are familiar with [GraphQL Concepts](/docs/intro-to-graphql/) and understand the basics of [writing GraphQL Queries](/docs/intro-to-graphql/#queries-and-mutation).

## Querying Themes

WPGraphQL provides support for querying Themes in various ways.

### List of Themes

Below is an example of querying a list of Themes.

```graphql
{
  themes {
    nodes {
      id
      name
      version
    }
  }
}
```

#### Query from Authenticated User

Authenticated users with the "edit\_themes" capability can query a list of Themes and see all available themes for the site.

![Screenshot of a Query for a list of themes from an authenticated user](./images/themes-authenticated-user.png)

#### Query from Public User

A public user or a user without "edit\_themes" capability can make the same query and only the active theme will be returned.

The active theme is considered a public entity in WordPress and can be publicly accessed and WPGraphQL respects this access control right.

![Screenshot of a Query for a list of themes from a non-authenticated user](./images/themes-not-authenticated-user.png)

## Mutations

> WPGraphQL does not currently support mutations for themes.
