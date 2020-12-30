# Changelog

## 1.0.5

### New

- Updates GraphQL-PHP from v14.3.0 to v14.4.0
- Updates GraphQL-Relay-PHP from v0.3.1 to v0.5.0

### Bugfixes

- Fixes a bug where CI Tests were not passing when code coverage is enabled
- ([#1633](https://github.com/wp-graphql/wp-graphql/pull/1633)) Fixes bug where Introspection Queries were showing fields with no deprecationReason as deprecated because it was outputting an empty string instead of a null value.
- ([#1627](https://github.com/wp-graphql/wp-graphql/pull/1627)) Fixes bug where fields on the Model called multiple times might weren't being set properly
- Updates Theme tests to be more resilient for WP Core updates where new themes are introduced

## 1.0.4

### Bugfixes

- Fixes a regression to previews introduced by v1.0.3 

## 1.0.3

### Bugfixes

- ([#1623](https://github.com/wp-graphql/wp-graphql/pull/1623)): Queries for single posts will only return posts of that post_type 
- ([#1624](https://github.com/wp-graphql/wp-graphql/pull/1624)): Passes Menu Item Labels through html_entity_decode

## 1.0.2

### Bugfixes

- fix issue with using the count() function on potentially not-countable value
- fix bug where post_status was being checked instead of comment_status
- fix error message when restoring a comment doesn't work
- ([#1610](https://github.com/wp-graphql/wp-graphql/issues/1610)) fix check to see if current user has permission to update another Author's post. Thanks @maximilianschmidt!


### New Features

- ([#1608](https://github.com/wp-graphql/wp-graphql/pull/1608)) move connections from each post type->contentType to be ContentNode->ContentType. Thanks @jeanfredrik!
- pass status code through as a param of the `graphql_process_http_request_response` action
- add test for mutating other authors posts 

## 1.0.1

### Bugfixes
- ([#1589](https://github.com/wp-graphql/wp-graphql/pull/1589)) Fixes a php type bug in TypeRegistry.php. Thanks @szepeviktor!
- [Fixes bug](https://github.com/wp-graphql/wp-graphql/compare/master...release/v1.0.1?expand=1#diff-74d71c4d1f9d84b9b0d946ca96eb875274f95d60611611d84cc01cdf6ed04021) with how GraphQL PHP Debug flags are set.
- ([#1598](https://github.com/wp-graphql/wp-graphql/pull/1598)) Fixes bug where Post Types registered with the same graphql_single_name and graphql_plural_name are the same value.
- ([#1615](https://github.com/wp-graphql/wp-graphql/issues/1615)) Fixes bug where fields added to the Schema that that were using get_post_meta() for Previews weren't always resolving properly.

### New Features
- Adds a setting to allow users to opt-in to tracking active installs of WPGraphQL.
- Removed old docs that used to be in this repo as markdown. Docs are now written in WordPress and the wpgraphql.com is a Gatsby site built from the content in WordPress and the [code in this repo](https://github.com/wp-graphql/wpgraphql.com). Looking to contribute content to the docs? Open an issue on this repo or the wpgraphql.com repo and we'll work with you to get content updated. We have future plans to allow the community to contribute by writing content in the WordPress install, but for now, Github issues will do.

## 1.0

Public Stable Release.

This release contains no technical changes.

Read the announcement: [https://www.wpgraphql.com/2020/11/16/announcing-wpgraphql-v1/](https://www.wpgraphql.com/2020/11/16/announcing-wpgraphql-v1/)

Previous release notes can be found on Github: [https://github.com/wp-graphql/wp-graphql/releases](https://github.com/wp-graphql/wp-graphql/releases)
