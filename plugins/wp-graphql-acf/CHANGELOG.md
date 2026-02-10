# Changelog

## 2.4.1

### Chores / Bugfixes

- chore: update "tested up to" and stable version tags.

## 2.4.0

### New Features

- [#211](https://github.com/wp-graphql/wpgraphql-acf/pull/211): feat: add wp-graphql as required plugin dependency. Thanks @stefanmomm!

### Chores / Bugfixes

- [#224](https://github.com/wp-graphql/wpgraphql-acf/pull/224): chore: update issue templates config.yml to link to Discord instead of Slack
- [#223](https://github.com/wp-graphql/wpgraphql-acf/pull/223): chore: update branding assets
- [#214](https://github.com/wp-graphql/wpgraphql-acf/pull/214): chore: bump composer/composer from 2.7.4 to 2.7.7 in the composer group across 1 directory
- [#231](https://github.com/wp-graphql/wpgraphql-acf/pull/231): fix: block type tests failing

## 2.3.0

## Update Notice

This release refactored some internals regarding how Clone fields and Group fields behave. There was no intentional breaking changes to the Schema, but if you are using Clone and Group fields there is a chance that if you were benefiting from a "bug as a feature" there might be some changes that could impact your Schema and/or resolvers, we recommend testing this update on a staging site to ensure things are still working for you as expected. Should you run into any problems, please [open a new issue](https://github.com/wp-graphql/wpgraphql-acf/issues/new/choose) and provide as much detail as possible to help us reproduce the scenario. Thanks! 🙏

### New Features

- [#193](https://github.com/wp-graphql/wpgraphql-acf/pull/193): feat: improved handling of clone and group fields

### Chores / Bugfixes

- [#194](https://github.com/wp-graphql/wpgraphql-acf/pull/194): ci: test against WordPress 6.5

## 2.2.0

### New Features

- [#181](https://github.com/wp-graphql/wpgraphql-acf/pull/181): feat: update docs Date fields to link to the RFC3339 spec

### Chores / Bugfixes

- [#182](https://github.com/wp-graphql/wpgraphql-acf/pull/182): fix: admin_enqueue_scripts callback should expect a possible null value passed to it
- [#185](https://github.com/wp-graphql/wpgraphql-acf/pull/185): fix: clone field within a group field type returns null values
- [#189](https://github.com/wp-graphql/wpgraphql-acf/pull/189): fix: image fields (and other connection fields) not properly resolving when queried asPreview

## 2.1.2

### Chores / Bugfixes

- [#178](https://github.com/wp-graphql/wpgraphql-acf/pull/178): fix: Taxonomy field returns incorrect data if set to store objects instead of IDs
- [#174](https://github.com/wp-graphql/wpgraphql-acf/pull/174): fix: taxonomy field resolves sorted in the incorrect order

## 2.1.1

### Chores / Bugfixes

- [#167](https://github.com/wp-graphql/wpgraphql-acf/pull/167): fix: pagination on connection fields
- [#166](https://github.com/wp-graphql/wpgraphql-acf/pull/166): fix: errors when querying fields of the `acfe_date_range_picker` field type
- [#165](https://github.com/wp-graphql/wpgraphql-acf/pull/165): fix: user field returning all publicly queryable users

## 2.1.0

## Upgrade Notice

While fixing some [performance issues](https://github.com/wp-graphql/wpgraphql-acf/pull/152) we had to adjust the fallback logic for mapping ACF Field Groups to the Schema if they do not have "graphql_types" defined.

ACF Field Groups that did not have "graphql_types" defined AND were assigned to Location Rules based on "post_status", "post_format", "post_category" or "post_taxonomy" _might_ not show in the Schema until their "graphql_types" are explicitly defined.

## New Features

- [#156](https://github.com/wp-graphql/wpgraphql-acf/pull/156): feat: Use published ACF values in resolvers for fields associated with posts that use the block editor, since the Block Editor has a bug preventing meta from being saved for previews. Adds a debug message if ACF fields are queried for with "asPreview" on post(s) that use the block editor.

### Chores / Bugfixes

- [#156](https://github.com/wp-graphql/wpgraphql-acf/pull/156): fix: ACF Fields not resolving when querying "asPreview"
- [#155](https://github.com/wp-graphql/wpgraphql-acf/pull/155): fix: "show_in_graphql" setting not being respected when turned "off"
- [#152](https://github.com/wp-graphql/wpgraphql-acf/pull/152): fix: performance issues with mapping ACF Field Groups to the Schema
- [#148](https://github.com/wp-graphql/wpgraphql-acf/pull/148): fix: bug when querying taxonomy field on blocks
- [#146](https://github.com/wp-graphql/wpgraphql-acf/pull/146): chore: update phpcs to match core WPGraphQL

## 2.0.0

- [#142](https://github.com/wp-graphql/wpgraphql-acf/pull/142): Update README, versions, plugin assets for deploy to WordPress.org.

## 2.0.0-beta.7.0.0

### BREAKING CHANGES

- [#136](https://github.com/wp-graphql/wpgraphql-acf/pull/136): fix: clone fields not respecting "prefix_name" setting

### Chores / Bugfixes

- [#126](https://github.com/wp-graphql/wpgraphql-acf/pull/126): fix: Make sure oembed field is also formatted when part of a flexible_content field. Thanks @kpoelhekke!
- [#131](https://github.com/wp-graphql/wpgraphql-acf/pull/131): ci: replace Appsero Updater.php with a blank class to satisfy WordPress.org reqs.
- [#132](https://github.com/wp-graphql/wpgraphql-acf/pull/132): fix: options page with custom post_id not resolving properly
- [#16](https://github.com/wp-graphql/wpgraphql-acf/pull/16): ci: Build the plugin zip, deploy to WordPress.org

## 2.0.0-beta.6.0.0

### New Features

- [#114](https://github.com/wp-graphql/wpgraphql-acf/pull/114): feat: add pre filter for resolve type

### Chores / Bugfixes

- [#111](https://github.com/wp-graphql/wpgraphql-acf/pull/111): fix: oembed field type returns embed instead of URL entered to the field
- [#112](https://github.com/wp-graphql/wpgraphql-acf/pull/112): fix: field groups on page templates not resolving
- [#116](https://github.com/wp-graphql/wpgraphql-acf/pull/116): fix: error when querying `post_object`, `relationship` or `page_link` field nested on a `repeater` or `flexible_content` field
- [#122](https://github.com/wp-graphql/wpgraphql-acf/pull/122): ci: update tests to run against WordPress 6.4

## 2.0.0-beta.5.1.0

### Chores / Bugfixes

- [#102](https://github.com/wp-graphql/wpgraphql-acf/pull/102): fix: ACF Field Group Location Rules -> GraphQL Schema mapping bugs
- [#100](https://github.com/wp-graphql/wpgraphql-acf/pull/100): fix: bug with CPTUI Imports, post type/taxonomy graphql_single_name not being respected
- [#99](https://github.com/wp-graphql/wpgraphql-acf/pull/99): fix: Repeater, Group, File field not resolving properly when used as a field on ACF Blocks


## 2.0.0-beta.5.0.0

[read more](https://github.com/wp-graphql/wpgraphql-acf/releases/tag/v2.0.0-beta.5.0.0)

### New Features

- [#81](https://github.com/wp-graphql/wpgraphql-acf/pull/81): feat: 🚀 ACF Blocks Support. Query ACF Blocks using WPGraphQL!! 🚀 (when [WPGraphQL Content Blocks v1.2.0+](https://github.com/wpengine/wp-graphql-content-blocks/releases/) is active)
- feat: ACF Options UI support. ACF Pro v6.2 has a new Options Page UI and now you can register those options pages to show in graphql from the UI.

### Chores / Bugfixes

- [#77](https://github.com/wp-graphql/wpgraphql-acf/pull/77): fix: js error when clone fields are added to a field group.
- [#80](https://github.com/wp-graphql/wpgraphql-acf/pull/81): ci: put the files in a subfolder when build zip in github
- fix: cloning an individual field applied the entire cloned field's group as an interface to the clonee field group.
- [#85](https://github.com/wp-graphql/wpgraphql-acf/pull/85): fix: TermNodes returning null when not array and fix type being set to generic TermNode in Taxonomy field


## v2.0.0-beta.4.0.0

### Breaking Changes

#### Hook / Filter Name Changes

This release contains breaking changes. Action and Filter names have been renamed to be more consistent across the codebase and to follow a "namespace" pattern similar to core ACF.

If you have custom code that was hooking into / filtering WPGraphQL for ACF, check your hook names.

There's a table documenting the name changes here: [https://github.com/wp-graphql/wpgraphql-acf/pull/67#issue-1818715865](https://github.com/wp-graphql/wpgraphql-acf/pull/67#issue-1818715865)


#### LocationRules namespace change

The LocationRules class has changed from `\WPGraphQL\Acf\LocationRules` to `\WPGraphQL\Acf\LocationRules\LocationRules`. It's unlikely that you have custom code referencing this class, but if you do, you'll need to update references.

### New Features

- [#67](https://github.com/wp-graphql/wpgraphql-acf/pull/67): [BREAKING] feat: introduce new hook and filter names to reduce chances of conflicting with custom code extending the previous version of the plugin.

### Chores / Bugfixes

- [#62](https://github.com/wp-graphql/wpgraphql-acf/pull/62): fix: prepare values when return_format is set to "array"
- [#68](https://github.com/wp-graphql/wpgraphql-acf/pull/68): fix: fieldGroupName was always returning null
- [#69](https://github.com/wp-graphql/wpgraphql-acf/pull/69): fix: graphql_description default value incorrect
- [#73](https://github.com/wp-graphql/wpgraphql-acf/pull/73): fix: replace filter_input with sanitize_text_field

## v2.0.0-beta.3.1.0

### Chores / Bugfixes

- [#60](https://github.com/wp-graphql/wpgraphql-acf/pull/60): fix: graphql_field_names were being set incorrectly when adding new fields.

## v2.0.0-beta.3.0.0

### New Features

- [#46](https://github.com/wp-graphql/wpgraphql-acf/pull/46): feat: add "graphql_non_null" setting for fields
- [#54](https://github.com/wp-graphql/wpgraphql-acf/pull/54): fix: change namespace from WPGraphQLAcf to WPGraphQL\ACF

### Chores / Bugfixes

- [#55](https://github.com/wp-graphql/wpgraphql-acf/pull/55): fix: cline fields not resolving properly
- [#47](https://github.com/wp-graphql/wpgraphql-acf/pull/47): fix: allow inactive field groups to show in the Schema (but prevent their location rules from being set)

## v2.0.0-beta.2.4.0

### New Features

- [#47](https://github.com/wp-graphql/wpgraphql-acf/pull/47) feat: Expose field groups that are not active, but set to "show_in_graphql" to allow inactive groups to be cloned and used in the schema.

## v2.0.0-beta.2.3.0

### New Features

- [#38](https://github.com/wp-graphql/wpgraphql-acf/pull/38) feat: ACF Options Page support

## 2.0.0-beta.2.2.0

### New Features

- [#38](https://github.com/wp-graphql/wpgraphql-acf/pull/38) feat: ACF Extended / ACF Extended Pro support

## 2.0.0-beta.2.1.0

- [#35](https://github.com/wp-graphql/wpgraphql-acf/pull/35) feat: add Appsero opt-in telemetry.
- feat: add checks for dependencies (WPGraphQL, ACF, min versions, etc) before loading functionality. Show admin notice and graphql_debug messages if pre-reqs aren't satisfied.

## 2.0.0-beta.2.0.1 to 2.0.0-beta.2.0.5

### Chores / Bugfixes:

- [#33](https://github.com/wp-graphql/wpgraphql-acf/pull/33) fix: failing github workflow for uploading the graphql schema artifact upon releases. No functional changes for users.
- chore: debugging github workflow for uploading Schema Artifact

## 2.0.0-beta.2

### Chores / Bugfixes:

- [#31](https://github.com/wp-graphql/wpgraphql-acf/pull/31) fix: Prevent fatal error when editing ACF Field Groups

## 2.0.0-beta-1

- Initial public release. "Quiet Beta".
