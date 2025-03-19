# Changelog

## 2.1.0

### New Features
- [#3320](https://github.com/wp-graphql/wp-graphql/pull/3320): feat: add filter to Request::is_valid_http_content_type to allow for custom content types with POST method requests

### Chores / Bugfixes
- [#3314](https://github.com/wp-graphql/wp-graphql/pull/3314): fix: use version_compare to simplify incompatible dependent check
- [#3316](https://github.com/wp-graphql/wp-graphql/pull/3316): docs: update changelog and upgrade notice
- [#3325](https://github.com/wp-graphql/wp-graphql/pull/3325): docs: update quick-start.md
- [#3190](https://github.com/wp-graphql/wp-graphql/pull/3190): docs: add developer docs for `AbstractConnectionResolver`

## 2.0.0

### BREAKING CHANGE UPDATE
This is a major update that drops support for PHP versions below 7.4 and WordPress versions below 6.0. We've written more about the update here:
- https://www.wpgraphql.com/2024/12/16/wpgraphql-v2-0-is-coming-heres-what-you-need-to-know
- https://www.wpgraphql.com/2024/12/16/wpgraphql-v2-0-technical-update-breaking-changes

## 1.32.1

### Chores / Bugfixes
- [#3308](https://github.com/wp-graphql/wp-graphql/pull/3308): fix: update term mutation was preventing terms from removing the parentId

## 1.32.0

### Upgrade Notice
In [#3293](https://github.com/wp-graphql/wp-graphql/pull/3293) a bug was fixed in how the `MediaDetails.file` field resolves. The previous behavior was a bug, but might have been used as a feature. If you need the field to behave the same as it did prior to this bugfix, you can [follow the instructions here](https://github.com/wp-graphql/wp-graphql/pull/3293) to override the field's resolver to how it worked before.

### New Features
- [#3294](https://github.com/wp-graphql/wp-graphql/pull/3294): feat: introduce new fields for getting mediaItem files and filePaths

### Chores / Bugfixes
- [#3293](https://github.com/wp-graphql/wp-graphql/pull/3293): fix: correct the resolver for the MediaDetails.file field to return the file name
- [#3299](https://github.com/wp-graphql/wp-graphql/pull/3299): chore: restore excluded PHPCS rules
- [#3301](https://github.com/wp-graphql/wp-graphql/pull/3301): fix: React backwards-compatibility with WP < 6.6
- [#3302](https://github.com/wp-graphql/wp-graphql/pull/3302): chore: update NPM dependencies
- [#3297](https://github.com/wp-graphql/wp-graphql/pull/3297): fix: typo in `Extensions\Registry\get_extensions()` method name
- [#3303](https://github.com/wp-graphql/wp-graphql/pull/3303): chore: cleanup git cache
- [#3298](https://github.com/wp-graphql/wp-graphql/pull/3298): chore: submit GF, Rank Math, and Headless Login plugins
- [#3287](https://github.com/wp-graphql/wp-graphql/pull/3287): chore: fixes the syntax of the readme.txt so that the short description is shown on WordPress.org
- [#3284](https://github.com/wp-graphql/wp-graphql/pull/3284): fix: Updated docs link for example of hierarchical data

