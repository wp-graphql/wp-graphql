# Changelog

## 1.3.9

### Chores / Bugfixes

- ([#1902](https://github.com/wp-graphql/wp-graphql/pull/1902)): Moves more documentation into markdown. Thanks @markkelnar!
- ([#1917](https://github.com/wp-graphql/wp-graphql/pull/1917)): Updates docblock on WPObjectType. Thanks @markkelnar!
- ([#1926](https://github.com/wp-graphql/wp-graphql/pull/1926)): Removes Telemetry.
- ([#1928](https://github.com/wp-graphql/wp-graphql/pull/1928)): Fixes bug (#1864) that was causing errors when get_post_meta() was used with a null meta key.
- ([#1929](https://github.com/wp-graphql/wp-graphql/pull/1929)): Adds Github Workflow to upload schema.graphql as release asset. 

### New Features

- ([#1924](https://github.com/wp-graphql/wp-graphql/pull/1924)): Adds new `graphql_http_request_response_errors` filter. Thanks @kidunot89!
- ([#1908](https://github.com/wp-graphql/wp-graphql/pull/1908)): Adds new `graphql_pre_resolve_uri` filter, allowing 3rd parties to filter the behavior of the nodeByUri resolver. Thanks @renatonascalves!

## 1.3.8

### Chores / Bugfixes

- ([#1897](https://github.com/wp-graphql/wp-graphql/pull/1897)): Fails batch requests when disabled earlier.
- ([#1893](https://github.com/wp-graphql/wp-graphql/pull/1893)): Moves more documentation into markdown. Thanks @markkelnar!

### New Features

- ([#1897](https://github.com/wp-graphql/wp-graphql/pull/1897)): Adds new setting to set a max number of batch operations to allow per Batch request.

## 1.3.7

### Chores / Bugfixes

- ([#1885](https://github.com/wp-graphql/wp-graphql/pull/1885)): Fixes regression to `register_graphql_connection` that was breaking custom connections registered by 3rd party plugins. 

## 1.3.6

### Chores / Bugfixes

- ([#1878](https://github.com/wp-graphql/wp-graphql/pull/1878)): Limits the x-hacker header to be output when in DEBUG mode by default. Thanks @wvffle!
- ([#1880](https://github.com/wp-graphql/wp-graphql/pull/1880)): Fixes the formatting of the modified date for Post objects. Thanks @chriszarate!
- ([#1851](https://github.com/wp-graphql/wp-graphql/pull/1851)): Update Schema Linker Github Action. Thanks @markkelnar!
- ([#1858](https://github.com/wp-graphql/wp-graphql/pull/1858)): Start migrating docs into markdown files within the repo. Thanks @markkelnar!
- ([#1856](https://github.com/wp-graphql/wp-graphql/pull/1856)): Move Schema Linter Github Action into multiple steps. Thanks @szepeviktor!

### New Features

- ([#1869](https://github.com/wp-graphql/wp-graphql/pull/1869)): Adds new setting to the GraphQL Settings page to allow site administrators to restrict the endpoint to authenticated requests.
- ([#1874](https://github.com/wp-graphql/wp-graphql/pull/1874)): Adds new setting to the GraphQL Settings page to allow site administrators to disable Batch Queries. 
- ([#1875](https://github.com/wp-graphql/wp-graphql/pull/1875)): Adds new setting to the GraphQL Settings page to allow site administrators to enable a max query depth and specify the depth.

## 1.3.5

### Chores / Bugfixes

- ([#1846](https://github.com/wp-graphql/wp-graphql/pull/1846)): Fixes bug where sites with no menu locations can throw a php error in the MenuItemConnectionResolver. Thanks @markkelnar!

## 1.3.4

### New Features

- ([#1834](https://github.com/wp-graphql/wp-graphql/pull/1834)): Adds new `rename_graphql_type` function that allows Types to be given a new name in the Schema. Thanks @kidunot89!
- ([#1830](https://github.com/wp-graphql/wp-graphql/pull/1830)): Adds new `rename_graphql_field_name` function that allows fields to be given re-named in the Schema. Thanks @kidunot89!

### Chores / Bugfixes

- ([#1820](https://github.com/wp-graphql/wp-graphql/pull/1820)): Fixes bug where one test in the test suite wasn't executing properly. Thanks @markkelnar!
- ([#1817](https://github.com/wp-graphql/wp-graphql/pull/1817)): Fixes docker environment to allow xdebug to run. Thanks @markkelnar!
- ([#1833](https://github.com/wp-graphql/wp-graphql/pull/1833)): Allow specific Test Suites to be executed when running tests with Docker. Thanks @markkelnar!
- ([#1816](https://github.com/wp-graphql/wp-graphql/pull/1816)): Fixes bug where user roles without a name caused errors when building the Schema
- ([#1824](https://github.com/wp-graphql/wp-graphql/pull/1824)): Fixes bug where setting the role of tracing/query logs to "any" wasn't being respected. Thanks @toriphes!
- ([#1828](https://github.com/wp-graphql/wp-graphql/pull/1828)): Fixes bug with Term connection pagination ordering


## 1.3.3

### Bugfixes / Chores

- ([#1806](https://github.com/wp-graphql/wp-graphql/pull/1806)): Fixes bug where databaseId couldn't be queried on the CommentAuthor type
- ([#1808](https://github.com/wp-graphql/wp-graphql/pull/1808)) & ([#1811](https://github.com/wp-graphql/wp-graphql/pull/1811)): Updates Schema descriptions across the board. Thanks @markkelnar!
- ([#1809](https://github.com/wp-graphql/wp-graphql/pull/1809)): Fixes bug where child terms couldn't properly be queried by URI.
- ([#1812](https://github.com/wp-graphql/wp-graphql/pull/1812)): Fixes bug where querying users in a site with many non-published authors can return 0 results.

## 1.3.2

### Bugfix

- Fixes ([#1802](https://github.com/wp-graphql/wp-graphql/issues/1802)) by reversing a change to how initial post types and taxonomies are setup.

## 1.3.1

### Bugfix

- patches a bug where default post types and taxonomies disappeared from the Schema

## 1.3.0

### Noteable changes

Between this release and the prior release ([v1.2.6](https://github.com/wp-graphql/wp-graphql/releases/tag/v1.2.6)) includes changes to pagination under the hood.

While these releases correcting mistakes and buggy behavior, it's possible that workarounds have already been implemented either in the server or in client applications.

For example, there was a bug with `start/end` cursors being reversed for backward pagination.

If a client application were reversing the cursors to fix the issue, the reversal in the client will now _cause_ the issue.

It's recommended to test your applications against this release, _specifically_ in regards to pagination.

### Bugfixes / Chores

- ([#1797](https://github.com/wp-graphql/wp-graphql/pull/1797)): Update test environment to allow custom permalink structures to be better tested. Moves the "show_in_graphql" setup of core post types and taxonomies into the `register_post_type_args` and `register_taxonomy_args` filters instead of modifying global filters directly.
- ([#1794](https://github.com/wp-graphql/wp-graphql/pull/1794)): Cleanup to PHPStan config. Thanks @szepeviktor!
- ([#1795](https://github.com/wp-graphql/wp-graphql/pull/1795)) and ([#1793](https://github.com/wp-graphql/wp-graphql/pull/1793)): Don't throw errors when external urls are provided as input for queries that try and resolve by uri
- ([#1792](https://github.com/wp-graphql/wp-graphql/pull/1792)): Add missing descriptions to various fields in the Schema. Thanks @markkelnar!
- ([#1791](https://github.com/wp-graphql/wp-graphql/pull/1791)): Update where `WP_GRAPHQL_URL` is defined to follow recommendation from WordPress.org.
- ([#1784](https://github.com/wp-graphql/wp-graphql/pull/1784)): Fix `UsersConnectionSearchColumnEnum` to show the proper values that were accidentally replaced.
- ([#1781](https://github.com/wp-graphql/wp-graphql/pull/1781)): Fixes various bugs related to pagination. Between this release and the v1.2.6 release the following bugs have been worked on in regards to pagination: ([#1780](https://github.com/wp-graphql/wp-graphql/pull/1780), [#1411](https://github.com/wp-graphql/wp-graphql/pull/1411), [#1552](https://github.com/wp-graphql/wp-graphql/pull/1552), [#1714](https://github.com/wp-graphql/wp-graphql/pull/1714), [#1440](https://github.com/wp-graphql/wp-graphql/pull/1440))
## 1.2.6

### Bugfixes / Chores

- ([#1773](https://github.com/wp-graphql/wp-graphql/pull/1773)) Fixes multiple issues ([#1411](https://github.com/wp-graphql/wp-graphql/pull/1411), [#1440](https://github.com/wp-graphql/wp-graphql/pull/1440), [#1714](https://github.com/wp-graphql/wp-graphql/pull/1714), [#1552](https://github.com/wp-graphql/wp-graphql/pull/1552)) related to backward pagination .
- ([#1775](https://github.com/wp-graphql/wp-graphql/pull/1775)) Updates resolver for `MenuItem.children` connection to ensure the children belong to the same menu as well to prevent orphaned items from being returned.
- ([#1774](https://github.com/wp-graphql/wp-graphql/pull/1774)) Fixes bug where the `terms` connection wasn't properly being added to all Post Types that have taxonomy relationships. Thanks @toriphes!
- ([#1752](https://github.com/wp-graphql/wp-graphql/pull/1752)) Update documentation in README. Thanks @markkelnar!
- ([#1759](https://github.com/wp-graphql/wp-graphql/pull/1759)) Update WPGraphQL Includes method to be called only if composer install has been run. Helpful for contributors that have cloned the plugin locally. Thanks @rsm0128!
- ([#1760](https://github.com/wp-graphql/wp-graphql/pull/1760)) Fixes the `MediaItem.sizes` resolver. (see: [#1758](https://github.com/wp-graphql/wp-graphql/pull/1758)). Thanks @rsm0128!
- ([#1763](https://github.com/wp-graphql/wp-graphql/pull/1763)) Update `testVersion` in phpcs.xml to match required php version. Thanks @GaryJones!

## 1.2.5

### Bugfixes / Chores

- ([#1748](https://github.com/wp-graphql/wp-graphql/pull/1748)) Fixes issue where installing the plugin in Trellis using Composer was causing the plugin not to load properly.

## 1.2.4

### Bugfixes / Chores

- More work to fix Github -> SVN deploys. 🤦‍♂️

## 1.2.3

### Bugfixes / Chores

- Addresses bug (still) causing deploys to WordPress.org to fail and not include the vendor directory.

## 1.2.2

### Bugfixes / Chores

- Fixes Github workflow to deploy to WordPress.org

## 1.2.1

### Bugfixes / Chores

- ([#1741](https://github.com/wp-graphql/wp-graphql/pull/1741)) Fix issue with DefaultTemplate not being registered to the Schema and throwing errors when no other templates are registered.

## 1.2.0

### New

- ([#1732](https://github.com/wp-graphql/wp-graphql/pull/1732)) Add `isPrivacyPage` to the Schema for the Page type. Thanks @Marco-Daniel!

### Bugfixes / Chores

- ([#1734](https://github.com/wp-graphql/wp-graphql/pull/1734)) Remove Composer dependencies from being versioned in Github. Update Github workflows to install dependencies for deploying to WordPress.org and uploading release assets on Github.

## 1.1.8

### Bugfixes / Chores

- Fix release asset url in Github action.

## 1.1.7

### Bugfixes / Chores

- Fix release upload url in Github action.

## 1.1.6

### Bugfixes / Chores

- ([#1723](https://github.com/wp-graphql/wp-graphql/pull/1723)) Fix CI Schema Linter action. Thanks @szepeviktor!
- ([#1722](https://github.com/wp-graphql/wp-graphql/pull/1722)) Update PR Template message. Thanks @szepeviktor!
- ([#1730](https://github.com/wp-graphql/wp-graphql/pull/1730)) Updates redundant test configuration in Github workflow. Thanks @szepeviktor!

## 1.1.5

### Bugfixes / Chores

- ([#1718](https://github.com/wp-graphql/wp-graphql/pull/1718)) Simplify the main plugin file to adhere to more modern WP plugin standards. Move the WPGraphQL class to it's own file under the src directory. Thanks @szepeviktor!
- ([#1704](https://github.com/wp-graphql/wp-graphql/pull/1704)) Fix end tags for inputs on the WPGraphQL Settings page to adhere to the w3 spec for inputs. Thanks @therealgilles!
- ([#1706](https://github.com/wp-graphql/wp-graphql/pull/1706)) Show all content types in the ContentTypeEnum, not just public ones. Thanks @ljanecek!
- ([#1699](https://github.com/wp-graphql/wp-graphql/pull/1699)) Set default value for 2nd paramater on `Tracker->get_info()` method. Thanks @SpartakusMd!

## 1.1.4

### Bugfixes

- ([#1715](https://github.com/wp-graphql/wp-graphql/pull/1715)) Updates `WPGraphQL\Type\Object` namespace to be `WPGraphQL\Type\ObjectType` to play nice with newer versions of PHP where `Object` is a reserved namespace.
- ([#1711](https://github.com/wp-graphql/wp-graphql/pull/1711)) Updates regex in phpstan.neon.dist. Thanks @szepeviktor!
- ([#1719](https://github.com/wp-graphql/wp-graphql/pull/1719)) Update to backtrace that is output with graphql_debug messages to ensure it includes a `file` key in the returned array, before returning the trace. Thanks @kidunot89!

## 1.1.3

### Bugfixes

- ([#1693](https://github.com/wp-graphql/wp-graphql/pull/1693)) Clear global user in the Router in case plugins have attempted to set the user before API authentication has been executed. Thanks @therealgilles!

### New

- ([#972](https://github.com/wp-graphql/wp-graphql/pull/972)) `graphql_pre_model_data_is_private` filter was added to the Abstract Model.php allowing Model's `is_private()` check to be bypassed.


## 1.1.2

### Bugfixes

- ([#1676](https://github.com/wp-graphql/wp-graphql/pull/1676)) Add a `nav_menu_item` loader to allow previous menu item IDs to work properly with WPGraphQL should they be passed to a node query (like, if the ID were persisted somewhere already)
- Update cases of menu item IDs to be `post:$id` instead of `nav_menu_item:$id`
- Update tests to test that both the old `nav_menu_item:$id` and `post:$id` work for nav menu item node queries to support previously issued IDs

## 1.1.1

### Bugfixes

- ([#1670](https://github.com/wp-graphql/wp-graphql/issues/1670)) Fixes a bug with querying pages that are set as to be the posts page

## 1.1.0

This release centers around updating code quality by implementing [PHPStan](https://phpstan.org/) checks. PHPStan is a tool that statically analyzes PHP codebases to detect bugs. This release centers around updating Docblocks and overall code quality, and implements automated tests to check code quality on every pull request.

### New

- Update PHPStan (Code Quality checker) to v0.12.64
- Increases PHPStan code quality checks to Level 8 (highest level).

### Bugfixes
- ([#1653](https://github.com/wp-graphql/wp-graphql/issues/1653)) Fixes bug where WPGraphQL was explicitly setting `has_published_posts` on WP_Query but WP_Query does this under the hood already. Thanks @jmartinhoj!
- Fixes issue with Comment Model returning comments that are not associated with a Post object. Comments with no associated Post object are not public entities.
- Update docblocks to be compatible with PHPStan Level 8. 
- Removed some uncalled code
- Added early returns in some places to prevent unnecessary added execution

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
