---
uri: "/docs/wpgraphql-mutations/"
title: "Mutations"
---

WPGraphQL provides support for Mutations, or the ability to use GraphQL to create, update and delete data managed in WordPress. This page covers general concepts of how WPGraphQL implements mutations. This page will be most useful for developers that are already [familiar with GraphQL](/docs/intro-to-graphql/).

## Register a new Mutation

To register a mutation to the GraphQL Schema, you can use the `register_graphql_mutation` function [documented here](/functions/register_graphql_mutation/).
