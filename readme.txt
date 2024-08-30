=== WPGraphQL ===
Contributors: jasonbahl, tylerbarnes1, ryankanner, chopinbach, kidunot89, justlevine
Tags: GraphQL, Headless, REST API, Decoupled, React
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.1
Stable tag: 1.28.1
License: GPL-3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

=== Short Description ===

WPGraphQL adds a flexible and powerful GraphQL API to WordPress, enabling efficient querying and interaction with your site's data.

=== Description ===

WPGraphQL is a free, open-source WordPress plugin that provides an extendable GraphQL schema and API for any WordPress site.

Below are some links to help you get started with WPGraphQL

- <a href="https://www.wpgraphql.com" target="_blank">WPGraphQL.com</a>
- <a href="https://wpgraphql.com/docs/quick-start" target="_blank">Quick Start Guide</a>
- <a href="https://wpgraphql.com/docs/intro-to-graphql" target="_blank">Intro to GraphQL</a>
- <a href="https://wpgraphql.com/docs/intro-to-wordpress" target="_blank">Intro to WordPress</a>
- <a href="https://discord.gg/AGVBqqyaUY" target="_blank">Join the WPGraphQL community on Discord</a>

<iframe width="560" height="315" src="https://www.youtube.com/embed/TiDD8k-_gzo?si=DIfA4HKRFHfZ2STu" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>

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

= 1.28.0 =

This release contains an internal refactor for how the Type Registry is generated which should lead to significant performance improvements for most users.

While there are no intentional breaking changes, because this change impacts every suser we highly recommend testing this release thoroughly on staging servers to ensure the changes don't negatively impact your projects.

= 1.26.0 =

This release refactors some code in the AbstractConnectionResolver with an aim at making it more efficient and easier to extend. While we believe there are no breaking changes and have tested against popular extensions such as WPGraphQL Headless Login, WPGraphQL Gravity Forms, WPGraphQL Rank Math and others, we recommend running your own tests on a staging site to confirm that there are no regresssions caused by the refactoring.

= 1.25.0 =

This release includes a fix to a regression in the v1.24.0. Users impacted by the regression in 1.24.0 included, but are not necessarily limited to, users of the WPGraphQL for WooCommerce extension.

= 1.24.0 =

The AbstractConnectionResolver has undergone some refactoring. Some methods using `snakeCase` have been deprecated in favor of their `camel_case` equivalent. While we've preserved the deprecated methods to prevent breaking changes, you might begin seeing PHP notices about the deprecations. Any plugin that extends the AbstractConnectionResolver should update the following methods:

- `getSource` -> `get_source`
- `getContext` -> `get_context`
- `getInfo` -> `get_info`
- `getShouldExecute` -> `get_should_execute`
- `getLoader` -> `getLoader`

= 1.16.0 =

**WPGraphQL Smart Cache**
For WPGraphQL Smart Cache users, you should update WPGraphQL Smart Cache to v1.2.0 when updating
WPGraphQL to v1.16.0 to ensure caches continue to purge as expected.

**Cursor Pagination Updates**
This version fixes some behaviors of Cursor Pagination which _may_ lead to behavior changes in your application.

As with any release, we recommend you test in staging environments. For this release, specifically any
queries you have using pagination arguments (`first`, `last`, `after`, `before`).

= 1.14.6 =

This release includes a security patch. It's recommended to update as soon as possible.

If you're unable to update to the latest version, we have a snippet you can add to your site.

You can read more about it here: https://github.com/wp-graphql/wp-graphql/security/advisories/GHSA-cfh4-7wq9-6pgg

= 1.13.0 =

The `ContentRevisionUnion` Union has been removed, and the `RootQuery.revisions` and `User.revisions` connections that used to resolve to this Type now resolve to the `ContentNode` Interface type.

This is _technically_ a Schema Breaking change, however the behavior for most users querying these fields should remain the same.

For example, this query worked before, and still works now:

```graphql
{
  viewer {
    revisions {
      nodes {
        __typename
        ... on Post {
          id
          uri
          isRevision
        }
        ... on Page {
          id
          uri
          isRevision
        }
      }
    }
  }
  revisions {
    nodes {
      __typename
      ... on Post {
        id
        uri
        isRevision
      }
      ... on Page {
        id
        uri
        isRevision
      }
    }
  }
}
```

If you were using a fragment to reference: `...on UserToContentRevisionUnionConnection` or `...on RootQueryToContentRevisionUnionConnection` you would need to update those references to `...on UserToRevisionsConnection` and `...on RootQueryToRevisionsConnection` respectively.

= 1.12.0 =

This release removes the `ContentNode` and `DatabaseIdentifier` interfaces from the `NodeWithFeaturedImage` Interface.

This is considered a breaking change for client applications using a `...on NodeWithFeaturedImage` fragment that reference fields applied by those interfaces. If you have client applications doing this (or are unsure if you do) you can use the following filter to bring back the previous behavior:

```php
add_filter( 'graphql_wp_interface_type_config', function( $config ) {
	if ( $config['name'] === 'NodeWithFeaturedImage' ) {
		$config['interfaces'][] = 'ContentNode';
		$config['interfaces'][] = 'DatabaseIdentifier';
	}
	return $config;
}, 10, 1 );
```

= 1.10.0 =

PR ([#2490](https://github.com/wp-graphql/wp-graphql/pull/2490)) fixes a bug that some users were
using as a feature.

When a page is marked as the "Posts Page" WordPress does not resolve that page by URI, and this
bugfix no longer will resolve that page by URI.

You can [read more](https://github.com/wp-graphql/wp-graphql/issues/2486#issuecomment-1232169375)
about why this change was made and find a snippet of code that will bring the old functionality back
if you've built features around it.


= 1.9.0 =

There are 2 changes that **might** require action when updating to 1.9.0.


1. ([#2464](https://github.com/wp-graphql/wp-graphql/pull/2464))

When querying for a `nodeByUri`, if your site has the "page_for_posts" setting configured, the behavior of the `nodeByUri` query for that uri might be different for you.

Previously a bug caused this query to return a "Page" type, when it should have returned a "ContentType" Type.

The bug fix might change your application if you were using the bug as a feature.



2. ([#2457](https://github.com/wp-graphql/wp-graphql/pull/2457))

There were a lot of bug fixes related to connections to ensure they behave as intended. If you were querying lists of data, in some cases the data might be returned in a different order than it was before.

For example, using the "last" input on a Comment or User query should still return the same nodes, but in a different order than before.

This might cause behavior you don't want in your application because you had coded around the bug. This change was needed to support proper backward pagination.



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

= 1.28.1 =

**Chores / Bugfixes**

- [#3189](https://github.com/wp-graphql/wp-graphql/pull/3189): fix: [regression] missing placeholder in $wpdb->prepare() call

= 1.28.0 =

**Upgrade Notice**

This release contains an internal refactor for how the Type Registry is generated which should lead to significant performance improvements for most users. While there is no known breaking changes, because this change impacts every user we highly recommend testing this release thoroughly on staging servers to ensure the changes don't negatively impact your projects.

**New Features**

- [#3172](https://github.com/wp-graphql/wp-graphql/pull/3172): feat: only `eagerlyLoadType` on introspection requests.

**Chores / Bugfixes**

- [#3181](https://github.com/wp-graphql/wp-graphql/pull/3181): ci: replace `docker-compose` commands with `docker compose`
- [#3182](https://github.com/wp-graphql/wp-graphql/pull/3182): ci: test against WP 6.6
- [#3183](https://github.com/wp-graphql/wp-graphql/pull/3183): fix: improve performance of SQL query in the user loader

= 1.27.2 =

**Chores / Bugfixes**

- [#3167](https://github.com/wp-graphql/wp-graphql/pull/3167): fix: missing .svg causing admin_menu not to be registered

= 1.27.1 =

**Chores / Bugfixes**

- [#3066](https://github.com/wp-graphql/wp-graphql/pull/3066): fix: merge query arg arrays instead of overriding.
- [#3151](https://github.com/wp-graphql/wp-graphql/pull/3151): fix: update dev-deps and fix `WPGraphQL::get_static_schema()`
- [#3152](https://github.com/wp-graphql/wp-graphql/pull/3152): fix: handle regression when implementing interface with identical args.
- [#3153](https://github.com/wp-graphql/wp-graphql/pull/3153): chore(deps-dev): bump composer/composer from 2.7.6 to 2.7.7 in the composer group across 1 directory
- [#3155](https://github.com/wp-graphql/wp-graphql/pull/3155): chore(deps-dev): bump the npm_and_yarn group across 1 directory with 2 updates
- [#3160](https://github.com/wp-graphql/wp-graphql/pull/3160): chore: Update branding assets
- [#3162](https://github.com/wp-graphql/wp-graphql/pull/3162): fix: set_query_arg should not merge args


= 1.27.0 =

**New Features**

- [#3143](https://github.com/wp-graphql/wp-graphql/pull/3143): feat: Enhance tab state management with query arguments and localStorage fallback

**Chores / Bugfixes**

- [#3139](https://github.com/wp-graphql/wp-graphql/pull/3139): fix: `$settings_fields` param on "graphql_get_setting_section_field_value" filter not passing the correct type
- [#3137](https://github.com/wp-graphql/wp-graphql/pull/3137): fix: WPGraphQL Settings page fails to load when "graphiql_enabled" setting is "off"
- [#3133](https://github.com/wp-graphql/wp-graphql/pull/3133): build: clean up dist
- [#3146](https://github.com/wp-graphql/wp-graphql/pull/3146): test: add e2e test coverage for tabs in the settings page

= 1.26.0 =

**New Features**

- [#3125](https://github.com/wp-graphql/wp-graphql/pull/3125): refactor: improve query handling in AbstractConnectionResolver
  - new: `graphql_connection_pre_get_query` filter
  - new: `AbstractConnectionResolver::is_valid_query_class()`
  - new: `AbstractConnectionResolver::get_query()`
  - new: `AbstractConnectionResolver::get_query_class()`
  - new: `AsbtractConnectionResolver::query_class()`
  - new: `AbstractConnectionResolver::$query_class`
- [#3124](https://github.com/wp-graphql/wp-graphql/pull/3124): refactor: split `AbstractConnectionResolver::get_args()` and `::get_query_args()` into `::prepare_*()` methods
- [#3123](https://github.com/wp-graphql/wp-graphql/pull/3123): refactor: split `AbstractConnectionResolver::get_ids()` into `::prepare_ids()`
- [#3121](https://github.com/wp-graphql/wp-graphql/pull/3121): refactor: split `AbstractConnectionResolver::get_nodes()` and `get_edges()` into `prepare_*()` methods
- [#3120](https://github.com/wp-graphql/wp-graphql/pull/3120): refactor: wrap `AbstractConnectionResolver::is_valid_model()` in `::get_is_valid_model()`

**Chores / Bugfixes**

- [#3125](https://github.com/wp-graphql/wp-graphql/pull/3125): refactor: improve query handling in AbstractConnectionResolver
  - Implement PHPStan Generic Type
  - Update generic Exceptions to InvariantViolation
- [#3127](https://github.com/wp-graphql/wp-graphql/pull/3127): chore: update references to the WPGraphQL Slack Community to point to the new WPGraphQL Discord community instead.
- [#3122](https://github.com/wp-graphql/wp-graphql/pull/3122): chore: relocate `AbstractConnectionResolver::is_valid_offset()` with other abstract methods.

= 1.25.0 =

**New Features**

- [#3104](https://github.com/wp-graphql/wp-graphql/pull/3104): feat: add `AbsractConnectionResolver::pre_should_execute()`. Thanks @justlevine!

**Chores / Bugfixes**
- [#3104](https://github.com/wp-graphql/wp-graphql/pull/3104): refactor: `AbstractConnectionResolver::should_execute()` Thanks @justlevine!
- [#3112](https://github.com/wp-graphql/wp-graphql/pull/3104): fix: fixes a regression from v1.24.0 relating to field arguments defined on Interfaces not being properly merged onto Object Types that implement the interface. Thanks @kidunot89!
- [#3114](https://github.com/wp-graphql/wp-graphql/pull/3114): fix: node IDs not showing in the Query Analyzer / X-GraphQL-Keys when using DataLoader->load_many()
- [#3116](https://github.com/wp-graphql/wp-graphql/pull/3116): chore: Update WPGraphQLTestCase to v3. Thanks @kidunot89!

= 1.24.0 =

**New Features**

- [#3084](https://github.com/wp-graphql/wp-graphql/pull/3084): perf: refactor PluginConnectionResolver to only fetch plugins once. Thanks @justlevine!
- [#3088](https://github.com/wp-graphql/wp-graphql/pull/3088): refactor: improve loader handling in AbstractConnectionResolver. Thanks @justlevine!
- [#3087](https://github.com/wp-graphql/wp-graphql/pull/3087): feat: improve query amount handling in AbstractConnectionResolver. Thanks @justlevine!
- [#3086](https://github.com/wp-graphql/wp-graphql/pull/3086): refactor: add AbstractConnectionResolver::get_unfiltered_args() public getter. Thanks @justlevine!
- [#3085](https://github.com/wp-graphql/wp-graphql/pull/3085): refactor: add AbstractConnectionResolver::prepare_page_info()and only instantiate once. Thanks @justlevine!
- [#3083](https://github.com/wp-graphql/wp-graphql/pull/3083): refactor: deprecate camelCase methods in AbstractConnectionResolver for snake_case equivalents. Thanks @justlevine!

**Chores / Bugfixes**

- [#3095](https://github.com/wp-graphql/wp-graphql/pull/3095): chore: lint for superfluous whitespace. Thanks @justlevine!
- [#3100](https://github.com/wp-graphql/wp-graphql/pull/3100): fix: recursion issues with interfaces
- [#3082](https://github.com/wp-graphql/wp-graphql/pull/3082): chore: prepare ConnectionResolver classes for v2 backport


= 1.23.0 =

**New Features**

- [#3073](https://github.com/wp-graphql/wp-graphql/pull/3073): feat: expose `hasPassword` and `password` fields on Post objects. Thanks @justlevine!
- [#3091](https://github.com/wp-graphql/wp-graphql/pull/3091): feat: introduce actions and filters for GraphQL Admin Notices

**Chores / Bugfixes**

- [#3079](https://github.com/wp-graphql/wp-graphql/pull/3079): fix: GraphiQL IDE test failures
- [#3084](https://github.com/wp-graphql/wp-graphql/pull/3084): perf: refactor PluginConnectionResolver to only fetch plugins once. Thanks @justlevine!
- [#3092](https://github.com/wp-graphql/wp-graphql/pull/3092): ci: test against wp 6.5
- [#3093](https://github.com/wp-graphql/wp-graphql/pull/3093): ci: Update actions in GitHub workflows and cleanup. Thanks @justlevine!
- [#3093](https://github.com/wp-graphql/wp-graphql/pull/3093): chore: update Composer dev-deps and lint. Thanks @justlevine!


= 1.22.1 =

**Chores / Bugfixes**

- [#3067](https://github.com/wp-graphql/wp-graphql/pull/3067): fix: respect show avatar setting
- [#3063](https://github.com/wp-graphql/wp-graphql/pull/3063): fix: fixes a bug in cursor stability filters that could lead to empty order
- [#3070](https://github.com/wp-graphql/wp-graphql/pull/3070): test(3063): Adds test for [#3063](https://github.com/wp-graphql/wp-graphql/pull/3063)

= 1.22.0 =

**New Features**

- [#3044](https://github.com/wp-graphql/wp-graphql/pull/3044): feat: add `graphql_pre_resolve_menu_item_connected_node` filter
- [#3039](https://github.com/wp-graphql/wp-graphql/pull/3043): feat: add `UniformResourceIdentifiable` interface to `Comment` type
- [#3020](https://github.com/wp-graphql/wp-graphql/pull/3020): feat: introduce `graphql_query_analyzer_get_headers` filter

**Chores / Bugfixes**

- [#3062](https://github.com/wp-graphql/wp-graphql/pull/3062): ci: pin wp-browser to "<3.5" to allow automated tests to run properly
- [#3057](https://github.com/wp-graphql/wp-graphql/pull/3057): fix: `admin_enqueue_scripts` callback should expect a possible `null` value passed to it
- [#3048](https://github.com/wp-graphql/wp-graphql/pull/3048): fix: `isPostsPage` on content type
- [#3043](https://github.com/wp-graphql/wp-graphql/pull/3043): fix: return empty when filtering `menuItems` by a location with no assigned items
- [#3045](https://github.com/wp-graphql/wp-graphql/pull/3045): fix: `UsersConnectionSearchColumnEnum` values should be prefixed with `user_`

= 1.21.0 =

**New Features**

- [#3035](https://github.com/wp-graphql/wp-graphql/pull/3035): feat: provide better error when field references a type that does not exist
- [#3027](https://github.com/wp-graphql/wp-graphql/pull/3027): feat: Add register_graphql_admin_notice API and intial use to inform users of the new WPGraphQL for ACF plugin

**Chores / Bugfixes**

- [#3038](https://github.com/wp-graphql/wp-graphql/pull/3038): chore(deps-dev): bump the composer group across 1 directories with 1 update. Thanks @dependabot!
- [#3033](https://github.com/wp-graphql/wp-graphql/pull/3033): fix: php deprecation error for dynamic properties on AppContext class
- [#3031](https://github.com/wp-graphql/wp-graphql/pull/3031): fix(graphiql): Allow GraphiQL to run even if a valid schema cannot be returned. Thanks @linucks!


= 1.20.0 =

**New Features**

- [#3013](https://github.com/wp-graphql/wp-graphql/pull/3013): feat: output GRAPHQL_DEBUG message if requested amount is larger than connection limit. Thanks @justlevine!
- [#3008](https://github.com/wp-graphql/wp-graphql/pull/3008): perf: Expose graphql_should_analyze_queries as setting. Thanks @justlevine!

**Chores / Bugfixes**

- [#3022](https://github.com/wp-graphql/wp-graphql/pull/3022): chore: add @justlevine to list of contributors! 🙌 🥳
- [#3011](https://github.com/wp-graphql/wp-graphql/pull/3011): chore: update composer dev-dependencies and use php-compatibility:develop branch to 8.0+ lints. Thanks @justlevine!
- [#3010](https://github.com/wp-graphql/wp-graphql/pull/3010): chore: implement stricter PHPDoc types. Thanks @justlevine!
- [#3009](https://github.com/wp-graphql/wp-graphql/pull/3009): chore: implement stricter PHPStan config and clean up unnecessary type-guards. Thanks @justlevine!
- [#3007](https://github.com/wp-graphql/wp-graphql/pull/3007): fix: call html_entity_decode() with explicit flags and decode single-quotes. Thanks @justlevine!
- [#3006](https://github.com/wp-graphql/wp-graphql/pull/3006): fix: replace deprecated AbstractConnectionResolver::setQueryArg() call with ::set_query_arg(). Thanks @justlevine!
- [#3004](https://github.com/wp-graphql/wp-graphql/pull/3004): docs: Update using-data-from-custom-database-tables.md
- [#2998](https://github.com/wp-graphql/wp-graphql/pull/2998): docs: Update build-your-first-wpgraphql-extension.md. Thanks @Jacob-Daniel!
- [#2997](https://github.com/wp-graphql/wp-graphql/pull/2997): docs: update wpgraphql-concepts.md. Thanks @Jacob-Daniel!
- [#2996](https://github.com/wp-graphql/wp-graphql/pull/2996): fix: Field id duplicates uri field description. Thanks @marcinkrzeminski!

----

View Full Changelog: https://github.com/wp-graphql/wp-graphql/blob/develop/CHANGELOG.md
