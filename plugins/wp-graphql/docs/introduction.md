---
uri: "/docs/introduction/"
title: "Introduction"
---

WPGraphQL is a free, open-source WordPress plugin that provides an extendable [GraphQL](/glossary/graphql/) schema and [API](/glossary/api/) for any [WordPress](/docs/intro-to-wordpress/) site.

## First Steps

Are you new to using GraphQL with WordPress? This is the place to start!

-   **[Quick Start](/docs/quick-start/)**: Get started using WPGraphQL in 5-minutes.

-   **Beginner Guides**: These guides will help you learn some of the basics and set you up with a good foundation to dive into the more advanced concepts when you're ready.

    - **[Intro to GraphQL](/docs/intro-to-graphql/)**: New to GraphQL? This guide covers basic concepts of using GraphQL and includes resources to learn even more.
    - **[Intro to WordPress](/docs/intro-to-wordpress/)**: New to WordPress? This guide will help you understand some basics of WordPress, how to use it as a CMS and how to extend it as a Developer.
    - **[Interacting with WPGraphQL](/docs/interacting-with-wpgraphql/)**: This guide covers some of the basics for interacting with WPGraphQL using tooling such as GraphiQL and fetch requests from the browser.
    - **[Build your first WPGraphQL Extension](/docs/build-your-first-wpgraphql-extension/)**: This guide covers some of the APIs WPGraphQL makes available to plugin developers to extend the WPGraphQL Schema. This is great for developers of any level that want to start building WPGraphQL Extensions.
    - **[WPGraphQL vs. WP REST API](/docs/wpgraphql-vs-wp-rest-api/)**: Learn about the similarities and differences between WPGraphQL and the WordPress REST API

-   **Using WPGraphQL**: Learn how to use the GraphQL query language to interact data managed in WordPress.

    - **[Posts and Pages](/docs/posts-and-pages/)**
    - **[Custom Post Types](/docs/custom-post-types/)**
    - **[Categories and Tags](/docs/categories-and-tags/)**
    - **[Custom Taxonomies](/docs/custom-taxonomies/)**
    - **[Media](/docs/media/)**
    - **[Menus](/docs/menus/)**
    - **[Settings](/docs/settings/)**
    - **[Users](/docs/users/)**
    - **[Comments](/docs/comments/)**
    - **[Plugins](/docs/plugins/)**
    - **[Themes](/actions/graphql_init/)**
    - **[Assets](/docs/assets/)**
    - **[Widgets](/docs/widgets/)**

## Dig Deeper

Once you have the basics of WordPress and GraphQL down, you can dig deeper and learn more.

-   **Advanced Guides**: The following guides dive deep into specific concepts, philosophies and technical implementations of WPGraphQL.
    - **[Default Types and Fields](/docs/default-types-and-fields/)**: Learn about the default Types and Fields that WPGraphQL adds to the Schema based on registered post types, taxonomies and more.
    - **[WPGraphQL Request Lifecycle](/docs/wpgraphql-request-lifecycle/)**: Learn about how WPGraphQL executes queries and mutations and what happens under the hood.
    - **[Global ID](/docs/global-id/)**: Learn about WPGraphQL's use of a global ID for nodes
    - **[Connections](/docs/connections/)**: Edges? Nodes? Pagination? Huh? This guide will help you understand GraphQL connections and how/why WPGraphQL makes use of them.
    - **[Interfaces](/docs/interfaces/)**: WPGraphQL has many Interfaces that unlock some amazing potential. Learn more about what Interfaces are and specifically about how WPGraphQL makes use of them and how you can benefit from using them.
    - **[Performance](/docs/performance/)**: Learn how WPGraphQL works to keep execution fast and options for making things/keeping things fast when working with WPGraphQL.
    - **[Security](/docs/security/)**: Learn how WPGraphQL works to keep your data secure and what you can do to keep things secure as you extend WPGraphQL with your own custom data.
    - **[Authentication and Authorization](/docs/authentication-and-authorization/)**: Learn about Authentication and Authorization with WordPress and how they work with WPGraphQL.
    - **[Hierarchical Data](/docs/hierarchical-data/)**: Learn about how to use WPGraphQL to interact with Hierarchical data, such as Nav Menus, hierarchical Post Types and hierarchical Taxonomies
    - **[GraphQL Resolvers](/docs/graphql-resolvers/)**: Learn about how GraphQL resolvers work in WPGraphQL and how to override their behavior.
    - **[Debugging](debugging)**: Learn common debugging techniques

-   **[Community](/community/)**: Connect with the community using WPGraphQL and get inspired by seeing how WPGraphQL is being used in the wild.

## Developer Reference

This section contains technical reference for APIs and other aspects of WPGraphQL. They describe how it works and how to use it but assume you have a basic understanding of key concepts.

- **[Actions](/actions)**: Actions are a type of WordPress "hook" that execute at certain points of a request. Learn about the actions WPGraphQL provides for developers to hook into.
- **[Filters](/filters)**: Filters are a type of WordPress "hook" that allow developers to modify data during execution. Learn about the filters WPGraphQL provides for developers to hook into.
- **[Functions](/functions)**: WPGraphQL provides many API functions for customizing the GraphQL Schema. Learn more about the available functions and how to use them to customize WPGraphQL.
- **[Recipes](/recipes)**: Recipes are bite-size morsels (aka code snippets) that can help satisfy your craving for productivity boosts when working with WPGraphQL.
