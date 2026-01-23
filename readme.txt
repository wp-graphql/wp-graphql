=== WPGraphQL Smart Cache ===
Contributors: jasonbahl, markkelnar
Tags: WPGraphQL, Cache, API, Persisted Queries, Performance
Requires at least: 5.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.1
Requires WPGraphQL: 2.0.0
WPGraphQL Tested Up To: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WPGraphQL Smart Cache is a WordPress plugin that provides fast, accurate API responses by intelligently caching and invalidating WPGraphQL queries.

=== Description ===

Do you want your API data _fast_ or _accurate_? With WPGraphQL Smart Cache, you can have both.

WPGraphQL Smart Cache is a free, open-source WordPress plugin that provides support for caching and cache invalidation of WPGraphQL Queries.

To get the most out of this plugin, we recommend using GET requests with Network Caching, which requires your WordPress install to be on a [supported host](https://github.com/wp-graphql/wp-graphql-smart-cache/blob/main/docs/network-cache.md#supported-hosts).

*BREAKING CHANGES:* We may make breaking changes in the future to improve functionality and experience. If we do, we will use semver to do so. Pay attention to release notes and upgrade notices before updating.

== Video Overview ==

<a href="https://youtu.be/t_y6q02q7K4" target="_blank"><img src="https://github.com/wp-graphql/wp-graphql-smart-cache/raw/main/docs/images/banner-wp-graphql-smart-cache-v1.jpg" width="640px" /></a>

== Docs ==

- [Overview](https://github.com/wp-graphql/wp-graphql-smart-cache#overview)
- [Quick Start](https://github.com/wp-graphql/wp-graphql-smart-cache#-quick-start)
- Features
  - [Network Cache](https://github.com/wp-graphql/wp-graphql-smart-cache/blob/main/docs/network-cache.md)
  - [Object Cache](https://github.com/wp-graphql/wp-graphql-smart-cache/blob/main/docs/object-cache.md)
  - [Persisted Queries](https://github.com/wp-graphql/wp-graphql-smart-cache/blob/main/docs/persisted-queries.md)
  - [Cache Invalidation](https://github.com/wp-graphql/wp-graphql-smart-cache/blob/main/docs/cache-invalidation.md)
- [Extending / Customizing Functionality](https://github.com/wp-graphql/wp-graphql-smart-cache/blob/main/docs/extending.md)
- [FAQ and Troubleshooting](https://github.com/wp-graphql/wp-graphql-smart-cache#faq--troubleshooting)
- [Known Issues](https://github.com/wp-graphql/wp-graphql-smart-cache#known-issues)
- [Providing Feedback](https://github.com/wp-graphql/wp-graphql-smart-cache#providing-feedback)

= Upgrading =

It is recommended that anytime you want to update WPGraphQL Smart Cache that you get familiar with what's changed in the release.

WPGraphQL Smart Cache publishes [release notes on GitHub](https://github.com/wp-graphql/wp-graphql-smart-cache/releases).

WPGraphQL Smart Cache will follow Semver versioning.

The summary of Semver versioning is as follows:

- *MAJOR* version when you make incompatible API changes,
- *MINOR* version when you add functionality in a backwards compatible manner, and
- *PATCH* version when you make backwards compatible bug fixes.

You can read more about the details of Semver at [semver.org](https://semver.org)

== Privacy Policy ==

WPGraphQL Smart Cache uses [Appsero](https://appsero.com) SDK to collect some telemetry data upon user's confirmation. This helps us to troubleshoot problems faster & make product improvements.

Appsero SDK **does not gather any data by default.** The SDK only starts gathering basic telemetry data **when a user allows it via the admin notice**. We collect the data to ensure a great user experience for all our users.

Integrating Appsero SDK **DOES NOT IMMEDIATELY** start gathering data, **without confirmation from users in any case.**

Learn more about how [Appsero collects and uses this data](https://appsero.com/privacy-policy/).


== Upgrade Notice ==

= 2.0.1 =

This release fixes an issue where authenticated user data (such as draft posts) could be incorrectly cached and served to public users when using the Object Cache feature. Users with Object Cache enabled should update immediately.

= 2.0.0 =

This release includes breaking changes to be compatible with WPGraphQL 2.0.0. When upgrading WPGraphQL to v2.0.0, you must also upgrade WPGraphQL Smart Cache to v2.0.0. WPGraphQL Smart Cache v2.0.0 is not compatible with WPGraphQL v1.x.x.

= 1.3.0 =

This fixes a regression to WPGraphQL v1.20.0 where the Query Analyzer became optional and defaulted to "off". WPGraphQL Smart Cache force-enables the Query Analyzer to support Cache tagging and tag-based cache invalidation.

= 1.2.0 =

**Code Removal**
This release removes some code specific to WP Engine that's been moved to WP Engine's MU Plugins.

Updating to WPGraphQL Smart Cache v1.2.0 or newer should be done at the same time as updating to [WPGraphQL v1.16.0](https://github.com/wp-graphql/wp-graphql/releases)
otherwise some caches might not evict properly in response to data changes.

**Garbage Collection of GraphQL Document**
When using "Automated Persisted Queries", documents are stored in the "GraphQL Document" post type and as client queries change over time an excess of persisted queries can be stored.

Garbage collection allows for documents to be purged after a certain amount of time.

You can enable "Garbage Collection" under "GraphQL > Settings > Saved Queries" and checking the option to "Delete Old Queries".

When enabling this feature, documents that are not associated with a "Group" will be purged after xx amount of days according to the settings.

Before enabling this setting, we recommend going through your saved GraphQL Documents and assigning a "group" to any that you want to skip garbage collection.

Groups are like bookmarks or collections for your GraphQL Documents. You can use them for whatever reason you like, but if a document is grouped, it will not be automatically garbage collected.

= 0.2.0 =

This release removes a lot of code that has since been released as part of WPGraphQL core.

In order to use v0.2.0+ of WPGraphQL Smart Cache, you will need WPGraphQL v1.12.0 or newer.

== Changelog ==

= 2.0.1 =

**Bugfixes**

- [#306](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/306): fix: prevent authenticated request data from being cached and served to public users. This fixes an issue where the object cache could incorrectly cache responses from authenticated users (containing draft posts, private content, etc.) due to WPGraphQL core calling `wp_set_current_user(0)` mid-request. The fix uses `AppContext->viewer` for reliable authentication state detection instead of `is_user_logged_in()`.

**Chores**

- ci: update deprecated GitHub Actions to v4
- ci: fix Gherkin test compatibility by pinning behat/gherkin < 4.9

= 2.0.0 =

**Breaking Changes**

- This release includes breaking changes to be compatible with WPGraphQL 2.0.0. When upgrading WPGraphQL to v2.0.0, you must also upgrade WPGraphQL Smart Cache to v2.0.0. WPGraphQL Smart Cache v2.0.0 is not compatible with WPGraphQL v1.x.x.

**Chores / Bugfixes**

= 1.3.3 =

**Chores / Bugfixes**

- [#294](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/294): fix: queryid not returning X-GraphQL-Keys headers
- [#292](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/292): chore: update test workflow to use docker compose instead of docker-compose
- [#291](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/291): fix: restore whitespace rules for PHPCBF
- [#286](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/286): chore: update .wordpress-org assets
- [#284](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/284): chore: Note that hosts might set a limit on caching

= 1.3.2 =

**Chores / Bugfixes**

- [#278](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/278): ci: Update tests to run against WordPress 6.5

= 1.3.1 =

**Chores / Bugfixes**

- [#273](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/273): fix: improve clarity on Cache settings page
- [#272](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/272): fix: invalidate caches for menu items

= 1.3.0 =

**New Features**

- [#270](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/270): feat: force enable query analyzer. This fixes a regression to WPGraphQL v1.20.0 where the Query Analyzer became optional and defaulted to "off". WPGraphQL Smart Cache force-enables the Query Analyzer to support Cache tagging and tag-based cache invalidation.

= 1.2.1 =

**Chores / Bugfixes**

- [#266](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/266): ci: update tests to run against WordPres 6.4
- [#266](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/266): fix: ensure store_content() is passed a string to adhere to phpstan standards
- [#262](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/262): fix: remove invalid namespaces from autoloading. Thanks @szepeviktor!
- [#251](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/251): ci: add WP 6.3 to test matrix
- [#258](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/258): ci: add build-plugin command to set up no-dev


= 1.2.0 =

**New Features**

- [#227](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/227): feat: add garbage collection for graphql_documents (see upgrade notice)

**Chores / Bugfixes**

- [#244](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/244): fix: handle errors when editing graphql documents in the admin
- [#253](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/244): ci: add varnish docker image. Update docs.
- [#247](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/247): fix: remove wpengine specific code (see upgrade notice).
- [#257](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/247):257: ci: use .distignore when building plugin for github release

= 1.1.4 =

**Chores / Bugfixes**

- [#237](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/237) fix: when creating a new query, do not show "something is wrong with form data" error
- [#242](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/242) ci: increase phpstan to level 7
- [#241](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/241) ci: increase phpstan to level 5,6
- [#240](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/240) ci: increase phpstan to level 3,4
- [#239](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/239) ci: increase phpstan to level 2
- [#236](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/236) ci: add phpstan workflow to check code quality
- [#234](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/234) fix: do not cache mutations to object cache results
- [#235](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/235) ci: tests failing after wpgraphql v1.14.5 release

= 1.1.3 =

**Chores / Bugfixes**

- [#230](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/230) fix: disable cache maps when "Use Object Cache" is disabled

= 1.1.2 =

**Chores / Bugfixes**

- [#226](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/226) fix: add missing events to purge calls. Remove call to purge list of terms when term relationship has changed.

= 1.1.1 =

**Chores / Bugfixes**

- [#221](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/221) fix: updating menus not assigned to locations doesn't purge menus, even if their model is public

= 1.1.0 =

**New Features**

- [#215](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/215) feat: graphql_purge_logs

**Chores / Bugfixes**

- [#214](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/214) fix: over-purging tags

= 1.0.4 =

- [#210](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/210) fix: post_exists being called even though the `post_exists` function doesn't exist in this context. Check instanceof WP_Post instead.


= 1.0.3 =

- [#207](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/207) fix: ignore updates to "apple_news_update" meta key. Add `graphql_cache_ignored_meta_keys` filter for modifying the list of ignored meta keys.
- [#205](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/205) fix: ErrorException Warning: Attempt to read property "post_type" on null. Thanks @izzygld!

= 1.0.2 =

**Chores / Bugfixes**

- [#202](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/202) fix: ErrorException Warning: Attempt to read property "post_type" on null. Thanks @izzygld!

= 1.0.1 =

**Chores / Bugfixes**

- Add workflow to update plugin assets/readme when those files are changed
- update links to docs. Thanks @rodrigo-arias!
- set internal taxonomies to public => false, add tests.
- fix bug with the "purge cache" button in the settings page not properly purging all caches for WPEngine users

= 1.0 =

- Version change. no functional changes.

= 0.3.9 =

- fix: vendor directory not properly deploying to WordPress.org.

= 0.3.8 =

- fix: rename constant that didn't get updated in 0.3.4. Thanks @colis!

= 0.3.7 =

- chore: update readme.txt file which is displayed on WordPress.org

= 0.3.6 =

- fix: correct slug in deploy workflow

= 0.3.5 =

- ([#189](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/189)): chore: add workflow to deploy to the WordPress.org repo

= 0.3.4 =

- ([#188](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/188)): fix: update constant name for min required version of WPGraphQL. Conflict with constant name defined in WPGraphQL for ACF.

= 0.3.3 =

- ([#184](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/184)): fix: update min required version of WPGraphQL. This plugin relies on features introduced in v1.12.0 of WPGraphQL.

= 0.3.2 =

**New Features**

- ([#178](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/178)): feat: add new "graphql_cache_is_object_cache_enabled" filter

**Chores/Bugfixes**

- ([#179](https://github.com/wp-graphql/wp-graphql-smart-cache/pull/179)): fix: prevent error when users install the plugin with Composer

= 0.3.1 =

- chore: update readme.txt with tags, updated "tested up to" version
- chore: update testing matrix to run tests on more versions of WordPress and PHP
- chore: update docs
- chore: add icons and banner for WordPress.org

= 0.3.0 =

- feat: a LOT of updates to the documentation
- feat: add opt-in telemetry via Appsero.

= 0.2.3 =

- fix: fixes a bug where X-GraphQL-Keys weren't being returned properly when querying a persisted query by queryId

= 0.2.2 =

- fix bug with patch. Missing namespace

= 0.2.1 =

- add temporary patch for wp-engine users. Will be removed when the wp engine mu plugin is updated.


= 0.2.0

- chore: remove unreferenced .zip build artifact
- feat: remove a lot of logic from Collection.php that analyzes queries to generate cache keys and response headers, as this has been moved to core WPGraphQL
- feat: reference core WPGraphQL functions for storing cache maps for object caching
- chore: remove unused "use" statements in Invalidation.php
- feat: introduce new "graphql_purge" action, which can be hooked into by caching clients to purge caches by key
- chore: remove $collection->node_key() method and references to it.
- feat: add "purge("skipped:$type_name)" event when purge_nodes is called
- chore: remove model class prefixes from purge_nodes() calls
- chore: rename const WPGRAPHQL_LABS_PLUGIN_DIR to WPGRAPHQL_SMART_CACHE_PLUGIN_DIR
- chore: update tests to remove "node:" prefix from expected keys
- chore: update tests to use self::factory() instead of $this->tester->factory()
- chore: update Plugin docblock
- feat: add logic to ensure minimum version of WPGraphQL is active before executing functionality needed by it
- chore: remove filters that add model definitions to Types as that's been moved to WPGraphQL core

= 0.1.2 =

- Updates to support batch queries
- move save urls out of this plugin into the wpengine cache plugin
- updates to tests

= 0.1.1 =

- Initial release to beta users
