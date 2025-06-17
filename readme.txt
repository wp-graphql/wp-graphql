=== WPGraphQL ===
Contributors: jasonbahl, tylerbarnes1, ryankanner, chopinbach, kidunot89, justlevine
Tags: GraphQL, Headless, REST API, Decoupled, React
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.3.3
License: GPL-3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WPGraphQL adds a flexible and powerful GraphQL API to WordPress, enabling efficient querying and interaction with your site's data.

=== Description ===

WPGraphQL is a free, open-source WordPress plugin that provides an extendable GraphQL schema and API for any WordPress site.

**Get Started**

1. Install WPGraphQL: `wp plugin install wp-graphql --activate`
2. Try it out: [Live Demo](https://repl.wpgraphql.com)
3. Read the [Quick Start Guide](https://wpgraphql.com/docs/quick-start).
4. Join the [Community on Discord](https://discord.gg/AGVBqqyaUY) and [Star the Repo](https://github.com/wp-graphql/wp-graphql)!

**Key Features**

- **Flexible API**: Query posts, pages, custom post types, taxonomies, users, and more.
- **Extendable Schema**: Easily add functionality with WPGraphQL’s API, enabling custom integrations.
- **Compatible with Modern Frameworks**: Works seamlessly with [Next.js](https://vercel.com/guides/wordpress-with-vercel), [Astro](https://docs.astro.build/en/guides/cms/wordpress/), [SvelteKit](https://www.okupter.com/blog/headless-wordpress-graphql-sveltekit), and more.
- **Optimized Performance**: Fetch exactly the data you need in a single query. Boost performance with [WPGraphQL Smart Cache](https://github.com/wp-graphql/wp-graphql-smart-cache).

WPGraphQL is becoming a [Canonical Plugin](https://wordpress.org/news/2024/10/wpgraphql/) on WordPress.org, ensuring long-term support and a growing community of users and contributors.

= Upgrading =

It is recommended that anytime you want to update WPGraphQL that you get familiar with what's changed in the release.

WPGraphQL publishes [release notes on Github](https://github.com/wp-graphql/wp-graphql/releases).

WPGraphQL has been following Semver practices for a few years. We will continue to follow Semver and let version numbers communicate meaning. The summary of Semver versioning is as follows:

- *MAJOR* version when you make incompatible API changes,
- *MINOR* version when you add functionality in a backwards compatible manner, and
- *PATCH* version when you make backwards compatible bug fixes.

You can read more about the details of Semver at semver.org

== Frequently Asked Questions ==

= How is WPGraphQL funded? =
WPGraphQL is free and open-source. It is supported by contributors, backers, and sponsors, including Automattic, which provides significant support as WPGraphQL becomes a Canonical Plugin.

Learn more about supporting WPGraphQL on [Open Collective](https://opencollective.com/wp-graphql).

= Can I use WPGraphQL with xx JavaScript framework? =
Yes! WPGraphQL works with any client that can make HTTP requests to the GraphQL endpoint. It integrates seamlessly with frameworks like [Next.js](https://vercel.com/guides/wordpress-with-vercel), [Gatsby](https://gatsbyjs.com), [Astro](https://docs.astro.build/en/guides/cms/wordpress/), and more.

= Where can I get support? =
You can join the WPGraphQL [Discord community](https://discord.gg/AGVBqqyaUY) for support, discussions, and announcements.

= How does WPGraphQL handle privacy and telemetry? =
WPGraphQL uses the [Appsero SDK](https://appsero.com/privacy-policy) to collect telemetry data **only after user consent**. This helps improve the plugin while respecting user privacy.

== Privacy Policy ==

WPGraphQL uses [Appsero](https://appsero.com) SDK to collect some telemetry data upon user's confirmation. This helps us to troubleshoot problems faster and make product improvements.

Appsero SDK **does not gather any data by default.** The SDK starts gathering basic telemetry data **only when a user allows it via the admin notice**.

Learn more about how [Appsero collects and uses this data](https://appsero.com/privacy-policy/).

Learn more about how [Appsero collects and uses this data](https://appsero.com/privacy-policy/).

== Upgrade Notice ==

= 2.0.0 =

**BREAKING CHANGE UPDATE**

This is a major update that drops support for PHP versions below 7.4 and WordPress versions below 6.0.

We've written more about the update here:

- https://www.wpgraphql.com/2024/12/16/wpgraphql-v2-0-is-coming-heres-what-you-need-to-know
- https://www.wpgraphql.com/2024/12/16/wpgraphql-v2-0-technical-update-breaking-changes

= 1.32.0 =

In <a href="https://github.com/wp-graphql/wp-graphql/pull/3293">#3293</a> a bug was fixed in how the `MediaDetails.file` field resolves. The previous behavior was a bug, but might have been used as a feature. If you need the field to behave the same as it did prior to this bugfix, you can [follow the instructions here](https://github.com/wp-graphql/wp-graphql/pull/3293) to override the field's resolver to how it worked before.

= 1.30.0 =

This release includes a new feature to implement a SemVer-compliant update checker, which will prevent auto-updates for major releases that include breaking changes.

It also exposes the `EnqueuedAsset.group` and `EnqueuedScript.location` fields to the schema. Additionally, it adds a WPGraphQL Extensions page to the WordPress admin.

There are no known breaking changes in this release, however, we recommend testing on staging servers to ensure the changes don't negatively impact your projects.

= 1.28.0 =

This release contains an internal refactor for how the Type Registry is generated which should lead to significant performance improvements for most users.

While there are no intentional breaking changes, because this change impacts every user we highly recommend testing this release thoroughly on staging servers to ensure the changes don't negatively impact your projects.

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

= 2.3.3 =

**Bug Fixes**

* fix: update skipped since tags (https://github.com/jasonbahl/automation-tests/pull/3372)
* fix: check for preloaded AppContext::get_loader() (https://github.com/jasonbahl/automation-tests/pull/3384)
* fix: cleanup  logic (https://github.com/jasonbahl/automation-tests/pull/3383)

**Other Changes**

* chore: improve type safety of  and schema registration (https://github.com/jasonbahl/automation-tests/pull/3382)
* refactor: cleanup  class to reduce complexity and improve type safety (https://github.com/jasonbahl/automation-tests/pull/3381)
* perf: refactor  to lazy-load dataloaders (https://github.com/jasonbahl/automation-tests/pull/3380)
* chore: update Composer dev-deps and PHPCs ruleset (https://github.com/jasonbahl/automation-tests/pull/3379)
* chore: expose array shape for   (https://github.com/jasonbahl/automation-tests/pull/3374)
* chore: expose array shapes for register_graphql_enum_type()  (https://github.com/jasonbahl/automation-tests/pull/3373)
* chore: narrow/fix php types on WPGraphQL, Server, Utils namespaces (https://github.com/jasonbahl/automation-tests/pull/3368)

= 2.3.2 =

**Other Changes**

* chore: improve type safety of  and schema registration (https://github.com/jasonbahl/automation-tests/pull/3382)
* refactor: cleanup  class to reduce complexity and improve type safety (https://github.com/jasonbahl/automation-tests/pull/3381)
* perf: refactor  to lazy-load dataloaders (https://github.com/jasonbahl/automation-tests/pull/3380)
* chore: update Composer dev-deps and PHPCs ruleset (https://github.com/jasonbahl/automation-tests/pull/3379)

= 2.3.1 =

**Other Changes**

* chore: expose array shape for   (https://github.com/jasonbahl/automation-tests/pull/3374)
* chore: expose array shapes for register_graphql_enum_type()  (https://github.com/jasonbahl/automation-tests/pull/3373)
* chore: narrow/fix php types on WPGraphQL, Server, Utils namespaces (https://github.com/jasonbahl/automation-tests/pull/3368)

= 2.3.0 =

**New Features**

* feat: lazy loading fields for Object Types and Interface Types (https://github.com/jasonbahl/automation-tests/pull/3356)
* feat: Update Enum Type descriptions (https://github.com/jasonbahl/automation-tests/pull/3355)

**Bug Fixes**

* fix: don't initialize  twice in class constructor (https://github.com/jasonbahl/automation-tests/pull/3369)
* fix: cleanup Model fields for better source-of-truth and type-safety. (https://github.com/jasonbahl/automation-tests/pull/3363)
* fix: bump  and remove 7.3 references (https://github.com/jasonbahl/automation-tests/pull/3360)

**Other Changes**

* chore: improve type-safety for  class (https://github.com/jasonbahl/automation-tests/pull/3367)
* chore: add array shapes to  and  (https://github.com/jasonbahl/automation-tests/pull/3366)
* chore: inline (non-breaking) native return types (https://github.com/jasonbahl/automation-tests/pull/3362)
* chore: implement array shapes for  (https://github.com/jasonbahl/automation-tests/pull/3364)
* chore: Test compatibility with WordPress 6.8 (https://github.com/jasonbahl/automation-tests/pull/3361)
* ci: trigger Codeception workflow more often (https://github.com/jasonbahl/automation-tests/pull/3359)
* chore: Update Composer deps (https://github.com/jasonbahl/automation-tests/pull/3358)

= 2.2.0 =

**New Features**

* feat: add support for graphql_description on register_post_type and register_taxonomy (https://github.com/jasonbahl/automation-tests/pull/3346)

**Other Changes**

* chore: update  placeholder that didn't properly get replaced during release (https://github.com/jasonbahl/automation-tests/pull/3349)
* chore: update interface descriptions (https://github.com/jasonbahl/automation-tests/pull/3347)

= 2.1.1 =

**Bug Fixes**

* fix: Avoid the deprecation warning when sending null header values (https://github.com/jasonbahl/automation-tests/pull/3338)

**Other Changes**

* chore: update README's for github workflows (https://github.com/jasonbahl/automation-tests/pull/3343)
* chore: update cursor rules to use .cursor/rules instead of .cursorrules (https://github.com/jasonbahl/automation-tests/pull/3333)
* chore: add WPGraphQL IDE to the extensions page (https://github.com/jasonbahl/automation-tests/pull/3332)

= 2.1.0 =

**New Features**

- [#3320](https://github.com/wp-graphql/wp-graphql/pull/3320): feat: add filter to Request::is_valid_http_content_type to allow for custom content types with POST method requests
**Chores / Bugfixes**

- [#3314](https://github.com/wp-graphql/wp-graphql/pull/3314): fix: use version_compare to simplify incompatible dependent check
- [#3316](https://github.com/wp-graphql/wp-graphql/pull/3316): docs: update changelog and upgrade notice
- [#3325](https://github.com/wp-graphql/wp-graphql/pull/3325): docs: update quick-start.md
- [#3190](https://github.com/wp-graphql/wp-graphql/pull/3190): docs: add developer docs for `AbstractConnectionResolver`

= 2.0.0 =

**BREAKING CHANGE UPDATE**

This is a major update that drops support for PHP versions below 7.4 and WordPress versions below 6.0.

We've written more about the update here:

- https://www.wpgraphql.com/2024/12/16/wpgraphql-v2-0-is-coming-heres-what-you-need-to-know
- https://www.wpgraphql.com/2024/12/16/wpgraphql-v2-0-technical-update-breaking-changes

= 1.32.1 =

**Chores / Bugfixes**

- [#3308](https://github.com/wp-graphql/wp-graphql/pull/3308): fix: update term mutation was preventing terms from removing the parentId


= 1.32.0 =

**New Features**

- [#3294](https://github.com/wp-graphql/wp-graphql/pull/3294): feat: introduce new fields for getting mediaItem files and filePaths

**Chores / Bugfixes**

- update stable tag

= 1.31.0 =

**New Features**

- [#3278](https://github.com/wp-graphql/wp-graphql/pull/3278): feat: add option to provide custom file path for static schemas when using the `wp graphql generate-static-schema` command

**Chores / Bugfixes**

- [#3284](https://github.com/wp-graphql/wp-graphql/pull/3284): fix: fix: Updated docs link for example of hierarchical data
- [#3283](https://github.com/wp-graphql/wp-graphql/pull/3283): fix: Error in update checker when WPGraphQL is active as an mu-plugin
- [#3293](https://github.com/wp-graphql/wp-graphql/pull/3293): fix: correct the resolver for the MediaDetails.file field to return the file name
- [#3299](https://github.com/wp-graphql/wp-graphql/pull/3299): chore: restore excluded PHPCS rules
- [#3301](https://github.com/wp-graphql/wp-graphql/pull/3301): fix: React backwards-compatibility with WP < 6.6
- [#3302](https://github.com/wp-graphql/wp-graphql/pull/3302): chore: update NPM dependencies
- [#3297](https://github.com/wp-graphql/wp-graphql/pull/3297): fix: typo in `Extensions\Registry\get_extensions()` method name
- [#3303](https://github.com/wp-graphql/wp-graphql/pull/3303): chore: cleanup git cache
- [#3298](https://github.com/wp-graphql/wp-graphql/pull/3298): chore: submit GF, Rank Math, and Headless Login plugins
- [#3287](https://github.com/wp-graphql/wp-graphql/pull/3287): chore: fixes the syntax of the readme.txt so that the short description is shown on WordPress.org
- [#3284](https://github.com/wp-graphql/wp-graphql/pull/3284): fix: Updated docs link for example of hierarchical data

= 1.30.0 =

**Chores / Bugfixes**

- [#3250](https://github.com/wp-graphql/wp-graphql/pull/3250): fix: receiving post for Incorrect uri
- [#3268](https://github.com/wp-graphql/wp-graphql/pull/3268): ci: trigger PR workflows on release/* branches
- [#3267](https://github.com/wp-graphql/wp-graphql/pull/3267): chore: fix bleeding edge/deprecated PHPStan smells [first pass]
- [#3270](https://github.com/wp-graphql/wp-graphql/pull/3270): build(deps): bump the npm_and_yarn group across 1 directory with 3 updates
- [#3271](https://github.com/wp-graphql/wp-graphql/pull/3271): fix: default cat should not be added when other categories are added

**New Features**

- [#3251](https://github.com/wp-graphql/wp-graphql/pull/3251): feat: implement SemVer-compliant update checker
- [#3196](https://github.com/wp-graphql/wp-graphql/pull/3196): feat: expose EnqueuedAsset.group and EnqueuedScript.location to schema
- [#3188](https://github.com/wp-graphql/wp-graphql/pull/3188): feat: Add WPGraphQL Extensions page to the WordPress admin

= 1.29.3 =

**Chores / Bugfixes**

- [#3245](https://github.com/wp-graphql/wp-graphql/pull/3245): fix: update appsero/client to v2.0.4 to prevent conflicts with WP6.7
- [#3243](https://github.com/wp-graphql/wp-graphql/pull/3243): chore: fix Composer autoloader for WPGraphQL.php
- [#3242](https://github.com/wp-graphql/wp-graphql/pull/3242): chore: update Composer dev deps
- [#3235](https://github.com/wp-graphql/wp-graphql/pull/3235): chore: general updates to README.md and readme.txt
- [#3234](https://github.com/wp-graphql/wp-graphql/pull/3234): chore: update quick-start.md to provide more clarity around using wpackagist


= 1.29.2 =

**Chores / Bugfixes**

- fix: move assets/blueprint.json under .wordpress-org directory

= 1.29.1 =

**Chores / Bugfixes**

- [#3226](https://github.com/wp-graphql/wp-graphql/pull/3226): chore: add blueprint.json so WPGraphQL can be demo'd with a live preview on WordPress.org
- [#3218](https://github.com/wp-graphql/wp-graphql/pull/3218): docs: update upgrading.md to highlight how breaking change releases will be handled
- [#3214](https://github.com/wp-graphql/wp-graphql/pull/3214): fix: lazy-resolve Post.sourceUrl and deprecate Post.sourceUrlsBySize
- [#3224](https://github.com/wp-graphql/wp-graphql/pull/3224): chore(deps-dev): bump symfony/process from 5.4.40 to 5.4.46 in the composer group
- [#3219](https://github.com/wp-graphql/wp-graphql/pull/3219): test: add tests for querying different sizes of media items
- [#3229](https://github.com/wp-graphql/wp-graphql/pull/3229): fix: Deprecated null value warning in titleRendered callback


= 1.29.0 =

**New Features**

- [#3208](https://github.com/wp-graphql/wp-graphql/pull/3208): feat: expose commenter edge fields
- [#3207](https://github.com/wp-graphql/wp-graphql/pull/3207): feat: introduce get_graphql_admin_notices and convert AdminNotices class to a singleton

**Chores / Bugfixes**

- [#3213](https://github.com/wp-graphql/wp-graphql/pull/3213): chore(deps): bump the npm_and_yarn group across 1 directory with 4 updates
- [#3212](https://github.com/wp-graphql/wp-graphql/pull/3212): chore(deps): bump dset from 3.1.3 to 3.1.4 in the npm_and_yarn group across 1 directory
- [#3211](https://github.com/wp-graphql/wp-graphql/pull/3211): chore: add LABELS.md
- [#3201](https://github.com/wp-graphql/wp-graphql/pull/3201): fix: ensure connectedTerms returns terms for the specified taxonomy only
- [#3199](https://github.com/wp-graphql/wp-graphql/pull/3199): chore(deps-dev): bump the npm_and_yarn group across 1 directory with 2 updates


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
