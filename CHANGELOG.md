# Changelog

## v2.3.4 - 2025-08-15

### Bug Fixes

- fix: make  void and call on  ([#3371](https://github.com/jasonbahl/automation-tests/pull/3371))

## v2.3.3 - 2025-06-17

### Bug Fixes

- fix: update skipped since tags ([#3372](https://github.com/jasonbahl/automation-tests/pull/3372))
- fix: check for preloaded AppContext::get_loader() ([#3384](https://github.com/jasonbahl/automation-tests/pull/3384))
- fix: cleanup  logic ([#3383](https://github.com/jasonbahl/automation-tests/pull/3383))

### Other Changes

- chore: improve type safety of  and schema registration ([#3382](https://github.com/jasonbahl/automation-tests/pull/3382))
- refactor: cleanup  class to reduce complexity and improve type safety ([#3381](https://github.com/jasonbahl/automation-tests/pull/3381))
- perf: refactor  to lazy-load dataloaders ([#3380](https://github.com/jasonbahl/automation-tests/pull/3380))
- chore: update Composer dev-deps and PHPCs ruleset ([#3379](https://github.com/jasonbahl/automation-tests/pull/3379))
- chore: expose array shape for   ([#3374](https://github.com/jasonbahl/automation-tests/pull/3374))
- chore: expose array shapes for register_graphql_enum_type()  ([#3373](https://github.com/jasonbahl/automation-tests/pull/3373))
- chore: narrow/fix php types on WPGraphQL, Server, Utils namespaces ([#3368](https://github.com/jasonbahl/automation-tests/pull/3368))



## v2.3.2 - 2025-06-15

### Other Changes

- chore: improve type safety of  and schema registration ([#3382](https://github.com/jasonbahl/automation-tests/pull/3382))
- refactor: cleanup  class to reduce complexity and improve type safety ([#3381](https://github.com/jasonbahl/automation-tests/pull/3381))
- perf: refactor  to lazy-load dataloaders ([#3380](https://github.com/jasonbahl/automation-tests/pull/3380))
- chore: update Composer dev-deps and PHPCs ruleset ([#3379](https://github.com/jasonbahl/automation-tests/pull/3379))



## v2.3.1 - 2025-06-01

### Other Changes

- chore: expose array shape for   ([#3374](https://github.com/jasonbahl/automation-tests/pull/3374))
- chore: expose array shapes for register_graphql_enum_type()  ([#3373](https://github.com/jasonbahl/automation-tests/pull/3373))
- chore: narrow/fix php types on WPGraphQL, Server, Utils namespaces ([#3368](https://github.com/jasonbahl/automation-tests/pull/3368))

## v2.3.0 - 2025-04-28

### New Features

- feat: lazy loading fields for Object Types and Interface Types ([#3356](https://github.com/jasonbahl/automation-tests/pull/3356))
- feat: Update Enum Type descriptions ([#3355](https://github.com/jasonbahl/automation-tests/pull/3355))

### Bug Fixes

- fix: don't initialize  twice in class constructor ([#3369](https://github.com/jasonbahl/automation-tests/pull/3369))
- fix: cleanup Model fields for better source-of-truth and type-safety. ([#3363](https://github.com/jasonbahl/automation-tests/pull/3363))
- fix: bump  and remove 7.3 references ([#3360](https://github.com/jasonbahl/automation-tests/pull/3360))

### Other Changes

- chore: improve type-safety for  class ([#3367](https://github.com/jasonbahl/automation-tests/pull/3367))
- chore: add array shapes to  and  ([#3366](https://github.com/jasonbahl/automation-tests/pull/3366))
- chore: inline (non-breaking) native return types ([#3362](https://github.com/jasonbahl/automation-tests/pull/3362))
- chore: implement array shapes for  ([#3364](https://github.com/jasonbahl/automation-tests/pull/3364))
- chore: Test compatibility with WordPress 6.8 ([#3361](https://github.com/jasonbahl/automation-tests/pull/3361))
- ci: trigger Codeception workflow more often ([#3359](https://github.com/jasonbahl/automation-tests/pull/3359))
- chore: Update Composer deps ([#3358](https://github.com/jasonbahl/automation-tests/pull/3358))



## v2.2.0 - 2025-04-15

### New Features

- feat: add support for graphql_description on register_post_type and register_taxonomy ([#3346](https://github.com/jasonbahl/automation-tests/pull/3346))

### Other Changes

- chore: update  placeholder that didn't properly get replaced during release ([#3349](https://github.com/jasonbahl/automation-tests/pull/3349))
- chore: update interface descriptions ([#3347](https://github.com/jasonbahl/automation-tests/pull/3347))

## v2.1.1 - 2025-03-19

### Bug Fixes

- fix: Avoid the deprecation warning when sending null header values ([#3338](https://github.com/jasonbahl/automation-tests/pull/3338))

### Other Changes

- chore: update README's for github workflows ([#3343](https://github.com/jasonbahl/automation-tests/pull/3343))
- chore: update cursor rules to use .cursor/rules instead of .cursorrules ([#3333](https://github.com/jasonbahl/automation-tests/pull/3333))
- chore: add WPGraphQL IDE to the extensions page ([#3332](https://github.com/jasonbahl/automation-tests/pull/3332))



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

