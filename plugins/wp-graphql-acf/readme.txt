=== WPGraphQL for ACF ===
Contributors: jasonbahl
Tags: GraphQL, ACF, API, NextJS, Headless
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.3
Stable tag: 3.2.0
License: GPL-3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WPGraphQL for ACF seamlessly integrates Advanced Custom Fields with WPGraphQL.

=== Description ===

WPGraphQL for Advanced Custom Fields is a free, open-source WordPress plugin that adds ACF Fields and Field Groups to the WPGraphQL Schema.

= Create ACF Field Groups =

Create ACF Field Groups and Fields using the ACF User Interface, register them with PHP, or leverage ACF local JSON. Each field group and the fields within it can be configured to "Show in GraphQL."

= Query your fields with GraphQL =

Once your field groups and fields are configured to "Show in GraphQL," they become available in the GraphQL Schema for querying.

= Supported Field Types =

WPGraphQL for ACF provides support for most built-in field types of ACF (free & PRO) and extends support to most field types from ACF Extended (free & PRO).

== Updating ==

If you are updating from WPGraphQL for ACF v0.6.* or older, check out the [Upgrade Guide](https://www.wpgraphql.com/docs/acf/upgrade-guide)

For non-major version updates, automatic updates usually should work smoothly, but we still recommend you back up your site and test on a staging site.

Before updating WPGraphQL for ACF, review the [release notes on GitHub](https://github.com/wp-graphql/wp-graphql/releases). Look for releases tagged with `wp-graphql-acf/v*`.

We follow Semantic Versioning (Semver) for meaningful releases:

- *MAJOR* version for incompatible API changes,
- *MINOR* version for backwards-compatible functionality additions,
- *PATCH* version for backwards-compatible bug fixes.

Learn more about Semver at [semver.org](https://semver.org).

== Support ==

- [General Help Requests](https://github.com/wp-graphql/wp-graphql/discussions): For general questions and help requests, create a new topic in Github Discussions
- [Discord Community](https://www.wpgraphql.com/discord): The WPGraphQL Discord is a great place to communicate in real-time. Ask questions, discuss features, get to know other folks using WPGraphQL.
- [Bug Reports](https://github.com/wp-graphql/wp-graphql/issues/new?assignees=&labels=&projects=&template=bug_report.yml): Report a bug in WPGraphQL
- [Feature Requests](https://github.com/wp-graphql/wp-graphql/issues/new?assignees=&labels=&projects=&template=feature_request.yml): Suggest an idea, feature, or enhancement for WPGraphQL.
- [Report a Security Vulnerability](https://github.com/wp-graphql/wp-graphql/security/advisories/new): Report a security vulnerability.


== FAQs ==

**Does this work with ACF Extended?**

Yes! WPGraphQL for ACF allows you to query for (most) fields created with ACF Extended.

**Can I filter and sort queries by ACF Fields using WPGraphQL for ACF?**

At this time WPGraphQL for ACF does not support filtering or sorting queries by ACF Fields. "Meta Queries" are often very expensive to execute, so we currently do not support filtering by ACF fields out of the box, but are exploring options for supporting it without the performance penalty.

**I think I found a bug, where do I report it?**

If you think you found a bug, please [open a bug report](https://github.com/wp-graphql/wp-graphql/issues/new?template=bug_report.yml) in the WPGraphQL monorepo and pick "WPGraphQL for ACF" from the plugin dropdown. The more details you provide in the issue, and the more clear your steps to reproduce are, the higher chances we will be able to help.

**Can I use ACF Free or Pro with WPGraphQL for ACF?**

Yes! WPGraphQL for ACF works great with ACF Free and Pro. The Pro version of ACF has some additional features, such as Flexible Content Fields, Repeater Fields and Options Pages that are supported by WPGraphQL for ACF.

**Does WPGraphQL for ACF work with Secure Custom Fields?**

Yes. [Secure Custom Fields](https://wordpress.org/plugins/secure-custom-fields/) (SCF), the WordPress.org fork of ACF, is supported the same way as ACF Pro: it includes the Pro field types (such as Repeater, Flexible Content and Clone fields), Options Pages and ACF Blocks, and WPGraphQL for ACF exposes them all. The full test suite runs against SCF in CI. Install either ACF or SCF, not both.

**Do I have to use Faust.js to use WPGraphQL for ACF?**

No! You can use WPGraphQL for ACF with any GraphQL client, including Apollo, Relay, Urql, [Faust.js](https://faustjs.org/), etc.

**I have an ACF Extension that adds a new field type, will it work with WPGraphQL for ACF?**

WPGraphQL for ACF supports the field types that come with ACF (Free and PRO) as well as the field types in ACF Extended (Free and PRO). Support for additional field types can be added by using the "register_graphql_acf_field_type" API.

**Do I need WPGraphQL and ACF to be active to use this?**

This plugin is a "bridge" plugin that brings functionality of ACF to WPGraphQL. Both WPGraphQL and ACF need to be installed and active in your WordPress installation for this plugin to work.

**How much does WPGraphQL for ACF cost?**

WPGraphQL for ACF is a FREE open-source plugin. The development is sponsored by [WP Engine Atlas](https://wpengine.com/atlas).

**Does WPGraphQL for ACF support GraphQL Mutations?**

Not yet. WPGraphQL for ACF exposes ACF fields for reading today, but creating and updating field values through GraphQL mutations isn't built yet. Follow the [mutation support feature request](https://github.com/wp-graphql/wp-graphql/issues/4551) for progress.

**Does this work with Field Groups registered in PHP or JSON?**

Yes! You can register ACF Field Groups and Fields using the Admin UI, PHP or JSON. WPGraphQL for ACF will detect the Field Groups and Fields and add them to the GraphQL Schema. If using PHP or JSON, you will need to set the "show_in_graphql" setting to "true" to expose the Field Group and Fields to the GraphQL Schema. There might be other settings that need attention at the field group or field level that might impact the schema or field resolution.


== Privacy Policy ==

WPGraphQL for Advanced Custom Fields uses [Appsero](https://appsero.com) SDK to collect telemetry data upon user confirmation, helping us troubleshoot problems and improve the product.

The Appsero SDK **doesn't collect data by default** and only starts gathering basic telemetry data when a user allows it via the admin notice. No data is collected without user consent.

When you opt in, each telemetry request is sent to Appsero and a duplicate is sent in a non-blocking request to WPGraphQL-operated infrastructure at https://telemetry.wpgraphql.com (the same categories of data as described for Appsero below).

Learn more about how [Appsero collects and uses data](https://appsero.com/privacy-policy/).

== Screenshots ==

1. Configure a Field Group for GraphQL — toggle "Show in GraphQL", set the GraphQL type name, and choose which schema types it shows on.
2. ACF field groups and fields appear automatically in the GraphQL schema, explored here in the WPGraphQL IDE.
3. Per-field GraphQL settings — control the field's GraphQL name, description, and nullability in the schema.

== Upgrade Notice ==

= 3.1.0 =

**Read before updating.** This release fixes a long list of bugs. Five of the fixes can change results a client depends on, either what a query returns or which types a field group appears on, and one asks sites on ACF older than 6.1 to check their field groups. Each of those is described below, followed by the other fixes. If you rely on any of these areas, test the update on a staging site first.

**Field groups now leave the types their "is not equal to" location rules exclude.** Field groups matched by such a rule were not being removed from the GraphQL Schema, so they appeared on types they were configured to be excluded from. After updating, those field groups are correctly absent, which means fields may disappear from types where they should not have been available.

This reaches more than just field groups using "Post Type is not equal to". Exclusions were also skipped for every field group processed after one whose exclusion had nothing to remove, so a site with any exclusion rule may see several field groups move.

If a query relies on one of these fields, check that field group's location rules, since the field group is now on exactly the types those rules describe. To keep a field group on types its location rules exclude, enable "Manually Set GraphQL Types for Field Group" on the field group and choose the types directly, which bypasses location rules entirely.

The "All" value of the Block location rule is also fixed. A field group set to "Block is equal to All" was added to no ACF block type, and now appears on every ACF block type shown in GraphQL. A field group set to "Block is not equal to All" was added to every ACF block type, and now, like ACF itself, matches none, so its fields disappear from blocks after updating.

**Single-value select fields set to "Both (Array)" return just the value.** A select field that allows one value and uses the "Both (Array)" return format resolved as `["red", "Red"]`, the chosen option's value followed by its label. It now resolves as `["red"]`, the same as a select set to "Value", and the same as a multi-value select already did. If a client read the label from that second entry, set the field's return format to "Label" instead. See #4561.

**Zero values now resolve.** A number, range or text field that stores 0 or "0", and a select or checkbox field whose chosen value is "0", used to resolve as null, at the top level and inside repeater and flexible content rows. They now return 0 (or "0"). If a client treats null as "not set" for one of these fields, check that it also handles 0. See #4540.

**Field group settings on ACF before 6.1.** On ACF versions older than 6.1, saving a field group could rewrite a "Taxonomy is equal to" location rule as "Post Taxonomy is equal to", give the field group a new key, and reset its GraphQL settings (Show in GraphQL, GraphQL Type Name and the rest) to their defaults. That no longer happens, but field groups already saved this way aren't repaired by updating. If your site runs an ACF version older than 6.1, open your field groups and check their location rules and GraphQL settings. See #4527.

**Date Time Picker seconds, and nested dates, now resolve from the stored value.** A Date Time Picker whose return format has no seconds resolved with the seconds set to 00, and one whose return format has only a date picked up the time of the request. They now return the stored date and time, seconds included. Date Picker and Date Time Picker fields inside repeater and flexible content rows could also resolve as 1970-01-01, or with the day and month swapped, when the return format used slashes (such as `d/m/Y`). They now resolve from the stored date too. If a client worked around either, for example by ignoring the seconds, check it against the corrected values. See #4564 and #4524.

**WYSIWYG fields are no longer run through `the_content`.** ACF formats a WYSIWYG value with its own `acf_the_content` filter, and the value was then run through WordPress's `the_content` filter as well, so anything a plugin or theme adds to post content through `the_content` (sharing buttons, related posts, newsletter forms) was added to WYSIWYG field values too. They now get ACF's formatting only, as they do from `get_field()`, so paragraphs, shortcodes and embeds still apply. If a client relied on markup added by a `the_content` filter, add that callback to `acf_the_content` instead, which runs for WYSIWYG fields everywhere, including templates that use `get_field()`. Block markup in a WYSIWYG field is still rendered, even though `acf_the_content` doesn't render blocks, and it is now rendered the way `the_content` renders it. The blocks used to be rendered after ACF added paragraphs, so the output had empty `<p></p>` tags between blocks and `<p>` tags around block-level elements. Those are gone. If a client stripped or relied on them, check it against the new output. To return block markup with ACF's formatting only instead, return false from the new `wpgraphql/acf/wysiwyg/render_blocks` filter. See #4562 and #4605.

**Also fixed.** Connection fields inside repeater and flexible content rows (Page Link fields, and Image, File and Gallery fields set to the URL return format) returned null or an empty list, and Link fields set to "Link URL" returned null inside rows. They now resolve the same as at the top level. `fieldGroupName` is no longer deprecated, so tools that leave deprecated fields out when they introspect the Schema, like Gatsby, can build it again. Secure Custom Fields, the WordPress.org fork of ACF, is now tested and supported. Opening a field group no longer prompts "Leave site?" when nothing was changed, and the plugin no longer loads its translations too early, which with `WP_DEBUG` on could put a PHP notice in front of REST API responses. See #4380, #4541, #4526, #4513, #4560 and #4567.

= 3.0.0 =

**⚠️ BREAKING CHANGES**: This release contains breaking changes that may require updates to your code.

* **acf:** require explicit show_in_graphql opt-in for ACF options pages ([#4280](https://github.com/wp-graphql/wp-graphql/issues/4280))

Please review these changes before upgrading.

= 2.3.0 =

This release refactored some internals regarding how Clone fields and Group fields behave. There was no intentional breaking changes to the Schema, but if you are using Clone and Group fields there is a chance that if you were benefiting from a "bug as a feature" there might be some changes that could impact your Schema and/or resolvers, we recommend testing this update on a staging site to ensure things are still working for you as expected. Should you run into any problems, please [open a new issue](https://github.com/wp-graphql/wp-graphql/issues/new/choose) and provide as much detail as possible to help us reproduce the scenario. Thanks! 🙏

= 2.1.0 =

While fixing some [performance issues](https://github.com/wp-graphql/wpgraphql-acf/pull/152) we had to adjust the fallback logic for mapping ACF Field Groups to the Schema if they do not have "graphql_types" defined.

ACF Field Groups that did not have "graphql_types" defined AND were assigned to Location Rules based on "post_status", "post_format", "post_category" or "post_taxonomy" _might_ not show in the Schema until their "graphql_types" are explicitly defined.

= 2.0.0 =

This release is a complete re-architecture of WPGraphQL for ACF, introducing breaking changes to the GraphQL Schema and PHP API. Please read the [upgrade guide](https://www.wpgraphql.com/docs/acf/upgrade-guide) before upgrading.

== Changelog ==

= 3.2.0 =

**New Features**

* **acf:** let custom field types resolve ACF's formatted value ([#4609](https://github.com/wp-graphql/wp-graphql/issues/4609))

**Bug Fixes**

* **acf:** give a duplicated field its own GraphQL field name ([#4610](https://github.com/wp-graphql/wp-graphql/issues/4610))

= 3.1.0 =

**New Features**

* **acf:** test and document Secure Custom Fields support ([#4573](https://github.com/wp-graphql/wp-graphql/issues/4573))

**Bug Fixes**

* **acf:** apply field groups located on "All" blocks to every ACF block type ([#4565](https://github.com/wp-graphql/wp-graphql/issues/4565))
* **acf:** don't run WYSIWYG field values through the_content ([#4585](https://github.com/wp-graphql/wp-graphql/issues/4585))
* **acf:** don't warn about unsaved changes after opening a field group ([#4579](https://github.com/wp-graphql/wp-graphql/issues/4579))
* **acf:** exclude types matched by "not equal to" location rules ([#4431](https://github.com/wp-graphql/wp-graphql/issues/4431))
* **acf:** keep a field group's key and GraphQL settings when saving on ACF before 6.1 ([#4571](https://github.com/wp-graphql/wp-graphql/issues/4571))
* **acf:** preserve Date Time Picker seconds and resolve nested row dates from stored values ([#4594](https://github.com/wp-graphql/wp-graphql/issues/4594))
* **acf:** render blocks in WYSIWYG values before ACF's wpautop ([#4606](https://github.com/wp-graphql/wp-graphql/issues/4606))
* **acf:** resolve a single "Both (Array)" choice as its value ([#4583](https://github.com/wp-graphql/wp-graphql/issues/4583))
* **acf:** resolve connection fields nested in repeater, flexible content and group rows ([#4380](https://github.com/wp-graphql/wp-graphql/issues/4380))
* **acf:** resolve link fields with the URL return format inside rows ([#4580](https://github.com/wp-graphql/wp-graphql/issues/4580))
* **acf:** resolve stored 0 values as 0 instead of null ([#4581](https://github.com/wp-graphql/wp-graphql/issues/4581))
* **acf:** stop deprecating fieldGroupName ([#4582](https://github.com/wp-graphql/wp-graphql/issues/4582))
* **acf:** update the Appsero client so telemetry no longer loads translations too early ([#4567](https://github.com/wp-graphql/wp-graphql/issues/4567))
* **deps-dev:** bump axepress/wp-graphql-stubs from 2.20.0 to 2.22.2 in /plugins/wp-graphql-acf in the wp-graphql-acf-composer-dev-minor-patch group ([#4324](https://github.com/wp-graphql/wp-graphql/issues/4324))
* **deps-dev:** bump axepress/wp-graphql-stubs from 2.22.2 to 2.23.1 in /plugins/wp-graphql-acf in the wp-graphql-acf-composer-dev-minor-patch group across 1 directory ([#4349](https://github.com/wp-graphql/wp-graphql/issues/4349))
* **deps-dev:** bump axepress/wp-graphql-stubs from 2.23.1 to 2.24.2 in /plugins/wp-graphql-acf in the wp-graphql-acf-composer-dev-minor-patch group ([#4590](https://github.com/wp-graphql/wp-graphql/issues/4590))
* **deps-dev:** bump composer/composer from 2.2.29 to 2.2.30 in /plugins/wp-graphql-acf ([#4281](https://github.com/wp-graphql/wp-graphql/issues/4281))
* **deps-dev:** bump symfony/yaml from 5.4.53 to 5.4.54 in /plugins/wp-graphql-acf ([#4596](https://github.com/wp-graphql/wp-graphql/issues/4596))
* **deps-dev:** bump the npm-dev-minor-patch group across 1 directory with 11 updates ([#4290](https://github.com/wp-graphql/wp-graphql/issues/4290))
* **deps-dev:** bump the npm-dev-minor-patch group, with the @wordpress/env patch regenerated ([#4478](https://github.com/wp-graphql/wp-graphql/issues/4478))

= 3.0.0 =

**⚠ BREAKING CHANGES**

* **acf:** require explicit show_in_graphql opt-in for ACF options pages ([#4280](https://github.com/wp-graphql/wp-graphql/issues/4280))

**Bug Fixes**

* **acf:** require explicit show_in_graphql opt-in for ACF options pages ([#4280](https://github.com/wp-graphql/wp-graphql/issues/4280))
* **deps-dev:** bump axepress/wp-graphql-stubs from 2.19.0 to 2.20.0 in /plugins/wp-graphql-acf in the wp-graphql-acf-composer-dev-minor-patch group across 1 directory ([#4244](https://github.com/wp-graphql/wp-graphql/issues/4244))

= 2.8.0 =

**New Features**

* preview ACF field values under the request-level preview context ([#4222](https://github.com/wp-graphql/wp-graphql/issues/4222))
* **wpgraphql.com:** field types in the ACF docs nav with provider badges ([#4237](https://github.com/wp-graphql/wp-graphql/issues/4237))

**Bug Fixes**

* **deps-dev:** bump axepress/wp-graphql-stubs from 2.18.0 to 2.19.0 in /plugins/wp-graphql-acf in the wp-graphql-acf-composer-dev-minor-patch group ([#4198](https://github.com/wp-graphql/wp-graphql/issues/4198))
* **deps-dev:** bump the npm-dev-minor-patch group across 1 directory with 12 updates ([#4226](https://github.com/wp-graphql/wp-graphql/issues/4226))
* point acf.wpgraphql.com links at the wpgraphql.com docs portal ([#4251](https://github.com/wp-graphql/wp-graphql/issues/4251))
* **wpgraphql.com:** make docs cross-links resolve on the site ([#4230](https://github.com/wp-graphql/wp-graphql/issues/4230))

= 2.7.1 =

**Bug Fixes**

* **deps-dev:** bump guzzlehttp/guzzle from 7.15.1 to 7.15.2 in /plugins/wp-graphql-acf ([#4158](https://github.com/wp-graphql/wp-graphql/issues/4158))
* **deps-dev:** bump squizlabs/php_codesniffer from 3.13.5 to 3.13.6 in /plugins/wp-graphql-acf ([#4177](https://github.com/wp-graphql/wp-graphql/issues/4177))
* **deps-dev:** bump the npm-dev-minor-patch group across 1 directory with 7 updates ([#4154](https://github.com/wp-graphql/wp-graphql/issues/4154))
* **deps-dev:** bump the npm-dev-minor-patch group across 1 directory with 8 updates ([#4192](https://github.com/wp-graphql/wp-graphql/issues/4192))
* **deps-dev:** bump the wp-graphql-acf-composer-dev-minor-patch group across 1 directory with 2 updates ([#4145](https://github.com/wp-graphql/wp-graphql/issues/4145))
* **deps-dev:** bump wp-coding-standards/wpcs from 3.3.0 to 3.4.1 in /plugins/wp-graphql-acf ([#4125](https://github.com/wp-graphql/wp-graphql/issues/4125))

= 2.7.0 =

**New Features**

* **settings:** support per-entry config in the normalized settings map ([#4049](https://github.com/wp-graphql/wp-graphql/issues/4049))

**Bug Fixes**

* **deps-dev:** bump composer/composer from 2.2.28 to 2.2.29 in /plugins/wp-graphql-acf ([#4097](https://github.com/wp-graphql/wp-graphql/issues/4097))
* **deps-dev:** bump guzzlehttp/guzzle from 7.12.1 to 7.15.1 in /plugins/wp-graphql-acf ([#4103](https://github.com/wp-graphql/wp-graphql/issues/4103))

= 2.6.5 =

**Bug Fixes**

* **deps-dev:** bump guzzlehttp/psr7 from 2.12.1 to 2.12.3 in /plugins/wp-graphql-acf in the wp-graphql-acf-composer-dev-minor-patch group ([#4034](https://github.com/wp-graphql/wp-graphql/issues/4034))
* **deps-dev:** bump the npm-dev-minor-patch group with 9 updates ([#4036](https://github.com/wp-graphql/wp-graphql/issues/4036))

= 2.6.4 =

**Bug Fixes**

* **deps-dev:** bump eslint from 8.57.1 to 10.4.0 ([#3977](https://github.com/wp-graphql/wp-graphql/issues/3977))
* **deps-dev:** bump globals from 16.5.0 to 17.6.0 ([#4013](https://github.com/wp-graphql/wp-graphql/issues/4013))
* **deps-dev:** bump guzzlehttp/guzzle from 7.10.0 to 7.12.1 in /plugins/wp-graphql-acf ([#3982](https://github.com/wp-graphql/wp-graphql/issues/3982))
* **deps-dev:** bump guzzlehttp/psr7 from 2.11.0 to 2.12.1 in /plugins/wp-graphql-acf ([#3980](https://github.com/wp-graphql/wp-graphql/issues/3980))
* **deps-dev:** bump the npm-dev-minor-patch group across 1 directory with 8 updates ([#4015](https://github.com/wp-graphql/wp-graphql/issues/4015))
* **deps-dev:** bump the wp-graphql-acf-composer-dev-minor-patch group in /plugins/wp-graphql-acf with 2 updates ([#3938](https://github.com/wp-graphql/wp-graphql/issues/3938))
* support WordPress 7.0 in the integration test matrix ([#3960](https://github.com/wp-graphql/wp-graphql/issues/3960))
* **wp-graphql-acf:** detect renamed Content Blocks constant so the integration loads ([#4005](https://github.com/wp-graphql/wp-graphql/issues/4005))
* **wp-graphql-acf:** migrate ESLint to flat config for ESLint 10 ([#3995](https://github.com/wp-graphql/wp-graphql/issues/3995))
* **wp-graphql-acf:** mock oEmbed in tests and unskip clone-field tests ([#3867](https://github.com/wp-graphql/wp-graphql/issues/3867))

= 2.6.3 =

**Bug Fixes**

* **deps-dev:** bump guzzlehttp/psr7 from 2.10.1 to 2.10.4 in /plugins/wp-graphql-acf in the wp-graphql-acf-composer-dev-minor-patch group ([#3887](https://github.com/wp-graphql/wp-graphql/issues/3887))
* **deps-dev:** bump php-stubs/acf-pro-stubs from 6.5.0 to 6.5.0.1 in /plugins/wp-graphql-acf ([#3888](https://github.com/wp-graphql/wp-graphql/issues/3888))

= 2.6.2 =

**Bug Fixes**

* **deps-dev:** bump guzzlehttp/psr7 from 2.9.0 to 2.10.1 in /plugins/wp-graphql-acf in the wp-graphql-acf-composer-dev-minor-patch group ([#3873](https://github.com/wp-graphql/wp-graphql/issues/3873))
* **deps-dev:** bump symfony/dom-crawler from 5.4.48 to 5.4.52 in /plugins/wp-graphql-acf ([#3855](https://github.com/wp-graphql/wp-graphql/issues/3855))
* **deps-dev:** bump symfony/yaml from 5.4.45 to 5.4.53 in /plugins/wp-graphql-acf ([#3862](https://github.com/wp-graphql/wp-graphql/issues/3862))

= 2.6.1 =

**Bug Fixes**

* **deps-dev:** bump composer/composer from 2.2.27 to 2.2.28 in /plugins/wp-graphql-acf ([#3833](https://github.com/wp-graphql/wp-graphql/issues/3833))

= 2.6.0 =

**New Features**

* **telemetry:** mirror Appsero insights to telemetry.wpgraphql.com ([#3785](https://github.com/wp-graphql/wp-graphql/issues/3785))

**Bug Fixes**

* **deps-dev:** bump composer/composer from 2.2.26 to 2.2.27 in /plugins/wp-graphql-acf ([#3774](https://github.com/wp-graphql/wp-graphql/issues/3774))
* sync readme.txt changelogs with releases ([#3744](https://github.com/wp-graphql/wp-graphql/issues/3744))

= 2.5.2 =

**Bug Fixes**

* **deps-dev:** bump guzzlehttp/psr7 from 2.8.0 to 2.9.0 in /plugins/wp-graphql-acf in the wp-graphql-acf-composer-dev-minor-patch group ([#3643](https://github.com/wp-graphql/wp-graphql/issues/3643))
* **deps:** bump appsero/client from 1.2.1 to 1.4.0 in /plugins/wp-graphql-acf ([#3726](https://github.com/wp-graphql/wp-graphql/issues/3726))

= 2.5.1 =

**Bug Fixes**

* **wp-graphql-acf:** ACF-registered post types/taxonomies not in GraphQL schema (init priority) ([#3609](https://github.com/wp-graphql/wp-graphql/issues/3609))

= 2.5.0 =

**New Features**

* migrate WPGraphQL for ACF to monorepo ([#3581](https://github.com/wp-graphql/wp-graphql/issues/3581))

**Bug Fixes**

* allow null/empty meta_key in preview_support() for get_post_meta filter ([#3599](https://github.com/wp-graphql/wp-graphql/issues/3599))

= 2.4.1 =

**Chores / Bugfixes**

- chore: update "tested up to" and stable version tags.

= 2.4.0 =

**New Features**

- [#211](https://github.com/wp-graphql/wpgraphql-acf/pull/211): feat: add wp-graphql as required plugin dependency. Thanks @stefanmomm!

**Chores / Bugfixes**

- [#224](https://github.com/wp-graphql/wpgraphql-acf/pull/224): chore: update issue templates config.yml to link to Discord instead of Slack
- [#223](https://github.com/wp-graphql/wpgraphql-acf/pull/223): chore: update branding assets
- [#214](https://github.com/wp-graphql/wpgraphql-acf/pull/214): chore: bump composer/composer from 2.7.4 to 2.7.7 in the composer group across 1 directory
- [#231](https://github.com/wp-graphql/wpgraphql-acf/pull/231): fix: block type tests failing

= 2.3.0 =

**New Features**

- [#193](https://github.com/wp-graphql/wpgraphql-acf/pull/193): feat: improved handling of clone and group fields

**Chores / Bugfixes**

- [#194](https://github.com/wp-graphql/wpgraphql-acf/pull/194): ci: test against WordPress 6.5

= 2.2.0 =

**New Features**

- [#181](https://github.com/wp-graphql/wpgraphql-acf/pull/181): feat: update docs Date fields to link to the RFC3339 spec

**Chores / Bugfixes**

- [#182](https://github.com/wp-graphql/wpgraphql-acf/pull/182): fix: admin_enqueue_scripts callback should expect a possible null value passed to it
- [#185](https://github.com/wp-graphql/wpgraphql-acf/pull/185): fix: clone field within a group field type returns null values
- [#189](https://github.com/wp-graphql/wpgraphql-acf/pull/189): fix: image fields (and other connection fields) not properly resolving when queried asPreview

= 2.1.2 =

**Chores / Bugfixes**

- [#178](https://github.com/wp-graphql/wpgraphql-acf/pull/178): fix: Taxonomy field returns incorrect data if set to store objects instead of IDs
- [#174](https://github.com/wp-graphql/wpgraphql-acf/pull/174): fix: taxonomy field resolves sorted in the incorrect order

= 2.1.1 =

**Chores / Bugfixes**

- [#167](https://github.com/wp-graphql/wpgraphql-acf/pull/167): fix: pagination on connection fields
- [#166](https://github.com/wp-graphql/wpgraphql-acf/pull/166): fix: errors when querying fields of the `acfe_date_range_picker` field type
- [#165](https://github.com/wp-graphql/wpgraphql-acf/pull/165): fix: user field returning all publicly queryable users

= 2.1.0 =

**New Features**

- [#156](https://github.com/wp-graphql/wpgraphql-acf/pull/156): feat: Use published ACF values in resolvers for fields associated with posts that use the block editor, since the Block Editor has a bug preventing meta from being saved for previews. Adds a debug message if ACF fields are queried for with "asPreview" on post(s) that use the block editor.

**Chores / Bugfixes**

- [#156](https://github.com/wp-graphql/wpgraphql-acf/pull/156): fix: ACF Fields not resolving when querying "asPreview"
- [#155](https://github.com/wp-graphql/wpgraphql-acf/pull/155): fix: "show_in_graphql" setting not being respected when turned "off"
- [#152](https://github.com/wp-graphql/wpgraphql-acf/pull/152): fix: performance issues with mapping ACF Field Groups to the Schema
- [#148](https://github.com/wp-graphql/wpgraphql-acf/pull/148): fix: bug when querying taxonomy field on blocks
- [#146](https://github.com/wp-graphql/wpgraphql-acf/pull/146): chore: update phpcs to match core WPGraphQL

= 2.0.0 =

- Initial release on WordPress.org. Complete re-architecture of WPGraphQL for ACF v0.6.*.
- For beta release notes leading up to v2.0.0, see the [Github Releases](https://github.com/wp-graphql/wpgraphql-acf/releases).
