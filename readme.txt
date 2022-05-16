=== WPGraphQL ===
Contributors: jasonbahl, tylerbarnes1, ryankanner, hughdevore, chopinbach, kidunot89
Tags: GraphQL, API, Gatsby, Headless, Decoupled, React, Nextjs, Vue, Apollo, REST, JSON,  HTTP, Remote, Query Language
Requires at least: 5.0
Tested up to: 5.9.1
Requires PHP: 7.1
Stable tag: 1.8.2
License: GPL-3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

=== Description ===

WPGraphQL is a free, open-source WordPress plugin that provides an extendable GraphQL schema and API for any WordPress site.

Below are some links to help you get started with WPGraphQL

- <a href="https://www.wpgraphql.com" target="_blank">WPGraphQL.com</a>
- <a href="https://wpgraphql.com/docs/quick-start" target="_blank">Quick Start Guide</a>
- <a href="https://wpgraphql.com/docs/intro-to-graphql" target="_blank">Intro to GraphQL</a>
- <a href="https://wpgraphql.com/docs/intro-to-wordpress" target="_blank">Intro to WordPress</a>
- <a href="https://join.slack.com/t/wp-graphql/shared_invite/zt-3vloo60z-PpJV2PFIwEathWDOxCTTLA" target="_blank">Join the WPGraphQL community on Slack</a>

= Build rich JavaScript applications with WordPress and GraphQL =

WPGraphQL allows you to separate your CMS from your presentation layer. Content creators can use the CMS they know, while developers can use the frameworks and tools they love.

WPGraphQL works great with:

- [Gatsby](https://gatsbyjs.com)
- [Apollo Client](https://www.apollographql.com/docs/react/)
- [NextJS](https://nextjs.org/)
- ...and more

= Query what you need. Get exactly that. =

With GraphQL, the client makes declarative queries, asking for the exact data needed, and in exactly what was asked for is given in response, nothing more. This allows the client have control over their application, and allows the GraphQL server to perform more efficiently by only fetching the resources requested.

= Fetch many resources in a single request. =

GraphQL queries allow access to multiple root resources, and also smoothly follow references between connected resources. While typical a REST API would require round-trip requests to many endpoints, GraphQL APIs can get all the data your app needs in a single request. Apps using GraphQL can be quick even on slow mobile network connections.

= Powerful Debugging Tools =

WPGraphQL ships with GraphiQL in your WordPress dashboard, allowing you to browse your site's GraphQL Schema and test Queries and Mutations.

= Upgrading =

It is recommended that anytime you want to update WPGraphQL that you get familiar with what's changed in the release.

WPGraphQL publishes [release notes on Github](https://github.com/wp-graphql/wp-graphql/releases).

WPGraphQL has been following Semver practices for a few years. We will continue to follow Semver and let version numbers communicate meaning. The summary of Semver versioning is as follows:

- *MAJOR* version when you make incompatible API changes,
- *MINOR* version when you add functionality in a backwards compatible manner, and
- *PATCH* version when you make backwards compatible bug fixes.

You can read more about the details of Semver at semver.org

== Frequently Asked Questions ==

= Can I use WPGraphQL with xx JavaScript Framework? =

WPGraphQL turns your WordPress site into a GraphQL API. Any client that can make http requests to the GraphQL endpoint can be used to interact with WPGraphQL.

= Where do I get WPGraphQL Swag? =

WPGraphQL Swag is available on the Gatsby Swag store.

= What's the relationship between Gatsby, WP Engine, and WPGraphQL? =

[WP Engine](https://wpengine.com/) is the employer of Jason Bahl, the creator and maintainer of WPGraphQL. He was previously employed by [Gatsby](https://gatsbyjs.com).

You can read more about this [here](https://www.wpgraphql.com/2021/02/07/whats-next-for-wpgraphql/).

Gatsby and WP Engine both believe that a strong GraphQL API for WordPress is a benefit for the web. Neither Gatsby or WP Engine are required to be used with WPGraphQL, however it's important to acknowledge and understand what's possible because of their investments into WPGraphQL and the future of headless WordPress!

== Privacy Policy ==

WPGraphQL uses [Appsero](https://appsero.com) SDK to collect some telemetry data upon user's confirmation. This helps us to troubleshoot problems faster & make product improvements.

Appsero SDK **does not gather any data by default.** The SDK only starts gathering basic telemetry data **when a user allows it via the admin notice**. We collect the data to ensure a great user experience for all our users.

Integrating Appsero SDK **DOES NOT IMMEDIATELY** start gathering data, **without confirmation from users in any case.**

Learn more about how [Appsero collects and uses this data](https://appsero.com/privacy-policy/).

== Upgrade Notice ==

= 1.6.7 =

There's been a bugfix in the Post Model layer which _might_ break existing behaviors.

WordPress Post Type registry allows for a post_type to be registered as `public` (`true` or `false`)
and `publicly_queryable` (`true` or `false`).

WPGraphQL's Model Layer was allowing published content of any post_type to be exposed publicly. This
change better respects the `public` and `publicly_queryable` properties of post types better.

Now, if a post_type is `public=>true`, published content of that post_type can be queried by public
WPGraphQL requests.

If a `post_type` is set to `public=>false`, then we fallback to the `publicly_queryable` property.
If a post_type is set to `publicly_queryable => true`, then published content of the Post Type can
be queried in WPGraphQL by public users.

If both `public=>false` and `publicly_queryable` is `false` or not defined, then the content of the
post_type will only be accessible via authenticated queries by users with proper capabilities to
access the post_type.

**Possible Action:** You might need to adjust your post_type registration to better reflect your intent.

- `public=>true`: The entries in the post_type will be public in WPGraphQL and will have a public
URI in WordPress.
- `public=>false, publicly_queryable=>true`: The entries in the post_type will be public in WPGraphQL,
but will not have individually respected URI from WordPress, and can not be queried by URI in WPGraphQL.
- `public=>false,publicly_queryable=>false`: The entries in the post_type will only be accessible in
WPGraphQL by authenticated requests for users with proper capabilities to interact with the post_type.

= 1.5.0 =

The `MenuItem.path` field was changed from `non-null` to nullable and some clients may need to make adjustments to support this.

= 1.4.0 =

The `uri` field was non-null on some Types in the Schema but has been changed to be nullable on all types that have it. This might require clients to update code to expect possible null values.

= 1.2.0 =

Composer dependencies are no longer versioned in Github. Recommended install source is WordPress.org or using Composer to get the code from Packagist.org or WPackagist.org.

== Changelog ==

= 1.8.2 =

**New Features**

- ([#2363](https://github.com/wp-graphql/wp-graphql/pull/2363)): Adds "uri" field to MenuItem type which resolves the path of the node which can then be used in a `nodeByUri` query to get the linked node. The path is relative and does not contain subdirectory path in a subdirectory multisite. the `path` field does include the multisite subdirectory path, still. Thanks @josephfusco and @justlevine!
- ([#2337](https://github.com/wp-graphql/wp-graphql/pull/2337)): Allows for either global ID or databaseId to be supplied in the ID field for user mutations. Thanks @justlevine!
- ([#2338](https://github.com/wp-graphql/wp-graphql/pull/2338)): Allows either global "relay" ID or databaseId for post object mutations. Thanks @justlevine!
- ([#2336](https://github.com/wp-graphql/wp-graphql/pull/2336)): Allows either global "relay" ID or databaseId for term object mutations. Thanks @justlevine!
- ([#2331](https://github.com/wp-graphql/wp-graphql/pull/2331)): Allows either global "relay" ID or databaseId for MediaItem object mutations. Thanks @justlevine!
- ([#2328](https://github.com/wp-graphql/wp-graphql/pull/2328)): Allows either global "relay" ID or databaseId for Comment object mutations. Thanks @justlevine!


**Chores/Bugfixes**

- ([#2368](https://github.com/wp-graphql/wp-graphql/pull/2368)): Updates dependencies for Schema Linter workflow.
- ([#2369](https://github.com/wp-graphql/wp-graphql/pull/2369)): Replaces the Codecov badge in the README with Coveralls badge. Thanks @justlevine!
- ([#2374](https://github.com/wp-graphql/wp-graphql/pull/2374)): Updates descriptions for PostObjectFieldFormatEnum. Thanks @justlevine!
- ([#2375](https://github.com/wp-graphql/wp-graphql/pull/2375)): Sets up the testing integration workflow to be able to run in multisite. Adds one workflow that runs in multisite. Fixes tests related to multisite.
- ([#2376](https://github.com/wp-graphql/wp-graphql/pull/2276)): Adds support for `['auth']['callback']` and `isPrivate` for the `register_graphql_mutation()` API.
- ([#2379](https://github.com/wp-graphql/wp-graphql/pull/2379)): Fixes a bug where term mutations were adding slashes when being stored in the database.
- ([#2380](https://github.com/wp-graphql/wp-graphql/pull/2380)): Fixes a bug where WPGraphQL wasn't sending the Wp class to the `parse_request` filter as a reference.
- ([#2382](https://github.com/wp-graphql/wp-graphql/pull/2382)): Fixes a bug where `register_graphql_field()` was not being respected by GraphQL Types added to the schema to represent Setting Groups of the core WordPress `register_setting()` API.


= 1.8.1 =

**New Features**

- ([#2349](https://github.com/wp-graphql/wp-graphql/pull/2349)): Adds tags to wpkses_post for WPGraphQL settings pages to be extended further. Thanks @eavonius!

**Chores/Bugfixes**

- ([#2358](https://github.com/wp-graphql/wp-graphql/pull/2358)): Updates NPM dependencies. Thanks @dependabot!
- ([#2357](https://github.com/wp-graphql/wp-graphql/pull/2357)): Updates NPM dependencies. Thanks @dependabot!
- ([#2356](https://github.com/wp-graphql/wp-graphql/pull/2356)): Refactors codebase to take advantage of the work done in #2353. Thanks @justlevine!
- ([#2354](https://github.com/wp-graphql/wp-graphql/pull/2354)): Fixes console warnings in GraphiQL related to missing React keys.
- ([#2353](https://github.com/wp-graphql/wp-graphql/pull/2353)): Refactors the WPGraphQL::get_allowed_post_types() and WPGraphQL::get_allowed_taxonomies() functions. Thanks @justlevine!
- ([#2350](https://github.com/wp-graphql/wp-graphql/pull/2350)): Fixes bug where Comment Authors were not always properly returning

= 1.8.0 =

**New Features**

- ([#2286](https://github.com/wp-graphql/wp-graphql/pull/2286)): Introduce new `Utils::get_database_id_from_id()` function to help DRY up some code around inputs that can accept Global IDs or Database IDs. Thanks @justlevine!
- ([#2327](https://github.com/wp-graphql/wp-graphql/pull/2327)): Update capability for plugin queries. Changes from `update_plugins` to `activate_plugins`. Thanks @justlevine!
- ([#2298](https://github.com/wp-graphql/wp-graphql/pull/2298)): Adds `$where` arguments to Plugin Connections. Thanks @justlevine!
- ([#2332](https://github.com/wp-graphql/wp-graphql/pull/2332)): Adds new Github workflow to build the GraphiQL App on pushes to `develop` and `master`. This should allow users that install WPGraphQL to install/update with Composer and have the GraphiQL app running, instead of having to run `npm install && npm run build` in addition to `composer install`.

**Chores / Bugfixes**

- ([#2286](https://github.com/wp-graphql/wp-graphql/pull/2286)): Remove old, no-longer used JS files. Remnant from 1.7.0 release.
- ([#2296](https://github.com/wp-graphql/wp-graphql/pull/2296)): Fixes bug with how post/page templates are added to the Schema. Thanks @justlevine!
- ([#2295](https://github.com/wp-graphql/wp-graphql/pull/2295)): Fixes bug where menus were returning when they shouldn't be. Thanks @justlevine!
- ([#2299](https://github.com/wp-graphql/wp-graphql/pull/2299)): Fixes bug with author ID not being cast to an integer properly in the MediaItemUpdate mutation. Thanks @abaicus!
- ([#2310](https://github.com/wp-graphql/wp-graphql/pull/2310)): Bumps node-forge npm dependency
- ([#2317](https://github.com/wp-graphql/wp-graphql/pull/2317)): Bumps composer dependencies
- ([#2291](https://github.com/wp-graphql/wp-graphql/pull/2291)): Add "allow-plugins" to composer.json to reduce warning output when running composer install. Thanks @justlevine!
- ([#2294](https://github.com/wp-graphql/wp-graphql/pull/2294)): Refactors AbstractConnectionResolver::get_nodes() to prevent double slicing. Thanks @justlevine!
- ([#2293](https://github.com/wp-graphql/wp-graphql/pull/2293)): Fixes connections that can be missing nodes when before/after arguments are empty. Thanks @justlevine!
- ([#2323](https://github.com/wp-graphql/wp-graphql/pull/2323)): Fixes bug in Comment mutations. Thanks @justlevine!
- ([#2320](https://github.com/wp-graphql/wp-graphql/pull/2320)): Fixes bug with filtering comments by commentType. Thanks @justlevine!
- ([#2319](https://github.com/wp-graphql/wp-graphql/pull/2319)): Fixes bug with the comment_text filter in Comment queries. Thanks @justlevine!

= 1.7.2 =

**Chores / Bugfixes**

- ([#2276](https://github.com/wp-graphql/wp-graphql/pull/2276)): Fixes a bug where `generalSettings.url` field was not in the Schema for multisite installs.
- ([#2278](https://github.com/wp-graphql/wp-graphql/pull/2278)): Adds a composer post-install script that installs JS dependencies and builds the JS app when `composer install` is run
- ([#2277](https://github.com/wp-graphql/wp-graphql/pull/2277)): Adds a condition to the docker image to only run `npm` scripts if the project has a package.json. Thanks @markkelnar!


= 1.7.1 =

**Chores / Bugfixes**

- ([#2268](https://github.com/wp-graphql/wp-graphql/pull/2268)): Fixes a bug in GraphiQL that would update browser history with every change to a query param.


= 1.7.0 =

**Chores / Bugfixes**

- ([#2228](https://github.com/wp-graphql/wp-graphql/pull/2228)): Allows optional fields to be set to empty values in the `updateUser` mutation. Thanks @victormattosvm!
- ([#2247](https://github.com/wp-graphql/wp-graphql/pull/2247)): Add WordPress 5.9 to the automated testing matrix. Thanks @markkelnar!
- ([#2242](https://github.com/wp-graphql/wp-graphql/pull/2242)): Adds End 2 End tests to test GraphiQL functionality in the admin.
- ([#2261](https://github.com/wp-graphql/wp-graphql/pull/2261)): Fixes a bug where the `pageByUri` query might return incorrect data when custom permalinks are set. Thanks @blakewilson!
- ([#2263](https://github.com/wp-graphql/wp-graphql/pull/2263)): Adds documentation entry for WordPress Application Passwords guide. Thanks @abhisekmazumdar!
- ([#2262](https://github.com/wp-graphql/wp-graphql/pull/2262)): Fixes a bug where settings registered via the core `register_setting()` API would cause Schema Introspection failures, causing GraphiQL and other tools to not work properly.

**New Features**

- ([#2248](https://github.com/wp-graphql/wp-graphql/pull/2248)): WPGraphiQL (the GraphiQL IDE in the WordPress dashboard) has been re-built to have an extension architecture and some updated user interfaces. Thanks for contributing to this effort @scottyzen!
- ([#2246](https://github.com/wp-graphql/wp-graphql/pull/2246)): Adds support for querying the `avatar` for the CommentAuthor Type and the Commenter Interface type.
- ([#2236](https://github.com/wp-graphql/wp-graphql/pull/2236)): Introduces new `graphql_model_prepare_fields` filter and deprecates `graphql_return_modeled_data` filter. Thanks @justlevine!
- ([#2265](https://github.com/wp-graphql/wp-graphql/pull/2265)): Adds opt-in telemetry tracking via Appsero, to allow us to collect helpful information for prioritizing future feature work, etc.

= 1.6.12 =

**Chores / Bugfixes**

- ([#2209](https://github.com/wp-graphql/wp-graphql/pull/2209)): Adds WordPress 5.8 to the testing matrix. Thanks @markkelnar!
- ([#2211](https://github.com/wp-graphql/wp-graphql/pull/2211)), ([#2216](https://github.com/wp-graphql/wp-graphql/pull/2216)), ([#2221](https://github.com/wp-graphql/wp-graphql/pull/2221)), ([#2223](https://github.com/wp-graphql/wp-graphql/pull/2223)): Bumps NPM dependencies for GraphiQL
- ([#2212](https://github.com/wp-graphql/wp-graphql/pull/2212)): Fixes how the `TermObject.uri` strips the link down to the path. Thanks @theodesp!
- ([#2215](https://github.com/wp-graphql/wp-graphql/pull/2215)): Fixes testing environment to play nice with a recent wp-browser update.
- ([#2218](https://github.com/wp-graphql/wp-graphql/pull/2218)): Update note on settings page explaining that Public Introspection is enabled when GraphQL Debug mode is enabled.
- ([#2220](https://github.com/wp-graphql/wp-graphql/pull/2220)): Adds CodeQL workflow to analyze JavaScript on PRs


= 1.6.11 =

**Chores / Bugfixes**

- ([#2177](https://github.com/wp-graphql/wp-graphql/pull/2177)): Prevents PHP notice when clientMutationId is not set on mutations. Thanks @oskarmodig!
- ([#2182](https://github.com/wp-graphql/wp-graphql/pull/2182)): Fixes bug where the graphql endpoint couldn't be accessed by a site domain other than the site_url(). Thanks @moommeister!
- ([#2184](https://github.com/wp-graphql/wp-graphql/pull/2184)): Fixes regression where duplicate type warning was not being displayed after lazy type loading was added in v1.6.0.
- ([#2189](https://github.com/wp-graphql/wp-graphql/pull/2189)): Fixes bug with content node previews
- ([#2196](https://github.com/wp-graphql/wp-graphql/pull/2196)): Further bug fixes for content node previews. Thanks @apmattews!
- ([#2197](https://github.com/wp-graphql/wp-graphql/pull/2197)): Fixes call to prepare_fields() to not be called statically. Thanks @justlevine!

**New Features**

- ([#2188](https://github.com/wp-graphql/wp-graphql/pull/2188)): Adds `contentTypeName` to the `ContentNode` type.
- ([#2199](https://github.com/wp-graphql/wp-graphql/pull/2199)): Pass the TypeRegistry instance through to the `graphql_schema_config` filter.
- ([#2204](https://github.com/wp-graphql/wp-graphql/pull/2204)): Allow a `root_value` to be set when calling the `graphql()` function.
- ([#2203](https://github.com/wp-graphql/wp-graphql/pull/2203)): Adds new filter to mutations to filter the input args before execution, and a new action after execution, before returning the mutation, to allow additional data to be stored during mutations. Thanks @markkelnar!

= 1.6.10 =

- Updating stable tag for WordPress.org

= 1.6.9 =

- No functional changes from v1.6.8. Fixing an issue with deploy to WordPress.org

= 1.6.8 =

**Chores / Bugfixes**

- ([#2143](https://github.com/wp-graphql/wp-graphql/pull/2143)): Adds `taxonomyName` field to the `TermNode` interface. Thanks @jeanfredrik!
- ([#2168](https://github.com/wp-graphql/wp-graphql/pull/2168)): Allows the GraphiQL screen markup to be filtered
- ([#2150](https://github.com/wp-graphql/wp-graphql/pull/2150)): Updates GraphiQL npm dependency to v1.4.7
- ([#2145](https://github.com/wp-graphql/wp-graphql/pull/2145)): Fixes a bug with cursor pagination stability


**New Features**

- ([#2141](https://github.com/wp-graphql/wp-graphql/pull/2141)): Adds a new `graphql_wp_connection_type_config` filter to allow customizing connection configurations. Thanks @justlevine!


= 1.6.7

**Chores / Bugfixes**

- ([#2135](https://github.com/wp-graphql/wp-graphql/pull/2135)): Fixes permission check in the Post model layer. Posts of a `'publicly_queryable' => true` post_type can be queried publicly (non-authenticated requests) via WPGraphQL, even if the post_type is set to `'public' => false`. Thanks @kellenmace!
- ([#2093](https://github.com/wp-graphql/wp-graphql/pull/2093)): Fixes `Post.pinged` field to properly return an array. Thanks @justlevine!
- ([#2132](https://github.com/wp-graphql/wp-graphql/pull/2132)): Fix issue where querying posts by slug could erroneously return null. Thanks @ChrisWiegman!
- ([#2127](https://github.com/wp-graphql/wp-graphql/pull/2127)): Update endpoint in documentation examples. Thanks @RafidMuhyim!


= 1.6.6

**New Features**

- ([#2106](https://github.com/wp-graphql/wp-graphql/pull/2106)): Add new `pre_graphql_execute_request` filter to better support full query caching. Thanks @markkelnar!
- ([#2123](https://github.com/wp-graphql/wp-graphql/pull/2123)): Add new `graphql_dataloader_get_cached` filter to better support persistent object caching in the Model Layer. Thanks @kidunot89!

**Chores / Bugfixes**

- ([#2094](https://github.com/wp-graphql/wp-graphql/pull/2094)): fix broken link in docs. Thanks @duffn!
- ([#2108](https://github.com/wp-graphql/wp-graphql/pull/2108)): Update lucatume/wp-browser dependency. Thanks @markkelnar!
- ([#2111](https://github.com/wp-graphql/wp-graphql/pull/2111)): Correct variable name passed to filter. Thanks @markkelnar!
- ([#2112](https://github.com/wp-graphql/wp-graphql/pull/2112)): Doc typo corrections. Thanks @nexxai!
- ([#2115](https://github.com/wp-graphql/wp-graphql/pull/2115)): Updates to GraphiQL npm dependencies. Thanks @alexghirelli!
- ([#2124](https://github.com/wp-graphql/wp-graphql/pull/2124)): Updates `tmpl` npm dependency.


= 1.6.5

**Chores / Bugfixes**

- ([#2081](https://github.com/wp-graphql/wp-graphql/pull/2081)): Set `is_graphql_request` earlier in Request.php. Thanks @jordanmaslyn!
- ([#2085](https://github.com/wp-graphql/wp-graphql/pull/2085)): Bump codeception from 4.1.21 to 4.1.22

**New Features**

- ([#2076](https://github.com/wp-graphql/wp-graphql/pull/2076)): Add `$graphiql` global variable to allow extensions the ability to more easily remove hooks/filters from the class.


= 1.6.4

**Chores / Bugfixes**

- ([#2076](https://github.com/wp-graphql/wp-graphql/pull/2076)): Updates WPGraphiQL IDE to use latest react, GraphiQL and other dependencies.

**New Features**

- ([#2076](https://github.com/wp-graphql/wp-graphql/pull/2076)): WPGraphiQL IDE now resizes when the browser window is resized.


= 1.6.3

**Chores / Bugfixes**

- ([#2064](https://github.com/wp-graphql/wp-graphql/pull/2064)): Fixes bug where using `asQuery` argument could return an error instead of a null when the ID passed could not be previewed.
- ([#2072](https://github.com/wp-graphql/wp-graphql/pull/2072)): Fixes bug (regression with 1.6) where Object Types for page templates were not properly loading in the Schema after Lazy Loading was introduced in 1.6.
- ([#2059](https://github.com/wp-graphql/wp-graphql/pull/2059)): Update typos and links in docs. Thanks @nicolnt!
- ([#2058](https://github.com/wp-graphql/wp-graphql/pull/2058)): Fixes bug in the filter_post_meta_for_previews was causing PHP warnings. Thanks @zolon4!


= 1.6.2 =

**Chores / Bugfixes**

- ([#2051](https://github.com/wp-graphql/wp-graphql/pull/2051)): Fixes a bug where Types that share the same name as a PHP function (ex: `Header` / `header()`) would try and call the function when loading the Type. See ([Issue #2047](https://github.com/wp-graphql/wp-graphql/issues/2047))
- ([#2055](https://github.com/wp-graphql/wp-graphql/pull/2055)): Fixes a bug where Connections registered from Types were adding connections to the registry too late causing some queries to fail. See Issue ([Issue #2054](https://github.com/wp-graphql/wp-graphql/issues/2054))


= 1.6.1 =

**Chores / Bugfixes**

- ([#2043](https://github.com/wp-graphql/wp-graphql/pull/2043)): Fixes a regression with GraphQL Request Execution that was causing Gatsby to fail builds.


= 1.6.0 =

**Chores / Bugfixes**

- ([#2000](https://github.com/wp-graphql/wp-graphql/pull/2000)): This fixes issue where all Types of the Schema were loaded for each GraphQL request. Now only the types required to fulfill the request are loaded on each request. Thanks @chriszarate!
- ([#2031](https://github.com/wp-graphql/wp-graphql/pull/2031)): This fixes a performance issue in the WPGraphQL model layer where determining whether a User is a published author was generating expensive MySQL queries on sites with a lot of users and a lot of content. Thanks @chriszarate!

= 1.5.8 =

**Chores / Bugfixes**

- ([#2038](https://github.com/wp-graphql/wp-graphql/pull/2038)): Exclude documentation directory from code archived by composer and deployed to WordPress.org

= 1.5.7 =

**Chores / Bugfixes**

- Update to trigger a missed deploy to WordPress.org. no functional changes from v1.5.6

= 1.5.6 =

**Chores / Bugfixes**

- ([#2035](https://github.com/wp-graphql/wp-graphql/pull/2035)): Fixes a bug where variables passed to `after_execute_actions` weren't properly set for Batch Queries.

**New Features**

- ([#2035](https://github.com/wp-graphql/wp-graphql/pull/2035)): (Yes, same PR as the bugfix above). Adds 2 new actions `graphql_before_execute` and `graphql_after_execute` to allow actions to run before/after the execution of entire Batch requests vs. the hooks that currently run _within_ each the execution of each operation within a request.


= 1.5.5 =

**Chores / Bugfixes**

- ([#2023](https://github.com/wp-graphql/wp-graphql/pull/2023)): Fixes issue with deploying Docker Testing Images. Thanks @markkelnar!
- ([#2025](https://github.com/wp-graphql/wp-graphql/pull/2025)): Update test workflow to test against WordPress 5.8 (released today) and updates the readme.txt to reflect the plugin has been tested up to 5.8
- ([#2028](https://github.com/wp-graphql/wp-graphql/pull/2028)): Update Codeception test environment to prevent WordPress from entering maintenance mode during tests.

= 1.5.4 =

**Chores / Bugfixes**

- ([#2012](https://github.com/wp-graphql/wp-graphql/pull/2012)): Adds functional tests back to the Github testing workflow!
- ([#2016](https://github.com/wp-graphql/wp-graphql/pull/2016)): Ignore Schema Linter workflow on releases, run on PRs only.
- ([#2019](https://github.com/wp-graphql/wp-graphql/pull/2019)): Deploy Docker Testing Image on releases. Thanks @markkelnar!

**New Features**

- ([#2011](https://github.com/wp-graphql/wp-graphql/pull/2011)): Introduces a new API to allow Types to register connections at the Type registration level and refactors several internal Types to use this new API.


= 1.5.3 =

**Chores / Bugfixes**

- ([#2001](https://github.com/wp-graphql/wp-graphql/pull/2001)): Updates Docker environment to use MariaDB instead of MySQL to play nice with those fancy M1 Macs. Thanks @chriszarate!
- ([#2002](https://github.com/wp-graphql/wp-graphql/pull/2002)): Add PHP8 Docker image to deploy upon releases. Thanks @markkelnar!
- ([#2006](https://github.com/wp-graphql/wp-graphql/pull/2006)): Update Docker to use $PROJECT_DIR variable instead of hardcoded value to allow composed docker images to run their own tests from their own project. Thanks @markkelnar!
- ([#2007](https://github.com/wp-graphql/wp-graphql/pull/2007)): Update broken links to Relay spec. Thanks @ramyareye!

**New Features**

- ([#2009](https://github.com/wp-graphql/wp-graphql/pull/2009)): Adds new WPConnectionType class and refactors register_graphql_connection() to use the class. Functionality should be the same, but this sets the codebase up for some new connection APIs.


= 1.5.2 =

**Chores / Bugfixes**

- ([#1992](https://github.com/wp-graphql/wp-graphql/pull/1992)): Fixes bug that caused conflict with the AmpWP plugin.
- ([#1994](https://github.com/wp-graphql/wp-graphql/pull/1994)): Fixes bug where querying a node by uri could return a node of a different post type.
- ([#1997](https://github.com/wp-graphql/wp-graphql/pull/1997)): Fixes bug where Enums could be generated with no values when a taxonomy was set to show in GraphQL but it's associated post_type(s) are not shown in graphql.


= 1.5.1 =

**Chores / Bugfixes**

- ([#1987](https://github.com/wp-graphql/wp-graphql/pull/1987)): Fixes Relay Spec link in documentation Thanks @ramyareye!
- ([#1988](https://github.com/wp-graphql/wp-graphql/pull/1988)): Fixes docblock and paramater Type in preview filter callback. Thanks @zolon4!
- ([#1986](https://github.com/wp-graphql/wp-graphql/pull/1986)): Update WP environment variables for tesing with PHP8. Thanks @markkelnar!

**New Features**

- ([#1984](https://github.com/wp-graphql/wp-graphql/pull/1984)): Support for PHP8! No functional changes to the code, just changes to dependency declarations and test environment.
- ([#1990](https://github.com/wp-graphql/wp-graphql/pull/1990)): Adds `isTermNode` and `isContentNode` to the `UniformResourceIdentifiable` Interface



= 1.5.0 =

**Chores / Bugfixes**

- ([#1865](https://github.com/wp-graphql/wp-graphql/pull/1865)): Change `MenuItem.path` field from `nonNull` to nullable as the value can be null in WordPress. Thanks @furedal!
- ([#1978](https://github.com/wp-graphql/wp-graphql/pull/1978)): Use "docker compose" instead of docker-compose in the run-docker.sh script. Thanks @markkelnar!
- ([#1974](https://github.com/wp-graphql/wp-graphql/pull/1974)): Separates app setup and app-post-setup scripts for use in the Docker/test environment setup. Thanks @markkelnar!
- ([#1972](https://github.com/wp-graphql/wp-graphql/pull/1972)): Pushes Docker images when new releases are tagged. Thanks @markkelnar!
- ([#1970](https://github.com/wp-graphql/wp-graphql/pull/1970)): Change Docker Image names specific to the WP and PHP versions. Thanks @markkelnar!
- ([#1967](https://github.com/wp-graphql/wp-graphql/pull/1967)): Update xdebug max nesting level to allow coverage to pass with resolver instrumentation active. Thanks @markkelnar!


**New Features**

- ([#1977](https://github.com/wp-graphql/wp-graphql/pull/1977)): Allow same string to be passed for "graphql_single_name" and "graphql_plural_name" (ex: "deer" and "deer") when registering Post Types and Taxonomies. Same strings will be prefixed with "all" for plurals. Thanks @apmatthews!
- ([#1787](https://github.com/wp-graphql/wp-graphql/pull/1787)): Adds a new "ContentTypesOf. Thanks @plong0!


= 1.4.3 =

- No functional change. Version bump to fix previous deploy.

= 1.4.2 =

**Chores / Bugfixes**

- ([#1963](https://github.com/wp-graphql/wp-graphql/pull/1963)): Fixes a regression in v1.4.0 where the `uri` field on Terms was returning `null`. The issue was actually wider than that as resolvers on Object Types that implement interfaces weren't being fully respected.
- ([#1956](https://github.com/wp-graphql/wp-graphql/pull/1956)): Adds `SpaceAfterFunction` Code Sniffer rule and adjusts the codebase to respect the rule. Thanks @markkelnar!


= 1.4.1 =

**Chores / Bugfixes**

- ([#1958](https://github.com/wp-graphql/wp-graphql/pull/1958)): Fixes a regression in 1.4.0 where `register_graphql_interfaces_to_types` was broken.


= 1.4.0 =

**Chores / Bugfixes**

- ([#1951](https://github.com/wp-graphql/wp-graphql/pull/1951)): Fixes bug with the `uri` field. Some Types in the Schema had the `uri` field as nullable field and some as a non-null field. This fixes it and makes the field consistently nullable as some Nodes with a URI might have a `null` value if the node is private.
- ([#1953](https://github.com/wp-graphql/wp-graphql/pull/1953)): Fixes bug with Settings groups with underscores not showing in the Schema properly. Thanks @markkelnar!

**New Features**

- ([#1951](https://github.com/wp-graphql/wp-graphql/pull/1951)): Updates GraphQL-PHP to v14.8.0 (from 14.4.0) and Introduces the ability for Interfaces to implement other Interfaces!

= 1.3.10 =

**Chores / Bugfixes**

- ([#1940](https://github.com/wp-graphql/wp-graphql/pull/1940)): Adds Breaking Change inspector to run on new Pull Requests. Thanks @markkelnar!
- ([#1937](https://github.com/wp-graphql/wp-graphql/pull/1937)): Fixed typo in documentation. Thanks @LeonardoDB!
- ([#1923](https://github.com/wp-graphql/wp-graphql/issues/1923)): Fixed bug where User Model didn't support the databaseId field

**New Features**

- ([#1938](https://github.com/wp-graphql/wp-graphql/pull/1938)): Adds new functionality to the `register_graphql_connection()` API. Thanks @kidunot89!

= 1.3.9 =

**Chores / Bugfixes**

- ([#1902](https://github.com/wp-graphql/wp-graphql/pull/1902)): Moves more documentation into markdown. Thanks @markkelnar!
- ([#1917](https://github.com/wp-graphql/wp-graphql/pull/1917)): Updates docblock on WPObjectType. Thanks @markkelnar!
- ([#1926](https://github.com/wp-graphql/wp-graphql/pull/1926)): Removes Telemetry.
- ([#1928](https://github.com/wp-graphql/wp-graphql/pull/1928)): Fixes bug (#1864) that was causing errors when get_post_meta() was used with a null meta key.
- ([#1929](https://github.com/wp-graphql/wp-graphql/pull/1929)): Adds Github Workflow to upload schema.graphql as release asset.

**New Features**

- ([#1924](https://github.com/wp-graphql/wp-graphql/pull/1924)): Adds new `graphql_http_request_response_errors` filter. Thanks @kidunot89!
- ([#1908](https://github.com/wp-graphql/wp-graphql/pull/1908)): Adds new `graphql_pre_resolve_uri` filter, allowing 3rd parties to filter the behavior of the nodeByUri resolver. Thanks @renatonascalves!

= 1.3.8 =

**Chores / Bugfixes**

- ([#1897](https://github.com/wp-graphql/wp-graphql/pull/1897)): Fails batch requests when disabled earlier.
- ([#1893](https://github.com/wp-graphql/wp-graphql/pull/1893)): Moves more documentation into markdown. Thanks @markkelnar!

**New Features**

- ([#1897](https://github.com/wp-graphql/wp-graphql/pull/1897)): Adds new setting to set a max number of batch operations to allow per Batch request.


= 1.3.7 =

**Chores / Bugfixes**

- ([#1885](https://github.com/wp-graphql/wp-graphql/pull/1885)): Fixes regression to `register_graphql_connection` that was breaking custom connections registered by 3rd party plugins.


= 1.3.6 =

**Chores / Bugfixes**

- ([#1878](https://github.com/wp-graphql/wp-graphql/pull/1878)): Limits the x-hacker header to be output when in DEBUG mode by default. Thanks @wvffle!
- ([#1880](https://github.com/wp-graphql/wp-graphql/pull/1880)): Fixes the formatting of the modified date for Post objects. Thanks @chriszarate!
- ([#1851](https://github.com/wp-graphql/wp-graphql/pull/1851)): Update Schema Linker Github Action. Thanks @markkelnar!
- ([#1858](https://github.com/wp-graphql/wp-graphql/pull/1858)): Start migrating docs into markdown files within the repo. Thanks @markkelnar!
- ([#1856](https://github.com/wp-graphql/wp-graphql/pull/1856)): Move Schema Linter Github Action into multiple steps. Thanks @szepeviktor!

**New Features**

- ([#1872](https://github.com/wp-graphql/wp-graphql/pull/1872)): Adds new setting to the GraphQL Settings page to allow site administrators to restrict the endpoint to authenticated requests.
- ([#1874](https://github.com/wp-graphql/wp-graphql/pull/1874)): Adds new setting to the GraphQL Settings page to allow site administrators to disable Batch Queries.
- ([#1875](https://github.com/wp-graphql/wp-graphql/pull/1875)): Adds new setting to the GraphQL Settings page to allow site administrators to enable a max query depth and specify the depth.


= 1.3.5 =

**Chores / Bugfixes**

- ([#1846](https://github.com/wp-graphql/wp-graphql/pull/1846)): Fixes bug where sites with no menu locations can throw a php error in the MenuItemConnectionResolver. Thanks @markkelnar!

= 1.3.4 =

**New Features**

- ([#1834](https://github.com/wp-graphql/wp-graphql/pull/1834)): Adds new `rename_graphql_type` function that allows Types to be given a new name in the Schema. Thanks @kidunot89!
- ([#1830](https://github.com/wp-graphql/wp-graphql/pull/1830)): Adds new `rename_graphql_field_name` function that allows fields to be given re-named in the Schema. Thanks @kidunot89!

**Chores / Bugfixes**

- ([#1820](https://github.com/wp-graphql/wp-graphql/pull/1820)): Fixes bug where one test in the test suite wasn't executing properly. Thanks @markkelnar!
- ([#1817](https://github.com/wp-graphql/wp-graphql/pull/1817)): Fixes docker environment to allow xdebug to run. Thanks @markkelnar!
- ([#1833](https://github.com/wp-graphql/wp-graphql/pull/1833)): Allow specific Test Suites to be executed when running tests with Docker. Thanks @markkelnar!
- ([#1816](https://github.com/wp-graphql/wp-graphql/pull/1816)): Fixes bug where user roles without a name caused errors when building the Schema
- ([#1824](https://github.com/wp-graphql/wp-graphql/pull/1824)): Fixes bug where setting the role of tracing/query logs to "any" wasn't being respected. Thanks @toriphes!
- ([#1828](https://github.com/wp-graphql/wp-graphql/pull/1828)): Fixes bug with Term connection pagination ordering



= 1.3.3 =

**Bugfixes / Chores**

- ([#1806](https://github.com/wp-graphql/wp-graphql/pull/1806)): Fixes bug where databaseId couldn't be queried on the CommentAuthor type
- ([#1808](https://github.com/wp-graphql/wp-graphql/pull/1808)) & ([#1811](https://github.com/wp-graphql/wp-graphql/pull/1811)): Updates Schema descriptions across the board. Thanks @markkelnar!
- ([#1809](https://github.com/wp-graphql/wp-graphql/pull/1809)): Fixes bug where child terms couldn't properly be queried by URI.
- ([#1812](https://github.com/wp-graphql/wp-graphql/pull/1812)): Fixes bug where querying users in a site with many non-published authors can return 0 results.

= 1.3.2 =

**Bugfix**

- Fixes ([#1802](https://github.com/wp-graphql/wp-graphql/issues/1802)) by reversing a change to how initial post types and taxonomies are setup.

= 1.3.1 =

**Bugfix**

- patches a bug where default post types and taxonomies disappeared from the Schema

= 1.3.0 =

**Noteable changes**

Between this release and the prior release ([v1.2.6](https://github.com/wp-graphql/wp-graphql/releases/tag/v1.2.6)) includes changes to pagination under the hood.

While these releases correcting mistakes and buggy behavior, it's possible that workarounds have already been implemented either in the server or in client applications.

For example, there was a bug with `start/end` cursors being reversed for backward pagination.

If a client application were reversing the cursors to fix the issue, the reversal in the client will now _cause_ the issue.

It's recommended to test your applications against this release, _specifically_ in regards to pagination.

**Bugfixes / Chores**

- ([#1797](https://github.com/wp-graphql/wp-graphql/pull/1797)): Update test environment to allow custom permalink structures to be better tested. Moves the "show_in_graphql" setup of core post types and taxonomies into the `register_post_type_args` and `register_taxonomy_args` filters instead of modifying global filters directly.
- ([#1794](https://github.com/wp-graphql/wp-graphql/pull/1794)): Cleanup to PHPStan config. Thanks @szepeviktor!
- ([#1795](https://github.com/wp-graphql/wp-graphql/pull/1795)) and ([#1793](https://github.com/wp-graphql/wp-graphql/pull/1793)): Don't throw errors when external urls are provided as input for queries that try and resolve by uri
- ([#1792](https://github.com/wp-graphql/wp-graphql/pull/1792)): Add missing descriptions to various fields in the Schema. Thanks @markkelnar!
- ([#1791](https://github.com/wp-graphql/wp-graphql/pull/1791)): Update where `WP_GRAPHQL_URL` is defined to follow recommendation from WordPress.org.
- ([#1784](https://github.com/wp-graphql/wp-graphql/pull/1784)): Fix `UsersConnectionSearchColumnEnum` to show the proper values that were accidentally replaced.
- ([#1781](https://github.com/wp-graphql/wp-graphql/pull/1781)): Fixes various bugs related to pagination. Between this release and the v1.2.6 release the following bugs have been worked on in regards to pagination: ([#1780](https://github.com/wp-graphql/wp-graphql/pull/1780), [#1411](https://github.com/wp-graphql/wp-graphql/pull/1411), [#1552](https://github.com/wp-graphql/wp-graphql/pull/1552), [#1714](https://github.com/wp-graphql/wp-graphql/pull/1714), [#1440](https://github.com/wp-graphql/wp-graphql/pull/1440))

= 1.2.6 =

**Bugfixes / Chores**

- ([#1773](https://github.com/wp-graphql/wp-graphql/pull/1773)) Fixes multiple issues ([#1411](https://github.com/wp-graphql/wp-graphql/pull/1411), [#1440](https://github.com/wp-graphql/wp-graphql/pull/1440), [#1714](https://github.com/wp-graphql/wp-graphql/pull/1714), [#1552](https://github.com/wp-graphql/wp-graphql/pull/1552)) related to backward pagination .
- ([#1775](https://github.com/wp-graphql/wp-graphql/pull/1775)) Updates resolver for `MenuItem.children` connection to ensure the children belong to the same menu as well to prevent orphaned items from being returned.
- ([#1774](https://github.com/wp-graphql/wp-graphql/pull/1774)) Fixes bug where the `terms` connection wasn't properly being added to all Post Types that have taxonomy relationships. Thanks @toriphes!
- ([#1752](https://github.com/wp-graphql/wp-graphql/pull/1752)) Update documentation in README. Thanks @markkelnar!
- ([#1759](https://github.com/wp-graphql/wp-graphql/pull/1759)) Update WPGraphQL Includes method to be called only if composer install has been run. Helpful for contributors that have cloned the plugin locally. Thanks @rsm0128!
- ([#1760](https://github.com/wp-graphql/wp-graphql/pull/1760)) Fixes the `MediaItem.sizes` resolver. (see: [#1758](https://github.com/wp-graphql/wp-graphql/pull/1758)). Thanks @rsm0128!
- ([#1763](https://github.com/wp-graphql/wp-graphql/pull/1763)) Update `testVersion` in phpcs.xml to match required php version. Thanks @GaryJones!

= 1.2.5 =

**Bugfixes / Chores**

- ([#1748](https://github.com/wp-graphql/wp-graphql/pull/1748)) Fixes issue where installing the plugin in Trellis using Composer was causing the plugin not to load properly.

= 1.2.4 =

**Bugfixes / Chores**

- More work to fix Github -> SVN deploys. 🤦‍♂️

= 1.2.3 =

**Bugfixes / Chores**

- Addresses bug (still) causing deploys to WordPress.org to fail and not include the vendor directory.

= 1.2.2 =

**Bugfixes / Chores**

- Fixes Github workflow to deploy to WordPress.org

= 1.2.1 =

**Bugfixes / Chores**

- ([#1741](https://github.com/wp-graphql/wp-graphql/pull/1741)) Fix issue with DefaultTemplate not being registered to the Schema and throwing errors when no other templates are registered.

= 1.2.0 =

**New**

- ([#1732](https://github.com/wp-graphql/wp-graphql/pull/1732)) Add `isPrivacyPage` to the Schema for the Page type. Thanks @Marco-Daniel!

**Bugfixes / Chores**

- ([#1734](https://github.com/wp-graphql/wp-graphql/pull/1734)) Remove Composer dependencies from being versioned in Github. Update Github workflows to install dependencies for deploying to WordPress.org and uploading release assets on Github.

= 1.1.8 =

**Bugfixes / Chores**

- Fix release asset url in Github action.

= 1.1.7 =

**Bugfixes / Chores**

- Fix release upload url in Github action.

= 1.1.6 =

**Bugfixes / Chores**

- ([#1723](https://github.com/wp-graphql/wp-graphql/pull/1723)) Fix CI Schema Linter action. Thanks @szepeviktor!
- ([#1722](https://github.com/wp-graphql/wp-graphql/pull/1722)) Update PR Template message. Thanks @szepeviktor!
- ([#1730](https://github.com/wp-graphql/wp-graphql/pull/1730)) Updates redundant test configuration in Github workflow. Thanks @szepeviktor!

= 1.1.5 =

**Bugfixes / Chores**

- ([#1718](https://github.com/wp-graphql/wp-graphql/pull/1718)) Simplify the main plugin file to adhere to more modern WP plugin standards. Move the WPGraphQL class to it's own file under the src directory. Thanks @szepeviktor!
- ([#1704](https://github.com/wp-graphql/wp-graphql/pull/1704)) Fix end tags for inputs on the WPGraphQL Settings page to adhere to the w3 spec for inputs. Thanks @therealgilles!
- ([#1706](https://github.com/wp-graphql/wp-graphql/pull/1706)) Show all content types in the ContentTypeEnum, not just public ones. Thanks @ljanecek!
- ([#1699](https://github.com/wp-graphql/wp-graphql/pull/1699)) Set default value for 2nd paramater on `Tracker->get_info()` method. Thanks @SpartakusMd!

= 1.1.4 =

**Bugfixes**

- ([#1715](https://github.com/wp-graphql/wp-graphql/pull/1715)) Updates `WPGraphQL\Type\Object` namespace to be `WPGraphQL\Type\ObjectType` to play nice with newer versions of PHP where `Object` is a reserved namespace.
- ([#1711](https://github.com/wp-graphql/wp-graphql/pull/1711)) Updates regex in phpstan.neon.dist. Thanks @szepeviktor!
- ([#1719](https://github.com/wp-graphql/wp-graphql/pull/1719)) Update to backtrace that is output with graphql_debug messages to ensure it includes a `file` key in the returned array, before returning the trace. Thanks @kidunot89!

= 1.1.3 =

**Bugfixes**

- ([#1693](https://github.com/wp-graphql/wp-graphql/pull/1693)) Clear global user in the Router in case plugins have attempted to set the user before API authentication has been executed. Thanks @therealgilles!

**New**

- ([#972](https://github.com/wp-graphql/wp-graphql/pull/972)) `graphql_pre_model_data_is_private` filter was added to the Abstract Model.php allowing Model's `is_private()` check to be bypassed.


= 1.1.2 =

**Bugfixes**

- ([#1676](https://github.com/wp-graphql/wp-graphql/pull/1676)) Add a `nav_menu_item` loader to allow previous menu item IDs to work properly with WPGraphQL should they be passed to a node query (like, if the ID were persisted somewhere already)
- Update cases of menu item IDs to be `post:$id` instead of `nav_menu_item:$id`
- Update tests to test that both the old `nav_menu_item:$id` and `post:$id` work for nav menu item node queries to support previously issued IDs

= 1.1.1 =

**Bugfixes**

- ([#1670](https://github.com/wp-graphql/wp-graphql/issues/1670)) Fixes a bug with querying pages that are set as to be the posts page

= 1.1.0 =

This release centers around updating code quality by implementing [PHPStan](https://phpstan.org/) checks. PHPStan is a tool that statically analyzes PHP codebases to detect bugs. This release centers around updating Docblocks and overall code quality, and implements automated tests to check code quality on every pull request.

**New**

- Update PHPStan (Code Quality checker) to v0.12.64
- Increases PHPStan code quality checks to Level 8 (highest level).

**Bugfixes**
- ([#1653](https://github.com/wp-graphql/wp-graphql/issues/1653)) Fixes bug where WPGraphQL was explicitly setting `has_published_posts` on WP_Query but WP_Query does this under the hood already. Thanks @jmartinhoj!
- Fixes issue with Comment Model returning comments that are not associated with a Post object. Comments with no associated Post object are not public entities.
- Update docblocks to be compatible with PHPStan Level 8.
- Removed some uncalled code
- Added early returns in some places to prevent unnecessary added execution

= 1.0.5 =

**New**

- Updates GraphQL-PHP from v14.3.0 to v14.4.0
- Updates GraphQL-Relay-PHP from v0.3.1 to v0.5.0

**Bugfixes**

- Fixes a bug where CI Tests were not passing when code coverage is enabled
- ([#1633](https://github.com/wp-graphql/wp-graphql/pull/1633)) Fixes bug where Introspection Queries were showing fields with no deprecationReason as deprecated because it was outputting an empty string instead of a null value.
- ([#1627](https://github.com/wp-graphql/wp-graphql/pull/1627)) Fixes bug where fields on the Model called multiple times might weren't being set properly
- Updates Theme tests to be more resilient for WP Core updates where new themes are introduced

= 1.0.4 =

**Bugfixes**

- Fixes a regression to previews introduced by v1.0.3

= 1.0.3 =

**Bugfixes**

- ([#1623](https://github.com/wp-graphql/wp-graphql/pull/1623)): Queries for single posts will only return posts of that post_type
- ([#1624](https://github.com/wp-graphql/wp-graphql/pull/1624)): Passes Menu Item Labels through html_entity_decode

= 1.0.2 =

**Bugfixes**

- fix issue with using the count() function on potentially not-countable value
- fix bug where post_status was being checked instead of comment_status
- fix error message when restoring a comment doesn't work
- ([#1610](https://github.com/wp-graphql/wp-graphql/issues/1610)) fix check to see if current user has permission to update another Author's post. Thanks @maximilianschmidt!


**New Features**

- ([#1608](https://github.com/wp-graphql/wp-graphql/pull/1608)) move connections from each post type->contentType to be ContentNode->ContentType. Thanks @jeanfredrik!
- pass status code through as a param of the `graphql_process_http_request_response` action
- add test for mutating other authors posts

= 1.0.1 =

**Bugfixes**
- ([#1589](https://github.com/wp-graphql/wp-graphql/pull/1589)) Fixes a php type bug in TypeRegistry.php. Thanks @szepeviktor!
- [Fixes bug](https://github.com/wp-graphql/wp-graphql/compare/master...release/v1.0.1?expand=1#diff-74d71c4d1f9d84b9b0d946ca96eb875274f95d60611611d84cc01cdf6ed04021) with how GraphQL PHP Debug flags are set.
- ([#1598](https://github.com/wp-graphql/wp-graphql/pull/1598)) Fixes bug where Post Types registered with the same graphql_single_name and graphql_plural_name are the same value.
- ([#1615](https://github.com/wp-graphql/wp-graphql/issues/1615)) Fixes bug where fields added to the Schema that that were using get_post_meta() for Previews weren't always resolving properly.

**New Features**
- Adds a setting to allow users to opt-in to tracking active installs of WPGraphQL.
- Removed old docs that used to be in this repo as markdown. Docs are now written in WordPress and the wpgraphql.com is a Gatsby site built from the content in WordPress and the [code in this repo](https://github.com/wp-graphql/wpgraphql.com). Looking to contribute content to the docs? Open an issue on this repo or the wpgraphql.com repo and we'll work with you to get content updated. We have future plans to allow the community to contribute by writing content in the WordPress install, but for now, Github issues will do.

= 1.0 =

Public Stable Release.

This release contains no technical changes.

Read the announcement: [https://www.wpgraphql.com/2020/11/16/announcing-wpgraphql-v1/](https://www.wpgraphql.com/2020/11/16/announcing-wpgraphql-v1/)

Previous release notes can be found on Github: [https://github.com/wp-graphql/wp-graphql/releases](https://github.com/wp-graphql/wp-graphql/releases)
