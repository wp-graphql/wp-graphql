=== WPGraphQL IDE ===
Contributors: jasonbahl, joefusco
Tags: headless, decoupled, graphql, devtools
Requires at least: 5.7
Tested up to: 6.8
Stable tag: 4.3.0
Requires PHP: 7.4
License: GPL-3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html

GraphQL IDE for WPGraphQL

== Description ==

GraphQL IDE for WPGraphQL.

== Installation ==

== Frequently Asked Questions ==

= Where can I find the non-compressed JavaScript and CSS source code? =

The non-compressed source code for the JavaScript and CSS files is available in the following directories:

- **Scripts**: [src/ directory](https://github.com/wp-graphql/wp-graphql/tree/main/plugins/wp-graphql-ide/src)
- **Styles**: [styles/ directory](https://github.com/wp-graphql/wp-graphql/tree/main/plugins/wp-graphql-ide/styles)

You can view or download the source code directly from the GitHub repository.

= What are some of the major dependencies used in the plugin? =

The WPGraphQL IDE plugin includes several important dependencies. You can learn more about these libraries at the following links:

- **GraphQL.js**: [https://github.com/graphql/graphql-js](https://github.com/graphql/graphql-js)
- **GraphiQL**: [https://github.com/graphql/graphiql](https://github.com/graphql/graphiql)
- **Vaul**: [https://github.com/emilkowalski/vaul](https://github.com/emilkowalski/vaul)

= How does WPGraphQL IDE handle privacy and telemetry? =
WPGraphQL IDE uses the [Appsero SDK](https://appsero.com/privacy-policy) to collect telemetry data **only after user consent**. This helps improve the plugin while respecting user privacy.

== Privacy Policy ==

WPGraphQL IDE uses [Appsero](https://appsero.com) SDK to collect some telemetry data upon user's confirmation. This helps us to troubleshoot problems faster and make product improvements.

Appsero SDK **does not gather any data by default.** The SDK only starts gathering basic telemetry data **when a user allows it via the admin notice**.

Learn more about how [Appsero collects and uses this data](https://appsero.com/privacy-policy/).

== Screenshots ==

== Upgrade Notice ==

WPGraphQL IDE follows Semver versioning. Breaking changes will be documented in the Upgrade Notice section above.

== Changelog ==

= 4.3.0 =

**New Features**

* add Appsero telemetry tracking to WPGraphQL IDE ([#3765](https://github.com/wp-graphql/wp-graphql/issues/3765))

**Bug Fixes**

* sync readme.txt changelogs with releases ([#3744](https://github.com/wp-graphql/wp-graphql/issues/3744))

= 4.2.0 =

**New Features**

* **deps:** bump @babel/runtime from 7.24.1 to 7.29.2 in /plugins/wp-graphql-ide ([#3700](https://github.com/wp-graphql/wp-graphql/issues/3700))
* **deps:** bump axios from 1.7.6 to 1.14.0 in /plugins/wp-graphql-ide ([#3706](https://github.com/wp-graphql/wp-graphql/issues/3706))
* **deps:** bump basic-ftp from 5.0.5 to 5.2.0 in /plugins/wp-graphql-ide ([#3684](https://github.com/wp-graphql/wp-graphql/issues/3684))
* **deps:** bump flatted from 3.3.1 to 3.4.2 in /plugins/wp-graphql-ide ([#3689](https://github.com/wp-graphql/wp-graphql/issues/3689))
* **deps:** bump form-data from 4.0.0 to 4.0.5 in /plugins/wp-graphql-ide ([#3693](https://github.com/wp-graphql/wp-graphql/issues/3693))
* **deps:** bump http-proxy-middleware from 2.0.6 to 2.0.9 in /plugins/wp-graphql-ide ([#3702](https://github.com/wp-graphql/wp-graphql/issues/3702))
* **deps:** bump immutable from 4.3.5 to 4.3.8 in /plugins/wp-graphql-ide ([#3687](https://github.com/wp-graphql/wp-graphql/issues/3687))
* **deps:** bump lodash from 4.17.21 to 4.18.1 in /plugins/wp-graphql-ide ([#3691](https://github.com/wp-graphql/wp-graphql/issues/3691))
* **deps:** bump node-forge from 1.3.1 to 1.4.0 in /plugins/wp-graphql-ide ([#3686](https://github.com/wp-graphql/wp-graphql/issues/3686))
* **deps:** bump on-headers and compression in /plugins/wp-graphql-ide ([#3678](https://github.com/wp-graphql/wp-graphql/issues/3678))
* **deps:** bump picomatch from 2.3.1 to 2.3.2 in /plugins/wp-graphql-ide ([#3688](https://github.com/wp-graphql/wp-graphql/issues/3688))
* **deps:** bump qs and body-parser in /plugins/wp-graphql-ide ([#3696](https://github.com/wp-graphql/wp-graphql/issues/3696))
* **deps:** bump simple-git from 3.23.0 to 3.33.0 in /plugins/wp-graphql-ide ([#3675](https://github.com/wp-graphql/wp-graphql/issues/3675))
* **deps:** bump svgo from 3.2.0 to 3.3.3 in /plugins/wp-graphql-ide ([#3690](https://github.com/wp-graphql/wp-graphql/issues/3690))
* **deps:** bump the npm-prod-minor-patch group across 1 directory with 5 updates ([#3739](https://github.com/wp-graphql/wp-graphql/issues/3739))
* **deps:** bump webpack from 5.94.0 to 5.105.4 in /plugins/wp-graphql-ide ([#3677](https://github.com/wp-graphql/wp-graphql/issues/3677))
* **deps:** bump yaml from 1.10.2 to 1.10.3 in /plugins/wp-graphql-ide ([#3698](https://github.com/wp-graphql/wp-graphql/issues/3698))
* migrate WPGraphQL for ACF to monorepo ([#3581](https://github.com/wp-graphql/wp-graphql/issues/3581))

**Bug Fixes**

* **deps-dev:** bump rimraf from 5.0.10 to 6.1.3 ([#3666](https://github.com/wp-graphql/wp-graphql/issues/3666))
* resolve post by percent-encoded slug/URI when post_name is stored encoded ([#3582](https://github.com/wp-graphql/wp-graphql/issues/3582)) ([#3611](https://github.com/wp-graphql/wp-graphql/issues/3611))

= 4.1.0 =

**New Features**

* import WPGraphQL IDE into monorepo ([#3542](https://github.com/wp-graphql/wp-graphql/issues/3542))

**Bug Fixes**

* resolve all JavaScript linting errors in wp-graphql-ide ([#3548](https://github.com/wp-graphql/wp-graphql/issues/3548))

= 4.0.11 - 4.0.24 =

**Patch Changes**

* debugging release scripts

= 4.0.10 =

**Patch Changes**

* 74c832c: chore: add period to description in readme.txt

= 4.0.9 =

**Patch Changes**

* 2eab1a7: chore: update license format in readme.txt to GPL-3.0

= 4.0.8 =

**Patch Changes**

* f610132: fix: remove duplicate git tag creation in release workflow

= 4.0.7 =

**Patch Changes**

* bf627cc: Fixed an issue with plugin updates not appearing on WordPress.org
* b47b46d: ci: attempt to fix GitHub actions auto deploy to wp.org.

= 4.0.6 =

**Patch Changes**

* d1df1d4: chore: update tested WordPress version to 6.8

= 4.0.5 =

**Patch Changes**

* c6cfbc1: fix: linting tooltips are now visible when using the IDE in the drawer
* 6500ef3: fix: broken query composer by adding missing import statements for AbstractArgView and FieldView components. Props to @hacknug for the fix!

= 4.0.4 =

**Patch Changes**

* b4d7302: Test

= 4.0.3 =

**Patch Changes**

* fd9d099: chore: set workflow environment

= 4.0.2 =

**Patch Changes**

* a2b5fbd: - chore: Bump supported WordPress version

= 4.0.1 =

**Patch Changes**

* 477a555: ### Added

* Introduced `wp_localize_escaped_data()` function for recursively escaping data before localizing it in WordPress. This ensures safe output of strings, URLs, integers, and nested arrays when passing data to JavaScript, using native WordPress functions like `wp_kses_post()` and `esc_url()`.

**Improved**

* Enhanced security by ensuring all localized data is properly sanitized before being passed to `wp_localize_script()`, preventing potential XSS vulnerabilities and ensuring safe use of dynamic data in JavaScript.

* 4da3973: - chore: Bump the npm_and_yarn group across 1 directory with 7 updates

= 4.0.0 =

**Major Changes**

* eda911d: Updated the plugin's custom filter and action names to be consistent across the plugin

**Patch Changes**

* eda911d: Fixed bug where credentials were being sent in the headers unnecessarily under certain conditions
