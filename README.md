# WPGraphQL for Advanced Custom Fields

![WPGraphQL for ACF plugin banner](/.wordpress-org/banner-1544x500.png)

WPGraphQL for Advanced Custom Fields is a free, open-source WordPress plugin that adds ACF Fields and Field Groups to the WPGraphQL Schema.

The plugin is [now available on WordPress.org!](https://wordpress.org/plugins/wpgraphql-acf/)

Development of this plugin has been made possible by [WP Engine Atlas](https://wpengine.com/atlas)

Learn more at [https://acf.wpgraphql.com](https://acf.wpgraphql.com)

## Table of Contents

- [Upgrade Notice](#upgrade-notice)
- [Plugin Overview](#plugin-overview)
- [Requirements](#requirements)
- [Installation and Activation](#installation-and-activation)
- [How WPGraphQL for ACF maps Field Groups to the Schema](#how-wpgraphql-for-acf-maps-field-groups-to-the-schema)
- [ACF Post Type and Taxonomy Support](#acf-post-type-and-taxonomy-support)
- [FAQs](#faqs)
- [Support](#support)
- [Privacy Policy](#privacy-policy)

## Upgrade Notice

If you are updating from WPGraphQL for ACF v0.6.* or older, check out the [Upgrade Guide](https://acf.wpgraphql.com/upgrade-guide).

## Plugin Overview

In short, WPGraphQL for ACF extends the WPGraphQL Schema by adding GraphQL Types, GraphQL Interfaces
and GraphQL fields for accessing data managed by Advanced Custom Fields.

WPGraphQL for ACF supports the built-in field types in
[Advanced Custom Fields](https://www.advancedcustomfields.com/) free and ACF PRO, as well as
[ACF Extended](https://www.acf-extended.com/) Free and Pro.

This plugin extends the ACF Admin UIs to provide settings for adding ACF Post Types, Taxonomies,
Field Groups and Fields to the WPGraphQL Schema. For users that register ACF Field Groups and Fields
using PHP or JSON, you can control how they're mapped to the GraphQL Schema at that level as well.

Under there are various APIs for determining how each ACF Field Type is mapped to the WPGraphQL '
Schema, and resolved when queried for in a GraphQL Query.

The plugin provides hooks and filters, allowing developers to customize the experience and override
default logic.

## Requirements

- [WPGraphQL](https://wordpress.org/plugins/wp-graphql/) (latest version recommended)
- [Advanced Custom Fields](https://advancedcustomfields.com) Free or PRO (latest version recommended)
- [ACF Extended Free or PRO](https://advancedcustomfields.com) (optional)
- WordPress 6.1+
- PHP 7.4+

## Installation and Activation

See our [Installation and Activation](https://acf.wpgraphql.com/installation-and-activation/) guide.

## How WPGraphQL for ACF maps Field Groups to the Schema

Each ACF Field Group is mapped to the GraphQL Schema with a GraphQL Interface Type and a GraphQL Object Type to represent the field group.

The Interface Type is added to the Schema with all the fields of the Field Group that are set to "Show in GraphQL", and that Interface Type is implemented by the Object Type.

When the Field Group is assigned to a location (i.e. "Post Type is equal to Post"), WPGraphQL will attempt to determine how to properly show the Field Group in the Schema based on the Location Rules. (There's admin UI settings to override the GraphQL Types a field group should show on, and this can be done in PHP/JSON registered field groups as well)

Each GraphQL Type that is connected to an ACF Field Group gets an interface applied to it with the name of "WithACf${GraphQLTypeName}"

To show how this works, let's start with a basic example:

Let's say we have an ACF Field Group named "My Field Group" that has a "text" field named "text", and has location rules set to show on the "Post" post type.

With WPGraphQL for ACF active, we can set the field group to "Show in GraphQL" in the Field Group's settings and set the "GraphQL Type Name" to "MyFieldGroup".

This will result in the following changes to the GraphQL Schema:

- A GraphQL Interface Type named "MyFieldGroup_Fields" will be registered to the Schema
  - This Interface Type will have a "text" field of the "String" type, representing the "text" field on the ACF Field Group
- A GraphQL Object Type named "MyFieldGroup" will be registered to the Schema
  - This Object Type will implement the "MyFieldGroup_Fields" Interface
  - This Object Type will implement the "AcfFieldGroup" interface (all ACF Field Groups implement this Interface)
- An Interface `WithAcfMyFieldGroup` will be added to the Schema
  - This interface will have a field `myFieldGroup` which resolves to the Type `MyFieldGroup`
- The "Post" Type will now implement the `WithAcfMyFieldGroup` interface
  - This is determined by the Location Rules on the field group. Since this field group was assigned to the "Post" post type, the "Post" Type implements the interface.

Now, we can write a fragment like so:

```graphql
fragment MyFieldGroup on WithAcfMyFieldGroup {
  myFieldGroup {
    text
  }
}
```

And we can use this fragment in various queries, for example:

```graphql
{
  nodeByUri(uri:"/my-test-page") {
    __typename
    id
    uri
    ...MyFieldGroup
  }
}
```

## ACF Post Type and Taxonomy Support

Advanced Custom Fields v6.1 added support for registering Post Types and Taxonomies from the WordPress dashboard.

WPGraphQL for ACF allow you to configure the Custom Post Types and Taxonomies to Show in GraphQL and allows you to customize the GraphQL Single Name and GraphQL Plural Name.

## FAQs

### Does this work with ACF Extended?

Yes! WPGraphQL for ACF allows you to query for (most) fields created with ACF Extended.

### Can I filter and sort queries by ACF Fields using WPGraphQL for ACF?

At this time WPGraphQL for ACF does not support filtering or sorting queries by ACF Fields. "Meta Queries" are often very expensive to execute, so we currently do not support filtering by ACF fields out of the box, but are exploring options for supporting it without the performance penalty.

### I think I found a bug, where do I report it?

If you think you found a bug, please open an issue on [GitHub](https://github.com/wp-graphql/wpgraphql-acf). The more details you provide in the issue, and the more clear your steps to reproduce are, the higher chances we will be able to help.

### Can I use ACF Free or Pro with WPGraphQL for ACF?

Yes! WPGraphQL for ACF works great with ACF Free and Pro. The Pro version of ACF has some additional features, such as Flexible Content Fields, Repeater Fields and Options Pages that are supported by WPGraphQL for ACF.

### Do I have to use Faust.js to use WPGraphQL for ACF?

No! While [wpgraphql.com](https://www.wpgraphql.com) and [acf.wpgraphql.com](https://acf.wpgraphql.com) are built using [Faust.js](https://faustjs.org/) and Next.js, you can use WPGraphQL for ACF with any GraphQL client, including Apollo, Relay, Urql, etc.

### I have an ACF Extension that adds a new field type, will it work with WPGraphQL for ACF?

WPGraphQL for ACF supports the field types that come with ACF (Free and PRO) as well as the field types in ACF Extended (Free and PRO). Support for additional field types can be added by using the "register_graphql_acf_field_type" API.

### Do I need WPGraphQL and ACF to be active to use this?

This plugin is a "bridge" plugin that brings functionality of ACF to WPGraphQL. Both WPGraphQL and ACF need to be installed and active in your WordPress installation for this plugin to work.

### How much does WPGraphQL for ACF cost?

WPGraphQL for ACF is a FREE open-source plugin. The development is sponsored by [WP Engine Atlas](https://wpengine.com/atlas).

### Does WPGraphQL for ACF support GraphQL Mutations?

GraphQL Mutations are not yet supported. We are working on adding support for Mutations in the future. We are waiting for the GraphQL "@oneOf" directive to be merged into the GraphQL spec before we add support for Mutations.

### Does this work with Field Groups registered in PHP or JSON?

Yes! You can register ACF Field Groups and Fields using the Admin UI, PHP or JSON. WPGraphQL for ACF will detect the Field Groups and Fields and add them to the GraphQL Schema. If using PHP or JSON, you will need to set the "show_in_graphql" setting to "true" to expose the Field Group and Fields to the GraphQL Schema. There might be other settings that need attention at the field group or field level that might impact the schema or field resolution.

## Support

- [General Help Requests](https://github.com/wp-graphql/wp-graphql/discussions): For general questions and help requests, create a new topic in Github Discussions
- [Discord Community](https://www.wpgraphql.com/discord): The WPGraphQL Discord is a great place to communicate in real-time. Ask questions, discuss features, get to know other folks using WPGraphQL.
- [Bug Reports](https://github.com/wp-graphql/wp-graphql/issues/new?assignees=&labels=&projects=&template=bug_report.yml): Report a bug in WPGraphQL
- [Feature Requests](https://github.com/wp-graphql/wp-graphql/issues/new?assignees=&labels=&projects=&template=feature_request.yml): Suggest an idea, feature, or enhancement for WPGraphQL.
- [Report a Security Vulnerability](https://github.com/wp-graphql/wp-graphql/security/advisories/new): Report a security vulnerability.

## Privacy Policy

WPGraphQL for Advanced Custom Fields uses [Appsero](https://appsero.com) SDK to collect some telemetry data upon user's confirmation. This helps us to troubleshoot problems faster & make product improvements.

Appsero SDK **does not gather any data by default.** The SDK only starts gathering basic telemetry data **when a user allows it via the admin notice**. We collect the data to ensure a great user experience for all our users.

Integrating Appsero SDK **DOES NOT IMMEDIATELY** start gathering data, **without confirmation from users in any case.**

Learn more about how [Appsero collects and uses this data](https://appsero.com/privacy-policy/).
