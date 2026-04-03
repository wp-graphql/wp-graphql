=== WPGraphQL Persisted Query URLs ===
Contributors: jasonbahl
Tags: WPGraphQL, GraphQL, API, Smart Cache, Persisted Queries, Headless
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0-beta.1
Requires WPGraphQL: 2.0.0
WPGraphQL Tested Up To: 2.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Experimental beta — not for production. Persisted GraphQL operations at permalink-style GET URLs (not long query strings) for URL-based edge purge; extends WPGraphQL Smart Cache.

== Description ==

**WPGraphQL Persisted Query URLs** is an experimental plugin that works with **WPGraphQL** and **WPGraphQL Smart Cache**. It exposes persisted operations at clean paths (for example `/graphql/persisted/{queryHash}`) so hosts that only support **URL-based cache purging** can invalidate the right responses when Smart Cache fires purge events.

This is **not** a drop-in replacement for Apollo Automatic Persisted Queries (APQ). Clients must follow the flow described in the plugin documentation.

The plugin is developed in the [WPGraphQL monorepo](https://github.com/wp-graphql/wp-graphql). Issues and releases use the main repository.

**Requirements**

* WordPress 6.0+
* PHP 7.4+
* WPGraphQL 2.0.0+
* WPGraphQL Smart Cache (active)

== Installation ==

1. Install and activate **WPGraphQL** and **WPGraphQL Smart Cache**.
2. Install and activate this plugin.
3. Visit **Settings → Permalinks** and click **Save Changes** to flush rewrite rules.

== Documentation ==

* [Plugin README (GitHub)](https://github.com/wp-graphql/wp-graphql/tree/main/plugins/wp-graphql-pqu#readme)
* [Status & scope](https://github.com/wp-graphql/wp-graphql/tree/main/plugins/wp-graphql-pqu/docs/STATUS.md)
* [Protocol spec](https://github.com/wp-graphql/wp-graphql/tree/main/plugins/wp-graphql-pqu/docs/SPEC.md)

== Frequently Asked Questions ==

= Is this ready for production? =

No. This is an **experimental beta**. Breaking changes are likely before 1.0.0.

= Does it work without WPGraphQL Smart Cache? =

No. Smart Cache must be installed and active.

== Changelog ==

See [CHANGELOG.md](https://github.com/wp-graphql/wp-graphql/blob/main/plugins/wp-graphql-pqu/CHANGELOG.md) in the monorepo for release history.

== Upgrade Notice ==

= 0.1.0-beta.1 =

Initial public beta. Experimental only; requires WPGraphQL 2.0+ and WPGraphQL Smart Cache. Do not use on production sites yet.
