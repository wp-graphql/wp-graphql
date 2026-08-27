---
uri: "/querying-acf-fields-with-graphql/"
title: "Querying ACF Fields with GraphQL"
---

Once you have created ACF Field Groups and configured them to show in GraphQL, it’s time to query for the fields using GraphQL!  
  
In this document, we’ll cover how ACF Field Groups are mapped to the Schema, how you can use tools like the GraphiQL IDE to understand how you can access the data stored in your ACF Fields using GraphQL Queries and Fragments.

For consistency, we will be showing examples and referencing the ACF Field Groups documented on the “[Kitchen Sink”](/kitchen-sink/) page. You should be able to translate that and apply the knowledge to your own ACF Field Groups and Fields.

## Understanding how ACF Field Groups map to the GraphQL Schema

Below we will break down how ACF Field Groups map to the GraphQL Schema

### GraphQL Object Types

Each ACF Field Group that is set to “show\_in\_graphql” will be represented in the GraphQL Schema as a GraphQL Object Type with fields matching the fields of the ACF Field Group.

**NOTE**: Even ACF Field groups marked as “inactive” but set to “Show in GraphQL” will be added to the Schema.

The name of the GraphQL Object Type is determined by the ACF Field Group’s “graphql\_type\_name” setting.

### GraphQL Interfaces

Each ACF Field Group will implement the “AcfFieldGroup” Interface.

To see all of the GraphQL Object Types that represent ACF Field Groups, we can use the GraphiQL IDE to search for “AcfFieldGroup”.

### ACF Field Types
