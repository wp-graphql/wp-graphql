=== WPGraphQL for ACF ===
Contributors: jasonbahl
Tags: GraphQL, ACF, API, NextJS, Headless
Requires at least: 5.9
Tested up to: 6.5
Requires PHP: 7.3
Stable Tag: 2.4.1
License: GPL-3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WPGraphQL for ACF seamlessly integrates Advanced Custom Fields with WPGraphQL.

=== Description ===

WPGraphQL for Advanced Custom Fields is a free, open-source WordPress plugin that adds ACF Fields and Field Groups to the WPGraphQL Schema.

= Create ACF Field Groups =

Create ACF Field Groups and Fields using the ACF User Interface, register them with PHP, or leverage ACF local JSON. Each field group and the fields within it can be configured to "Show in GraphQL."

= Query your fields with GraphQL =

Once your field groups and fields are configured to "Show in GraphQL," they become available in the GraphQL Schema for querying.

= Supported Field Types =

WPGraphQL for ACF provides support for most built-in field types of ACF (free & PRO) and extends support to most field types from ACF Extended (free & PRO).

== Updating ==

If you are updating from WPGraphQL for ACF v0.6.* or older, check out the [Upgrade Guide](https://acf.wpgraphql.com/upgrade-guide)

For non-major version updates, automatic updates usually should work smoothly, but we still recommend you back up your site and test on a staging site.

Before updating WPGraphQL for ACF, review the release notes on [GitHub](https://github.com/wp-graphql/wpgraphql-acf/releases).

We follow Semantic Versioning (Semver) for meaningful releases:

- *MAJOR* version for incompatible API changes,
- *MINOR* version for backwards-compatible functionality additions,
- *PATCH* version for backwards-compatible bug fixes.

Learn more about Semver at [semver.org](https://semver.org).

== Support ==

- [General Help Requests](https://github.com/wp-graphql/wp-graphql/discussions): For general questions and help requests, create a new topic in Github Discussions
- [Discord Community](https://www.wpgraphql.com/discord): The WPGraphQL Discord is a great place to communicate in real-time. Ask questions, discuss features, get to know other folks using WPGraphQL.
- [Bug Reports](https://github.com/wp-graphql/wp-graphql/issues/new?assignees=&labels=&projects=&template=bug_report.yml): Report a bug in WPGraphQL
- [Feature Requests](https://github.com/wp-graphql/wp-graphql/issues/new?assignees=&labels=&projects=&template=feature_request.yml): Suggest an idea, feature, or enhancement for WPGraphQL.
- [Report a Security Vulnerability](https://github.com/wp-graphql/wp-graphql/security/advisories/new): Report a security vulnerability.


== FAQs ==

**Does this work with ACF Extended?**

Yes! WPGraphQL for ACF allows you to query for (most) fields created with ACF Extended.

**Can I filter and sort queries by ACF Fields using WPGraphQL for ACF?**

At this time WPGraphQL for ACF does not support filtering or sorting queries by ACF Fields. "Meta Queries" are often very expensive to execute, so we currently do not support filtering by ACF fields out of the box, but are exploring options for supporting it without the performance penalty.

**I think I found a bug, where do I report it?**

If you think you found a bug, please open an issue on [GitHub](https://github.com/wp-graphql/wpgraphql-acf). The more details you provide in the issue, and the more clear your steps to reproduce are, the higher chances we will be able to help.

**Can I use ACF Free or Pro with WPGraphQL for ACF?**

Yes! WPGraphQL for ACF works great with ACF Free and Pro. The Pro version of ACF has some additional features, such as Flexible Content Fields, Repeater Fields and Options Pages that are supported by WPGraphQL for ACF.

**Do I have to use Faust.js to use WPGraphQL for ACF?**

No! While [wpgraphql.com](https://www.wpgraphql.com) and [acf.wpgraphql.com](https://acf.wpgraphql.com) are built using [Faust.js](https://faustjs.org/) and Next.js, you can use WPGraphQL for ACF with any GraphQL client, including Apollo, Relay, Urql, etc.

**I have an ACF Extension that adds a new field type, will it work with WPGraphQL for ACF?**

WPGraphQL for ACF supports the field types that come with ACF (Free and PRO) as well as the field types in ACF Extended (Free and PRO). Support for additional field types can be added by using the "register_graphql_acf_field_type" API.

**Do I need WPGraphQL and ACF to be active to use this?**

This plugin is a "bridge" plugin that brings functionality of ACF to WPGraphQL. Both WPGraphQL and ACF need to be installed and active in your WordPress installation for this plugin to work.

**How much does WPGraphQL for ACF cost?**

WPGraphQL for ACF is a FREE open-source plugin. The development is sponsored by [WP Engine Atlas](https://wpengine.com/atlas).

**Does WPGraphQL for ACF support GraphQL Mutations?**

GraphQL Mutations are not yet supported. We are working on adding support for Mutations in the future. We are waiting for the GraphQL "@oneOf" directive to be merged into the GraphQL spec before we add support for Mutations.

**Does this work with Field Groups registered in PHP or JSON?**

Yes! You can register ACF Field Groups and Fields using the Admin UI, PHP or JSON. WPGraphQL for ACF will detect the Field Groups and Fields and add them to the GraphQL Schema. If using PHP or JSON, you will need to set the "show_in_graphql" setting to "true" to expose the Field Group and Fields to the GraphQL Schema. There might be other settings that need attention at the field group or field level that might impact the schema or field resolution.


== Privacy Policy ==

WPGraphQL for Advanced Custom Fields uses [Appsero](https://appsero.com) SDK to collect telemetry data upon user confirmation, helping us troubleshoot problems and improve the product.

The Appsero SDK **doesn't collect data by default** and only starts gathering basic telemetry data when a user allows it via the admin notice. No data is collected without user consent.

Learn more about how [Appsero collects and uses data](https://appsero.com/privacy-policy/).

== Upgrade Notice ==

= 2.3.0 =

This release refactored some internals regarding how Clone fields and Group fields behave. There was no intentional breaking changes to the Schema, but if you are using Clone and Group fields there is a chance that if you were benefiting from a "bug as a feature" there might be some changes that could impact your Schema and/or resolvers, we recommend testing this update on a staging site to ensure things are still working for you as expected. Should you run into any problems, please [open a new issue](https://github.com/wp-graphql/wpgraphql-acf/issues/new/choose) and provide as much detail as possible to help us reproduce the scenario. Thanks! 🙏

= 2.1.0 =

While fixing some [performance issues](https://github.com/wp-graphql/wpgraphql-acf/pull/152) we had to adjust the fallback logic for mapping ACF Field Groups to the Schema if they do not have "graphql_types" defined.

ACF Field Groups that did not have "graphql_types" defined AND were assigned to Location Rules based on "post_status", "post_format", "post_category" or "post_taxonomy" _might_ not show in the Schema until their "graphql_types" are explicitly defined.

= 2.0.0 =

This release is a complete re-architecture of WPGraphQL for ACF, introducing breaking changes to the GraphQL Schema and PHP API. Please read the [upgrade guide](https://acf.wpgraphql.com/upgrade-guide/) before upgrading.

== Changelog ==

= 2.4.1 =

**Chores / Bugfixes**

- chore: update "tested up to" and stable version tags.

= 2.4.0 =

**New Features**

- [#211](https://github.com/wp-graphql/wpgraphql-acf/pull/211): feat: add wp-graphql as required plugin dependency. Thanks @stefanmomm!

**Chores / Bugfixes**

- [#224](https://github.com/wp-graphql/wpgraphql-acf/pull/224): chore: update issue templates config.yml to link to Discord instead of Slack
- [#223](https://github.com/wp-graphql/wpgraphql-acf/pull/223): chore: update branding assets
- [#214](https://github.com/wp-graphql/wpgraphql-acf/pull/214): chore: bump composer/composer from 2.7.4 to 2.7.7 in the composer group across 1 directory
- [#231](https://github.com/wp-graphql/wpgraphql-acf/pull/231): fix: block type tests failing

= 2.3.0 =

**New Features**

- [#193](https://github.com/wp-graphql/wpgraphql-acf/pull/193): feat: improved handling of clone and group fields

**Chores / Bugfixes**

- [#194](https://github.com/wp-graphql/wpgraphql-acf/pull/194): ci: test against WordPress 6.5

= 2.2.0 =

**New Features**

- [#181](https://github.com/wp-graphql/wpgraphql-acf/pull/181): feat: update docs Date fields to link to the RFC3339 spec

**Chores / Bugfixes**

- [#182](https://github.com/wp-graphql/wpgraphql-acf/pull/182): fix: admin_enqueue_scripts callback should expect a possible null value passed to it
- [#185](https://github.com/wp-graphql/wpgraphql-acf/pull/185): fix: clone field within a group field type returns null values
- [#189](https://github.com/wp-graphql/wpgraphql-acf/pull/189): fix: image fields (and other connection fields) not properly resolving when queried asPreview

= 2.1.2 =

**Chores / Bugfixes**

- [#178](https://github.com/wp-graphql/wpgraphql-acf/pull/178): fix: Taxonomy field returns incorrect data if set to store objects instead of IDs
- [#174](https://github.com/wp-graphql/wpgraphql-acf/pull/174): fix: taxonomy field resolves sorted in the incorrect order

= 2.1.1 =

**Chores / Bugfixes**

- [#167](https://github.com/wp-graphql/wpgraphql-acf/pull/167): fix: pagination on connection fields
- [#166](https://github.com/wp-graphql/wpgraphql-acf/pull/166): fix: errors when querying fields of the `acfe_date_range_picker` field type
- [#165](https://github.com/wp-graphql/wpgraphql-acf/pull/165): fix: user field returning all publicly queryable users

= 2.1.0 =

**New Features**

- [#156](https://github.com/wp-graphql/wpgraphql-acf/pull/156): feat: Use published ACF values in resolvers for fields associated with posts that use the block editor, since the Block Editor has a bug preventing meta from being saved for previews. Adds a debug message if ACF fields are queried for with "asPreview" on post(s) that use the block editor.

**Chores / Bugfixes**

- [#156](https://github.com/wp-graphql/wpgraphql-acf/pull/156): fix: ACF Fields not resolving when querying "asPreview"
- [#155](https://github.com/wp-graphql/wpgraphql-acf/pull/155): fix: "show_in_graphql" setting not being respected when turned "off"
- [#152](https://github.com/wp-graphql/wpgraphql-acf/pull/152): fix: performance issues with mapping ACF Field Groups to the Schema
- [#148](https://github.com/wp-graphql/wpgraphql-acf/pull/148): fix: bug when querying taxonomy field on blocks
- [#146](https://github.com/wp-graphql/wpgraphql-acf/pull/146): chore: update phpcs to match core WPGraphQL

= 2.0.0 =

- Initial release on WordPress.org. Complete re-architecture of WPGraphQL for ACF v0.6.*.
- For beta release notes leading up to v2.0.0, see the [Github Releases](https://github.com/wp-graphql/wpgraphql-acf/releases).
