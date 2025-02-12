# Changelog

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

## 1.31.1

### Chores / Bugfixes

- update stable tag

## 1.31.0

### New Features

- [#3278](https://github.com/wp-graphql/wp-graphql/pull/3278): feat: add option to provide custom file path for static schemas when using the `wp graphql generate-static-schema` command

### Chores / Bugfixes

- [#3284](https://github.com/wp-graphql/wp-graphql/pull/3284): fix: fix: Updated docs link for example of hierarchical data
- [#3283](https://github.com/wp-graphql/wp-graphql/pull/3283): fix: Error in update checker when WPGraphQL is active as an mu-plugin

## 1.30.0

### Chores / Bugfixes

- [#3250](https://github.com/wp-graphql/wp-graphql/pull/3250): fix: receiving post for Incorrect uri
- [#3268](https://github.com/wp-graphql/wp-graphql/pull/3268): ci: trigger PR workflows on release/* branches
- [#3267](https://github.com/wp-graphql/wp-graphql/pull/3267): chore: fix bleeding edge/deprecated PHPStan smells [first pass]
- [#3270](https://github.com/wp-graphql/wp-graphql/pull/3270): build(deps): bump the npm_and_yarn group across 1 directory with 3 updates
- [#3271](https://github.com/wp-graphql/wp-graphql/pull/3271): fix: default cat should not be added when other categories are added

### New Features

- [#3251](https://github.com/wp-graphql/wp-graphql/pull/3251): feat: implement SemVer-compliant update checker
- [#3196](https://github.com/wp-graphql/wp-graphql/pull/3196): feat: expose EnqueuedAsset.group and EnqueuedScript.location to schema
- [#3188](https://github.com/wp-graphql/wp-graphql/pull/3188): feat: Add WPGraphQL Extensions page to the WordPress admin

## 1.29.3

### Chores / Bugfixes

- [#3245](https://github.com/wp-graphql/wp-graphql/pull/3245): fix: update appsero/client to v2.0.4 to prevent conflicts with WP6.7
- [#3243](https://github.com/wp-graphql/wp-graphql/pull/3243): chore: fix Composer autoloader for WPGraphQL.php
- [#3242](https://github.com/wp-graphql/wp-graphql/pull/3242): chore: update Composer dev deps
- [#3235](https://github.com/wp-graphql/wp-graphql/pull/3235): chore: general updates to README.md and readme.txt
- [#3234](https://github.com/wp-graphql/wp-graphql/pull/3234): chore: update quick-start.md to provide more clarity around using wpackagist

## 1.29.2

### Chores / Bugfixes

- fix: move assets/blueprint.json under .wordpress-org directory

## 1.29.1

### Chores / Bugfixes

- [#3226](https://github.com/wp-graphql/wp-graphql/pull/3226): chore: add blueprint.json so WPGraphQL can be demo'd with a live preview on WordPress.org
- [#3218](https://github.com/wp-graphql/wp-graphql/pull/3218): docs: update upgrading.md to highlight how breaking change releases will be handled
- [#3214](https://github.com/wp-graphql/wp-graphql/pull/3214): fix: lazy-resolve Post.sourceUrl and deprecate Post.sourceUrlsBySize
- [#3224](https://github.com/wp-graphql/wp-graphql/pull/3224): chore(deps-dev): bump symfony/process from 5.4.40 to 5.4.46 in the composer group
- [#3219](https://github.com/wp-graphql/wp-graphql/pull/3219): test: add tests for querying different sizes of media items
- [#3229](https://github.com/wp-graphql/wp-graphql/pull/3229): fix: Deprecated null value warning in titleRendered callback

## 1.29.0

### New Features

- [#3208](https://github.com/wp-graphql/wp-graphql/pull/3208): feat: expose commenter edge fields
- [#3207](https://github.com/wp-graphql/wp-graphql/pull/3207): feat: introduce get_graphql_admin_notices and convert AdminNotices class to a singleton

### Chores / Bugfixes

- [#3213](https://github.com/wp-graphql/wp-graphql/pull/3213): chore(deps): bump the npm_and_yarn group across 1 directory with 4 updates
- [#3212](https://github.com/wp-graphql/wp-graphql/pull/3212): chore(deps): bump dset from 3.1.3 to 3.1.4 in the npm_and_yarn group across 1 directory
- [#3211](https://github.com/wp-graphql/wp-graphql/pull/3211): chore: add LABELS.md
- [#3201](https://github.com/wp-graphql/wp-graphql/pull/3201): fix: ensure connectedTerms returns terms for the specified taxonomy only
- [#3199](https://github.com/wp-graphql/wp-graphql/pull/3199): chore(deps-dev): bump the npm_and_yarn group across 1 directory with 2 updates

## 1.28.1

### Chores / Bugfixes

- [#3189](https://github.com/wp-graphql/wp-graphql/pull/3189): fix: [regression] missing placeholder in $wpdb->prepare() call

## 1.28.0

### Upgrade Notice

This release contains an internal refactor for how the Type Registry is generated which should lead to significant performance improvements for most users. 

While there are no intentional breaking changes, because this change impacts every user we highly recommend testing this release thoroughly on staging servers to ensure the changes don't negatively impact your projects.

### New Features

- [#3172](https://github.com/wp-graphql/wp-graphql/pull/3172): feat: only `eagerlyLoadType` on introspection requests.

### Chores / Bugfixes

- [#3181](https://github.com/wp-graphql/wp-graphql/pull/3181): ci: replace `docker-compose` commands with `docker compose`
- [#3182](https://github.com/wp-graphql/wp-graphql/pull/3182): ci: test against WP 6.6
- [#3183](https://github.com/wp-graphql/wp-graphql/pull/3183): fix: improve performance of SQL query in the user loader

## 1.27.2

### Chores / Bugfixes

- [#3167](https://github.com/wp-graphql/wp-graphql/pull/3167): fix: missing .svg causing admin_menu not to be registered

## 1.27.1

### Chores / Bugfixes

- [#3066](https://github.com/wp-graphql/wp-graphql/pull/3066): fix: merge query arg arrays instead of overriding.
- [#3151](https://github.com/wp-graphql/wp-graphql/pull/3151): fix: update dev-deps and fix `WPGraphQL::get_static_schema()` 
- [#3152](https://github.com/wp-graphql/wp-graphql/pull/3152): fix: handle regression when implementing interface with identical args.
- [#3153](https://github.com/wp-graphql/wp-graphql/pull/3153): chore(deps-dev): bump composer/composer from 2.7.6 to 2.7.7 in the composer group across 1 directory
- [#3155](https://github.com/wp-graphql/wp-graphql/pull/3155): chore(deps-dev): bump the npm_and_yarn group across 1 directory with 2 updates
- [#3160](https://github.com/wp-graphql/wp-graphql/pull/3160): chore: Update branding assets
- [#3162](https://github.com/wp-graphql/wp-graphql/pull/3162): fix: set_query_arg should not merge args

## 1.27.0

### New Features

- [#3143](https://github.com/wp-graphql/wp-graphql/pull/3143): feat: Enhance tab state management with query arguments and localStorage fallback

### Chores / Bugfixes

- [#3139](https://github.com/wp-graphql/wp-graphql/pull/3139): fix: `$settings_fields` param on "graphql_get_setting_section_field_value" filter not passing the correct type
- [#3137](https://github.com/wp-graphql/wp-graphql/pull/3137): fix: WPGraphQL Settings page fails to load when "graphiql_enabled" setting is "off"
- [#3133](https://github.com/wp-graphql/wp-graphql/pull/3133): build: clean up dist
- [#3146](https://github.com/wp-graphql/wp-graphql/pull/3146): test: add e2e test coverage for tabs in the settings page

## 1.26.0 =

### New Features

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

### Chores / Bugfixes

- [#3125](https://github.com/wp-graphql/wp-graphql/pull/3125): refactor: improve query handling in AbstractConnectionResolver
    - Implement PHPStan Generic Type
    - Update generic Exceptions to InvariantViolation
- [#3127](https://github.com/wp-graphql/wp-graphql/pull/3127): chore: update references to the WPGraphQL Slack Community to point to the new WPGraphQL Discord community instead.
- [#3122](https://github.com/wp-graphql/wp-graphql/pull/3122): chore: relocate `AbstractConnectionResolver::is_valid_offset()` with other abstract methods.
- 
## 1.25.0

### Upgrade Notice

This release includes a fix to a regression in the v1.24.0. Users impacted by the regression in 1.24.0 included, but are not necessarily limited to, users of the WPGraphQL for WooCommerce extension.

### New Features

- [#3104](https://github.com/wp-graphql/wp-graphql/pull/3104): feat: add `AbsractConnectionResolver::pre_should_execute()`. Thanks @justlevine!

### Chores / Bugfixes
- [#3104](https://github.com/wp-graphql/wp-graphql/pull/3104): refactor: `AbstractConnectionResolver::should_execute()` Thanks @justlevine!
- [#3112](https://github.com/wp-graphql/wp-graphql/pull/3104): fix: fixes a regression from v1.24.0 relating to field arguments defined on Interfaces not being properly merged onto Object Types that implement the interface. Thanks @kidunot89!
- [#3114](https://github.com/wp-graphql/wp-graphql/pull/3114): fix: node IDs not showing in the Query Analyzer / X-GraphQL-Keys when using DataLoader->load_many()
- [#3116](https://github.com/wp-graphql/wp-graphql/pull/3116): chore: Update WPGraphQLTestCase to v3. Thanks @kidunot89!


## 1.24.0

### Upgrade Notice

The AbstractConnectionResolver has undergone some refactoring. Some methods using `snakeCase` have been deprecated in favor of their `camel_case` equivalent. While we've preserved the deprecated methods to prevent breaking changes, you might begin seeing PHP notices about the deprecations. Any plugin that extends the AbstractConnectionResolver should update the following methods:

- `getSource` -> `get_source`
- `getContext` -> `get_context`
- `getInfo` -> `get_info`
- `getShouldExecute` -> `get_should_execute`
- `getLoader` -> `get_loader`

### New Features

- [#3084](https://github.com/wp-graphql/wp-graphql/pull/3084): perf: refactor PluginConnectionResolver to only fetch plugins once. Thanks @justlevine!
- [#3088](https://github.com/wp-graphql/wp-graphql/pull/3088): refactor: improve loader handling in AbstractConnectionResolver. Thanks @justlevine!
- [#3087](https://github.com/wp-graphql/wp-graphql/pull/3087): feat: improve query amount handling in AbstractConnectionResolver. Thanks @justlevine!
- [#3086](https://github.com/wp-graphql/wp-graphql/pull/3086): refactor: add AbstractConnectionResolver::get_unfiltered_args() public getter. Thanks @justlevine!
- [#3085](https://github.com/wp-graphql/wp-graphql/pull/3085): refactor: add AbstractConnectionResolver::prepare_page_info()and only instantiate once. Thanks @justlevine!
- [#3083](https://github.com/wp-graphql/wp-graphql/pull/3083): refactor: deprecate camelCase methods in AbstractConnectionResolver for snake_case equivalents. Thanks @justlevine!


### Chores / Bugfixes

- [#3095](https://github.com/wp-graphql/wp-graphql/pull/3095): chore: lint for superfluous whitespace. Thanks @justlevine!
- [#3100](https://github.com/wp-graphql/wp-graphql/pull/3100): fix: recursion issues with interfaces
- [#3082](https://github.com/wp-graphql/wp-graphql/pull/3082): chore: prepare ConnectionResolver classes for v2 backport

## 1.23.0

### New Features

- [#3073](https://github.com/wp-graphql/wp-graphql/pull/3073): feat: expose `hasPassword` and `password` fields on Post objects. Thanks @justlevine!
- [#3091](https://github.com/wp-graphql/wp-graphql/pull/3091): feat: introduce actions and filters for GraphQL Admin Notices

### Chores / Bugfixes

- [#3079](https://github.com/wp-graphql/wp-graphql/pull/3079): fix: GraphiQL IDE test failures
- [#3084](https://github.com/wp-graphql/wp-graphql/pull/3084): perf: refactor PluginConnectionResolver to only fetch plugins once. Thanks @justlevine!
- [#3092](https://github.com/wp-graphql/wp-graphql/pull/3092): ci: test against wp 6.5
- [#3093](https://github.com/wp-graphql/wp-graphql/pull/3093): ci: Update actions in GitHub workflows and cleanup. Thanks @justlevine!
- [#3093](https://github.com/wp-graphql/wp-graphql/pull/3093): chore: update Composer dev-deps and lint. Thanks @justlevine!

## 1.22.1

### Chores / Bugfixes

- [#3067](https://github.com/wp-graphql/wp-graphql/pull/3067): fix: respect show avatar setting
- [#3063](https://github.com/wp-graphql/wp-graphql/pull/3063): fix: fixes a bug in cursor stability filters that could lead to empty order
- [#3070](https://github.com/wp-graphql/wp-graphql/pull/3070): test(3063): Adds test for [#3063](https://github.com/wp-graphql/wp-graphql/pull/3063)

## 1.22.0

### New Features

- [#3044](https://github.com/wp-graphql/wp-graphql/pull/3044): feat: add `graphql_pre_resolve_menu_item_connected_node` filter
- [#3039](https://github.com/wp-graphql/wp-graphql/pull/3043): feat: add `UniformResourceIdentifiable` interface to `Comment` type
- [#3020](https://github.com/wp-graphql/wp-graphql/pull/3020): feat: introduce `graphql_query_analyzer_get_headers` filter

### Chores / Bugfixes

- [#3062](https://github.com/wp-graphql/wp-graphql/pull/3062): ci: pin wp-browser to "<3.5" to allow automated tests to run properly 
- [#3057](https://github.com/wp-graphql/wp-graphql/pull/3057): fix: `admin_enqueue_scripts` callback should expect a possible `null` value passed to it
- [#3048](https://github.com/wp-graphql/wp-graphql/pull/3048): fix: `isPostsPage` on content type
- [#3043](https://github.com/wp-graphql/wp-graphql/pull/3043): fix: return empty when filtering `menuItems` by a location with no assigned items
- [#3045](https://github.com/wp-graphql/wp-graphql/pull/3045): fix: `UsersConnectionSearchColumnEnum` values should be prefixed with `user_`

## 1.21.0

### New Features

- [#3035](https://github.com/wp-graphql/wp-graphql/pull/3035): feat: provide better error when field references a type that does not exist
- [#3027](https://github.com/wp-graphql/wp-graphql/pull/3027): feat: Add register_graphql_admin_notice API and intial use to inform users of the new WPGraphQL for ACF plugin

### Chores / Bugfixes

- [#3038](https://github.com/wp-graphql/wp-graphql/pull/3038): chore(deps-dev): bump the composer group across 1 directories with 1 update. Thanks @dependabot!
- [#3033](https://github.com/wp-graphql/wp-graphql/pull/3033): fix: php deprecation error for dynamic properties on AppContext class
- [#3031](https://github.com/wp-graphql/wp-graphql/pull/3031): fix(graphiql): Allow GraphiQL to run even if a valid schema cannot be returned. Thanks @linucks!

## 1.20.0

### New Features

- [#3013](https://github.com/wp-graphql/wp-graphql/pull/3013): feat: output GRAPHQL_DEBUG message if requested amount is larger than connection limit. Thanks @justlevine!
- [#3008](https://github.com/wp-graphql/wp-graphql/pull/3008): perf: Expose graphql_should_analyze_queries as setting. Thanks @justlevine!

### Chores / Bugfixes

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

## 1.19.0

### New Features

- [#2988](https://github.com/wp-graphql/wp-graphql/pull/2988): feat: add missing extra fields to `EnqueuedAsset`, `EnqueuedScript` and `EnqueuedStylesheet`

### Chores / Bugfixes

- [#2989](https://github.com/wp-graphql/wp-graphql/pull/2989): fix: make User.url public
- [#2990](https://github.com/wp-graphql/wp-graphql/pull/2990): chore: autolint tests with phpcbf
- [#2992](https://github.com/wp-graphql/wp-graphql/pull/2992): fix: add polyfills for `str_starts_with()` and `str_ends_with()` to prevent fatal errors in `PHP < 8.0` 

## 1.18.2

### Chores / Bugfixes

- ci: update tests to run against WordPress 6.4.1. Update Docker Deploy to include WP 6.4.1. Update README, plugin file's "tested up to" to reflect.


## 1.18.1

### Chores / Bugfixes

- [#2984](https://github.com/wp-graphql/wp-graphql/pull/2984): ci: update tests to run against WordPress 6.4. Update README, plugin file's "tested up to" to reflect.

## 1.18.0

### New Features

- [#2937](https://github.com/wp-graphql/wp-graphql/pull/2937): fix: Support asPreview by URI/SLUG Id Type (this is technically a bugfix, but introduces new functionality)

### Chores / Bugfixes

- [#2972](https://github.com/wp-graphql/wp-graphql/pull/2972): chore(deps): bump @babel/traverse from 7.17.3 to 7.23.2
- [#2930](https://github.com/wp-graphql/wp-graphql/pull/2930): fix: unstable term cursor identical names
- [#2976](https://github.com/wp-graphql/wp-graphql/pull/2976): chore: restore commenting sniffs to PHPCS ruleset
- [#2973](https://github.com/wp-graphql/wp-graphql/pull/2973): chore: update composer deps to latest
- [#2975](https://github.com/wp-graphql/wp-graphql/pull/2975): chore: lint and remove useless variables [phpcs]
- [#2977](https://github.com/wp-graphql/wp-graphql/pull/2977): chore: sort use statements alphabetically with SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses (autofix)
- [#2978](https://github.com/wp-graphql/wp-graphql/pull/2978]): chore: implement stricter type hinting with SlevomatCodingStandard.TypeHints [phpcs]


## 1.17.0

### New Features

- [#2940](https://github.com/wp-graphql/wp-graphql/pull/2940): feat: add graphql_format_name() access method
- [#2256](https://github.com/wp-graphql/wp-graphql/pull/2256): feat: add connectedTerms connection to Taxonomy Object

### Chores / Bugfixes

- [#2808](https://github.com/wp-graphql/wp-graphql/pull/2808): fix: fallback to template filename if sanitized name is empty
- [#2968](https://github.com/wp-graphql/wp-graphql/pull/2968): fix: Add graphql_debug warning when using `hasPublishedPosts: ATTACHMENT`
- [#2968](https://github.com/wp-graphql/wp-graphql/pull/2968): fix: improve DX for updateComment mutation
- [#2962](https://github.com/wp-graphql/wp-graphql/pull/2962): fix: respect hasPublishedPosts where arg on unauthenticated users queries
- [#2967](https://github.com/wp-graphql/wp-graphql/pull/2967): fix: use all roles for UserRoleEnum instead of the filtered editible_roles
- [#2940](https://github.com/wp-graphql/wp-graphql/pull/2940): fix: Decode slug so it works with other languages
- [#2959](https://github.com/wp-graphql/wp-graphql/pull/2959): chore: remove @phpstan-ignore annotations
- [#2945](https://github.com/wp-graphql/wp-graphql/pull/2945): fix: rename fields registered by connections when using `rename_graphql_field()`
- [#2949](https://github.com/wp-graphql/wp-graphql/pull/2949): fix: correctly get default user role for settings selectbox
- [#2955](https://github.com/wp-graphql/wp-graphql/pull/2955): test: back-fill register_graphql_input|union_type() tests
- [#2953](https://github.com/wp-graphql/wp-graphql/pull/2953): fix: term uri, early return. (Follow up to [#2341](https://github.com/wp-graphql/wp-graphql/pull/2341))
- [#2956](https://github.com/wp-graphql/wp-graphql/pull/2956): chore(deps-dev): bump postcss from 8.4.12 to 8.4.31
- [#2954](https://github.com/wp-graphql/wp-graphql/pull/2954): fix: regression to autoloader for bedrock sites. (Follow-up to [#2935](https://github.com/wp-graphql/wp-graphql/pull/2935))
- [#2950](https://github.com/wp-graphql/wp-graphql/pull/2950): fix: rename typo in component name - AuthSwitchProvider
- [#2948](https://github.com/wp-graphql/wp-graphql/pull/2948): chore: fix spelling mistakes (non-logical)
- [#2944](https://github.com/wp-graphql/wp-graphql/pull/2944): fix: skip setting if no $setting['group']
- [#2934](https://github.com/wp-graphql/wp-graphql/pull/2934): chore(deps-dev): bump composer/composer from 2.2.21 to 2.2.22
- [#2936](https://github.com/wp-graphql/wp-graphql/pull/2936): chore(deps): bump graphql from 16.5.0 to 16.8.1
- [#2341](https://github.com/wp-graphql/wp-graphql/pull/2341): fix: wrong term URI on sub-sites of multisite subdomain installs
- [#2935](https://github.com/wp-graphql/wp-graphql/pull/2935): fix: admin notice wasn't displaying if composer dependencies were missing
- [#2933](https://github.com/wp-graphql/wp-graphql/pull/2933): chore: remove unused parameters from resolver callbacks
- [#2932](https://github.com/wp-graphql/wp-graphql/pull/2932): chore: cleanup PHPCS inline annotations
- [#2934](https://github.com/wp-graphql/wp-graphql/pull/2634): chore: use .php extension for stub files
- [#2924](https://github.com/wp-graphql/wp-graphql/pull/2924): chore: upgrade WPCS to v3.0
- [#2921](https://github.com/wp-graphql/wp-graphql/pull/2921): fix: zip artifact in GitHub not in sub folder

## 1.16.0

### Upgrade Notice

**WPGraphQL Smart Cache**
For WPGraphQL Smart Cache users, you should update WPGraphQL Smart Cache to v1.2.0 when updating 
WPGraphQL to v1.16.0 to ensure caches continue to purge as expected.

**Cursor Pagination Updates**
This version fixes some behaviors of Cursor Pagination which _may_ lead to behavior changes in your application.

As with any release, we recommend you test in staging environments. For this release, specifically any 
queries you have using pagination arguments (`first`, `last`, `after`, `before`). 

### New Features

- [#2918](https://github.com/wp-graphql/wp-graphql/pull/2918): feat: Use graphql endpoint without scheme in url header. 
- [#2882](https://github.com/wp-graphql/wp-graphql/pull/2882): feat: Config and Cursor Classes refactor

## 1.15.0

### New Features

- [#2908](https://github.com/wp-graphql/wp-graphql/pull/2908): feat: Skip param added to Utils::map_input(). Thanks @kidunot89!

### Chores / Bugfixes

- [#2907](https://github.com/wp-graphql/wp-graphql/pull/2907): ci: Use WP 6.3 image, not the beta one
- [#2902](https://github.com/wp-graphql/wp-graphql/pull/2902): chore: handle unused variables (phpcs). Thanks @justlevine!
- [#2901](https://github.com/wp-graphql/wp-graphql/pull/2901): chore: remove useless ternaries (phpcs). Thanks @justlevine!
- [#2898](https://github.com/wp-graphql/wp-graphql/pull/2898): chore: restore excluded PHPCS sniffs. Thanks @justlevine!
- [#2899](https://github.com/wp-graphql/wp-graphql/pull/2899): chore: Configure PHPCS blank line check and autofix. Thanks @justlevine!
- [#2900](https://github.com/wp-graphql/wp-graphql/pull/2900): chore: implement PHPCS sniffs from Slevomat Coding Standards. Thanks @justlevine!
- [#2897](https://github.com/wp-graphql/wp-graphql/pull/2897): fix: default excerptRendered to empty string. Thanks @izzygld!
- [#2890](https://github.com/wp-graphql/wp-graphql/pull/2890): fix: Use hostname for graphql cache header url for varnish
- [#2892](https://github.com/wp-graphql/wp-graphql/pull/2889): chore: GitHub template tweaks. Thanks @justlevine!
- [#2889](https://github.com/wp-graphql/wp-graphql/pull/2889): ci: update tests to test against WordPress 6.3, simplify the matrix
- [#2891](https://github.com/wp-graphql/wp-graphql/pull/2891): chore: bump graphql-php to 14.11.10 and update Composer dev-deps. Thanks @justlevine!

## 1.14.10

### Chores / Bugfixes

- [#2874](https://github.com/wp-graphql/wp-graphql/pull/2874): fix: improve PostObjectCursor support for meta queries. Thanks @kidunot89!
- [#2880](https://github.com/wp-graphql/wp-graphql/pull/2880): fix: increase clarity of the description of "asPreview" argument

## 1.14.9

## Chores / Bugfixes

- [#2865](https://github.com/wp-graphql/wp-graphql/pull/2865): fix: user roles should return empty if user doesn't have roles. Thanks @j3ang!
- [#2870](https://github.com/wp-graphql/wp-graphql/pull/2870): fix: Type Loader returns null when "graphql_single_name" value has underscores [regression]
- [#2871](https://github.com/wp-graphql/wp-graphql/pull/2871): fix: update tests, follow-up to [#2865](https://github.com/wp-graphql/wp-graphql/pull/2865)

## 1.14.8

### Chores / Bugfixes

- [#2855](https://github.com/wp-graphql/wp-graphql/pull/2855): perf: enforce static closures when possible (PHPCS). Thanks @justlevine!
- [#2857](https://github.com/wp-graphql/wp-graphql/pull/2857): fix: Prevent truncation of query name inside the GraphiQL Query composer explorer tab. Thanks @LarsEjaas!
- [#2856](https://github.com/wp-graphql/wp-graphql/pull/2856): chore: add missing translator comments. Thanks @justlevine!
- [#2862](https://github.com/wp-graphql/wp-graphql/pull/2862): chore(deps-dev): bump word-wrap from 1.2.3 to 1.2.4
- [#2861](https://github.com/wp-graphql/wp-graphql/pull/2861): fix: output `list:$type` keys for Root fields that return a list of nodes

## 1.14.7

### Chores / Bugfixes

- [#2853](https://github.com/wp-graphql/wp-graphql/pull/2853): fix: internal server error when query max depth setting is left empty
- [#2851](https://github.com/wp-graphql/wp-graphql/pull/2851): fix: querying posts by slug or uri with non-ascii characters
- [#2849](https://github.com/wp-graphql/wp-graphql/pull/2849): ci: Indent WP 6.2 in workflow file. Fixes Docker deploys. Thanks @markkelnar!
- [#2846](https://github.com/wp-graphql/wp-graphql/pull/2846): chore(deps): bump tough-cookie from 4.0.0 to 4.1.3

## 1.14.6

### Chores / Bugfixes

- [#2841](https://github.com/wp-graphql/wp-graphql/pull/2841): ci: support STEP_DEBUG in Code Quality workflow. Thanks @justlevine!
- [#2840](https://github.com/wp-graphql/wp-graphql/pull/2840): fix: update createMediaItem mutation to have better validation of input.
- [#2838](https://github.com/wp-graphql/wp-graphql/pull/2838): chore: update security.md

## 1.14.5

### Chores / Bugfixes

- [#2834](https://github.com/wp-graphql/wp-graphql/pull/2834): fix: improve how the Query Analyzer tracks list types, only tracking lists from the RootType and not nested lists.
- [#2828](https://github.com/wp-graphql/wp-graphql/pull/2828): chore: update composer dev-deps to latest. Thanks @justlevine!
- [#2835](https://github.com/wp-graphql/wp-graphql/pull/2835): ci: update docker deploy workflow to use latest docker actions.
- [#2836](https://github.com/wp-graphql/wp-graphql/pull/2836): ci: update schema upload workflow to pin mariadb to 10.8.2

## 1.14.4

### New Features

- [#2826](https://github.com/wp-graphql/wp-graphql/pull/2826): feat: pass connection config to connection field

### Chores / Bugfixes

- [#2818](https://github.com/wp-graphql/wp-graphql/pull/2818): chore: update webonyx/graphql-php to v14.11.9. Thanks @justlevine!
- [#2813](https://github.com/wp-graphql/wp-graphql/pull/2813): fix: replace double negation with true. Thanks @cesarkohl!

## 1.14.3

### Chores / Bugfixes

- [#2801](https://github.com/wp-graphql/wp-graphql/pull/2801): fix: conflict between custom post type and media slugs
- [#2799](https://github.com/wp-graphql/wp-graphql/pull/2794): fix: querying posts by slug fails when custom permalinks are set
- [#2794](https://github.com/wp-graphql/wp-graphql/pull/2794): chore(deps): bump guzzlehttp/psr7 from 1.9.0 to 1.9.1


## 1.14.2

### Chores / Bugfixes

- [#2792](https://github.com/wp-graphql/wp-graphql/pull/2792): fix: uri field is null when querying the page for posts uri

## 1.14.1

### New Features

- [#2763](https://github.com/wp-graphql/wp-graphql/pull/2763): feat: add `shouldShowAdminToolbar` field to the User type, resolving from the "show_admin_bar_front" meta value. Thanks @blakewilson!

### Chores / Bugfixes

- [#2758](https://github.com/wp-graphql/wp-graphql/pull/2758): fix: Allow post types and taxonomies to be registered without "graphql_plural_name".
- [#2762](https://github.com/wp-graphql/wp-graphql/pull/2762): Bump webpack version.
- [#2770](https://github.com/wp-graphql/wp-graphql/pull/2770): fix: wrong order in term/post ancestor queries. Thanks @creative-andrew!
- [#2775](https://github.com/wp-graphql/wp-graphql/pull/2775): fix: properly resolve when querying terms filtered by multiple taxonomies. Thanks @thecodeassassin!
- [#2776](https://github.com/wp-graphql/wp-graphql/pull/2776): chore: remove internal usage of deprecated functions. Thanks @justlevine!
- [#2777](https://github.com/wp-graphql/wp-graphql/pull/2777): chore: update composer dev-deps (not PHPStan). Thanks @justlevine!
- [#2778](https://github.com/wp-graphql/wp-graphql/pull/2778): fix: Update PHPStan and fix smells. Thanks @justlevine!
- [#2779](https://github.com/wp-graphql/wp-graphql/pull/2779): ci: test against WordPress 6.2. Thanks @justlevine!
- [#2781](https://github.com/wp-graphql/wp-graphql/pull/2781): chore: call _doing_it_wrong() when using deprecated PostObjectUnion and TermObjectUnion. Thanks @justlevine!
- [#2782](https://github.com/wp-graphql/wp-graphql/pull/2782): ci: fix deprecation warnings in Github workflows. Thanks @justlevine!
- [#2786](https://github.com/wp-graphql/wp-graphql/pull/2786): fix: early return for HTTP OPTIONS requests. 

## 1.14.0

### New Features

- [#2745](https://github.com/wp-graphql/wp-graphql/pull/2745): feat: Allow fields, connections and mutations to optionally be registered with undersores in the field name.
- [#2651](https://github.com/wp-graphql/wp-graphql/pull/2651): feat: Add `deregister_graphql_mutation()` and `graphql_excluded_mutations` filter. Thanks @justlevine!
- [#2652](https://github.com/wp-graphql/wp-graphql/pull/2652): feat: Add `deregister_graphql_connection` and `graphql_excluded_connections` filter. Thanks @justlevine!
- [#2680](https://github.com/wp-graphql/wp-graphql/pull/2680): feat: Refactor the NodeResolver::resolve_uri to use WP_Query. Thanks @justlevine!
- [#2643](https://github.com/wp-graphql/wp-graphql/pull/2643): feat: Add post_lock check on edit/delete mutation. Thanks @markkelnar!
- [#2649](https://github.com/wp-graphql/wp-graphql/pull/2649): feat: Add `pageInfo` field to the Connection type.

### Chores / Bugfixes

- [#2752](https://github.com/wp-graphql/wp-graphql/pull/2752): fix: handle 404s in NodeResolver.php. Thanks @justlevine!
- [#2735](https://github.com/wp-graphql/wp-graphql/pull/2735): fix: Explicitly check for DEBUG enabled value for tests. Thanks @markkelnar!
- [#2659](https://github.com/wp-graphql/wp-graphql/pull/2659): test: Add tests for nodeByUri. Thanks @justlevine!
- [#2724](https://github.com/wp-graphql/wp-graphql/pull/2724): test: Add test for graphql:Query key in headers. Thanks @markkelnar!
- [#2718](https://github.com/wp-graphql/wp-graphql/pull/2718): fix: deprecation notice. Thanks @decodekult!
- [#2705](https://github.com/wp-graphql/wp-graphql/pull/2705): chore: Use fully qualified classnames in PHPDoc annotations. Thanks @justlevine!
- [#2706](https://github.com/wp-graphql/wp-graphql/pull/2706): chore: update PHPStan and fix newly surfaced sniffs. Thanks @justlevine!
- [#2698](https://github.com/wp-graphql/wp-graphql/pull/2698): chore: bump simple-get from 3.15.1 to 3.16.0. Thanks @dependabot!
- [#2701](https://github.com/wp-graphql/wp-graphql/pull/2701): fix: navigation url. Thanks @jiwon-mun!
- [#2704](https://github.com/wp-graphql/wp-graphql/pull/2704): fix: missing apostrophe after escape. Thanks @i-mann!
- [#2709](https://github.com/wp-graphql/wp-graphql/pull/2709): chore: update http-cache-semantics. Thanks @dependabot!
- [#2707](https://github.com/wp-graphql/wp-graphql/pull/2707): ci: update and fix Lint PR workflow. Thanks @justlevine!
- [#2689](https://github.com/wp-graphql/wp-graphql/pull/2689): fix: prevent infinite recursion for interfaces that implement themselves as an interface.
- [#2691](https://github.com/wp-graphql/wp-graphql/pull/2691): fix: prevent non-node types from being output in the query analyzer lis-type
- [#2684](https://github.com/wp-graphql/wp-graphql/pull/2684): chore: remove deprecated use of WPGraphQL\Data\DataSource::resolve_user(). Thanks @renatonascalves
- [#2675](https://github.com/wp-graphql/wp-graphql/pull/2675): ci: keep the develop branch in sync with master.

## 1.13.10

### Chores / Bugfixes

- [#2741](https://github.com/wp-graphql/wp-graphql/pull/2741): Change the plugin name from "WP GraphQL" to "WPGraphQL". Thanks @josephfusco!
- [#2742](https://github.com/wp-graphql/wp-graphql/pull/2742): Update Stalebot rules. Thanks @justlevine!

## 1.13.9

### Chores / Bugfixes

- [#2726](https://github.com/wp-graphql/wp-graphql/pull/2726): fix: invalid schema when custom post types and custom taxonomies are registered with underscores in the "graphql_single_name" / "graphql_plural_name"

## 1.13.8

### Chores / Bugfixes

- [#2712](https://github.com/wp-graphql/wp-graphql/pull/2712): fix: query analyzer outputting unexpected list types

## 1.13.7

### Chores / Bugfixes

- ([#2661](https://github.com/wp-graphql/wp-graphql/pull/2661)): chore(deps): bump simple-git from 3.10.0 to 3.15.1
- ([#2665](https://github.com/wp-graphql/wp-graphql/pull/2665)): chore(deps): bump decode-uri-component from 0.2.0 to 0.2.2
- ([#2668](https://github.com/wp-graphql/wp-graphql/pull/2668)): test: Multiple domain tests. Thanks @markkelnar!
- ([#2669](https://github.com/wp-graphql/wp-graphql/pull/2669)): ci: Use last working version of xdebug for php7. Thanks @markkelnar!
- ([#2671](https://github.com/wp-graphql/wp-graphql/pull/2671)): fix: correct regressions to field formatting forcing snake_case and UcFirst fields to be lcfirst/camelCase
- ([#2672](https://github.com/wp-graphql/wp-graphql/pull/2672)): chore: update lint-pr workflow

## 1.13.6

### New Features

- ([#2657](https://github.com/wp-graphql/wp-graphql/pull/2657)): feat: pass unfiltered args through to filters in the ConnectionResolver classes. Thanks @kidunot89!
- ([#2655](https://github.com/wp-graphql/wp-graphql/pull/2655)): feat: add `includeDefaultInterfaces` to connection config, allowing connections to be registered without the default `Connection` and `Edge` interfaces applied.. Thanks @justlevine!

### Chores / Bugfixes

- ([#2656](https://github.com/wp-graphql/wp-graphql/pull/2656)): chore: clean up NodeResolver::resolve_uri() logic. Thanks @justlevine!

## 1.13.5

### Chores / Bugfixes

- ([#2647](https://github.com/wp-graphql/wp-graphql/pull/2647)): fix: properly register the node field on ConnectionEdge interfaces
- ([#2645](https://github.com/wp-graphql/wp-graphql/pull/2645)): fix: regression where fields of an object type were forced to be camelCase. This allows snake_case fields again.

## 1.13.4

### Chores / Bugfixes

- ([#2631](https://github.com/wp-graphql/wp-graphql/pull/2631)): simplify (DRY up) connection interface registration.

## 1.13.3

- fix: update versions for WordPress.org deploys

## 1.13.2

### Chores / Bugfixes

- ([#2627](https://github.com/wp-graphql/wp-graphql/pull/2627)): fix: Fixes regression where Connection classes were moved to another namespace. This adds deprecated classes back to the old namespace to extend the new classes. Thanks @justlevine!

## 1.13.1

### Chores / Bugfixes

- ([#2625](https://github.com/wp-graphql/wp-graphql/pull/2625)): fix: Fixes a regression to v1.13.0 where mutations registered with an uppercase first letter weren't properly being transformed to a lowercase first letter when the field is added to the Schema.

## 1.13.0

### Possible Breaking Change for some users

The work to introduce the `Connection` and `Edge` (and other) Interfaces required the `User.revisions` and `RootQuery.revisions` connection to
change from resolving to the `ContentRevisionUnion` type and instead resolve to the `ContentNode` type.

We believe that it's highly likely that most users will not be impacted by this change.

Any queries that directly reference the following types:

- `...on UserToContentRevisionUnionConnection`
- `...on RootQueryToContentRevisionUnionConnection`

Would need to be updated to reference these types instead:

- `...on UserToRevisionsConnection`
- `...on RootQueryToRevisionsConnection`

For example:

#### BEFORE

```graphql
{
  viewer {
    revisions {
      ... on UserToContentRevisionUnionConnection {
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
  }
  revisions {
    ... on RootQueryToContentRevisionUnionConnection {
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
}
```

#### AFTER

```graphql
{
  viewer {
    revisions {
      ... on UserToRevisionsConnection {
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
  }
  revisions {
    ... on RootQueryToRevisionsConnection {
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
}
```

### New Features

- ([#2617](https://github.com/wp-graphql/wp-graphql/pull/2617): feat: Introduce Connection, Edge and other common Interfaces.
- ([#2563](https://github.com/wp-graphql/wp-graphql/pull/2563): feat: refactor mutation registration to use new `WPMutationType`. Thanks @justlevine!
- ([#2557](https://github.com/wp-graphql/wp-graphql/pull/2557): feat: add `deregister_graphql_type()` access function and corresponding `graphql_excluded_types` filter. Thanks @justlevine!
- ([#2546](https://github.com/wp-graphql/wp-graphql/pull/2546): feat: Add new `register_graphql_edge_fields()` and `register_graphql_connection_where_args()` access functions. Thanks @justlevine!

### Chores / Bugfixes

- ([#2622](https://github.com/wp-graphql/wp-graphql/pull/2622): fix: deprecate the `previews` field for non-publicly queryable post types, and limit the `Previewable` Interface to publicly queryable post types.
- ([#2614](https://github.com/wp-graphql/wp-graphql/pull/2614): chore(deps): bump loader-utils from 2.0.3 to 2.0.4.
- ([#2540](https://github.com/wp-graphql/wp-graphql/pull/2540): fix: deprecate `Comment.approved` field in favor of `Comment.status: CommentStatusEnum`. Thanks @justlevine!
- ([#2542](https://github.com/wp-graphql/wp-graphql/pull/2542): Move parse_request logic in `NodeResolver::resolve_uri()` to its own method. Thanks @justlevine!

## 1.12.2

### New Features:

- ([#2541](https://github.com/wp-graphql/wp-graphql/pull/2541)): feat: Obfuscate SendPasswordResetEmail response. Thanks @justlevine!

### Chores / Bugfixes

- ([#2544](https://github.com/wp-graphql/wp-graphql/pull/2544)): chore: log and cleanup deprecations. Thanks @justlevine!
- ([#2605](https://github.com/wp-graphql/wp-graphql/pull/2605)): chore: bump tested version of WordPress to 6.1. Thanks @justlevine!
- ([#2606](https://github.com/wp-graphql/wp-graphql/pull/2606)): fix: update resolver in post->author connection to be more strict about the value of the author ID
- ([#2609](https://github.com/wp-graphql/wp-graphql/pull/2609)): chore(deps): bump loader-utils from 2.0.2 to 2.0.3

## 1.12.1

### New Features

- ([#2593](https://github.com/wp-graphql/wp-graphql/pull/2593)): feat: use sha256 instead of md5 for hashing queryId
- ([#2581](https://github.com/wp-graphql/wp-graphql/pull/2581)): feat: support deprecation reason when using `register_graphql_connection`.
- ([#2603](https://github.com/wp-graphql/wp-graphql/pull/2603)): feat: add GraphQL operation name to x-graphql-keys headers.

### Chores / Bugfixes

- ([#2472](https://github.com/wp-graphql/wp-graphql/pull/2472)): fix: Return CommentAuthor avatar urls in public requests. Thanks @justlevine!
- ([#2549](https://github.com/wp-graphql/wp-graphql/pull/2549)): chore: fix bug_report.yml description input. Thanks @justlevine!
- ([#2582](https://github.com/wp-graphql/wp-graphql/pull/2582)): fix(noderesolver): adding extra_query_vars in graphql_pre_resolve_uri. Thanks @yanmorinokamca!
- ([#2583](https://github.com/wp-graphql/wp-graphql/pull/2583)): chore: prepare docs for new website. Thanks @moonmeister!
- ([#2590](https://github.com/wp-graphql/wp-graphql/pull/2590)): fix: Add list of node types as X-GraphQL-Keys instead of list of edge types
- ([#2599](https://github.com/wp-graphql/wp-graphql/pull/2599)): fix: only use Appsero `add_plugin_data` if the method exists in the version of the Appsero client that's loaded.
- ([#2600](https://github.com/wp-graphql/wp-graphql/pull/2600)): docs: fix contributing doc render errors. Thanks @moonmeister!

## 1.12.0

### Upgrading

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

### New Features

- ([#2399](https://github.com/wp-graphql/wp-graphql/pull/2399)): New Schema Customization options for register_post_type and register_taxonomy. Thanks @justlevine! Thanks also to @kidunot89 for prototyping features and starting discussions that lead to this!
- ([#2565](https://github.com/wp-graphql/wp-graphql/pull/2565)): Expose X-GraphQL-URL header.

### Chores / Bugfixes

- ([#2568](https://github.com/wp-graphql/wp-graphql/pull/2568)): Fix typo in docs. Thanks @altearius!
- ([#2569](https://github.com/wp-graphql/wp-graphql/pull/2569)): Update Appsero Client SDK.
- ([#2571](https://github.com/wp-graphql/wp-graphql/pull/2571)): Dependabot bumps.
- ([#2572](https://github.com/wp-graphql/wp-graphql/pull/2572)): Fixes a bug in the GraphiQL Query Composer when working with fields that return Unions. Thanks @chrisherold!
- ([#2556](https://github.com/wp-graphql/wp-graphql/pull/2556)): Updates script that installs test environment to use env vars. Makes spinning up environments more convenient for contributors. Thanks @justlevine!
- ([#2538](https://github.com/wp-graphql/wp-graphql/pull/2538)): Updates phpstan and fixes surfaced issues. Thanks @justlevine!
- ([#2545](https://github.com/wp-graphql/wp-graphql/pull/2545)): Update WPBrowser to v3.1.6 and update test for SendPasswordResetEmail. Thanks @justlevine!

## 1.11.3

### Chores / Bugfixes

- ([#2555](https://github.com/wp-graphql/wp-graphql/pull/2555)): Further changes to `X-GraphQL-Keys` header output. Truncate keys based on a filterable max length. Output the skipped keys in extensions payload for debugging, and add `skipped:$type` keys to the X-GraphQL-Keys header for nodes that are skipped.

## 1.11.2

### Chores / Bugfixes

- ([#2551](https://github.com/wp-graphql/wp-graphql/pull/2551)): Chunks X-GraphQL-Keys header into multiple headers under a set max header limit length.
- ([#2539](https://github.com/wp-graphql/wp-graphql/pull/2539)): Set IDE direction to prevent breaks in RTL mode. Thanks @justlevine!
- ([#2549](https://github.com/wp-graphql/wp-graphql/pull/2549)): Fix bug_report.yml field to be textarea instead of input. Thanks @justlevine!

## 1.11.1

### Chores / Bugfixes

- ([#2530](https://github.com/wp-graphql/wp-graphql/pull/2530)): Fixes a regression introduced in v1.11.0 where querying menuItems with parentId where arg set to 0 was returning all menuItems instead of just top level items.

## 1.11.0

### New Features

- ([#2519](https://github.com/wp-graphql/wp-graphql/pull/2519)): Add new "QueryAnalyzer" class which tracks Types, Models and Nodes asked for and returned in a request and adds them to the response headers.
- ([#2519](https://github.com/wp-graphql/wp-graphql/pull/2519)): Add 2nd argument to `graphql()` function that will return the `Request` object instead executing and returning the response.
- ([#2522](https://github.com/wp-graphql/wp-graphql/pull/2522)): Allow global/database IDs in Comment connection where args. Thanks @justlevine!
- ([#2523](https://github.com/wp-graphql/wp-graphql/pull/2523)): Allow global/database IDs in MenuItem connection where args ID Inputs. Thanks @justlevine!
- ([#2524](https://github.com/wp-graphql/wp-graphql/pull/2524)): Allow global/database IDs in Term connection where args ID Inputs. Thanks @justlevine!
- ([#2525](https://github.com/wp-graphql/wp-graphql/pull/2525)): Allow global/database IDs in Post connection where args ID Inputs. Thanks @justlevine!

### Chores / Bugfixes

- ([#2521](https://github.com/wp-graphql/wp-graphql/pull/2521)): Refactor `$args` in AbstractConnectionResolver. Thanks @justlevine!
- ([#2526](https://github.com/wp-graphql/wp-graphql/pull/2526)): Ensure tracked data in QueryAnalyzer is unique.

## 1.10.0

### Upgrading

PR ([#2490](https://github.com/wp-graphql/wp-graphql/pull/2490)) fixes a bug that some users were using as a feature.

When a page is marked as the "Posts Page" WordPress does not resolve that page by URI, and this bugfix no longer will resolve that page by URI.

[Here](https://github.com/wp-graphql/wp-graphql/issues/2486#issuecomment-1232169375), you can read more about why this change was made and find a snippet of code that will bring the old functionality back if you've built features around it.

### New Features

- ([#2503](https://github.com/wp-graphql/wp-graphql/pull/2503)): Enable codeception debugging via Github Actions. Thanks @justlevine!
- ([#2502](https://github.com/wp-graphql/wp-graphql/pull/2502)): Add `idType` arg to `RootQuery.comment`. Thanks @justlevine!
- ([#2505](https://github.com/wp-graphql/wp-graphql/pull/2505)): Return user after `resetUserPassword` mutation. Thanks @justlevine!

### Chores / Bugfixes

- ([#2482](https://github.com/wp-graphql/wp-graphql/pull/2482)): Add PHP Code Sniffer support for the WordPress.com VIP GO standard. Thanks @renatonascalves!
- ([#2490](https://github.com/wp-graphql/wp-graphql/pull/2490)): Fix bug related to querying the page set as "Posts Page"
- ([#2497](https://github.com/wp-graphql/wp-graphql/pull/2497)): Only enqueue admin scripts on the settings page. Thanks @justlevine!
- ([#2498](https://github.com/wp-graphql/wp-graphql/pull/2498)): Add `include` and `exclude` args to `MediaDetails.sizes`. Thanks @justlevine!
- ([#2499](https://github.com/wp-graphql/wp-graphql/pull/2499)): Check for multiple theme capabilities in the Theme Model. Thanks @justlevine!
- ([#2504](https://github.com/wp-graphql/wp-graphql/pull/2504)): Filter `mediaItems` query by `mimeType`. Thanks @justlevine!
- ([#2506](https://github.com/wp-graphql/wp-graphql/pull/2506)): Update descriptions for input fields that accept a `databaseId`. Thanks @justlevine!
- ([#2511](https://github.com/wp-graphql/wp-graphql/pull/2511)): Update link in docs to point to correct "nonce" example. Thanks @NielsdeBlaauw!

## 1.9.1

### Chores / Bugfixes

- ([#2471](https://github.com/wp-graphql/wp-graphql/pull/2471)): feat: PHPCS: enhancements to the Coding Standards Setup. Thanks @renatonascalves!
- ([#2472](https://github.com/wp-graphql/wp-graphql/pull/2472)): fix: return CommentAuthor avatar urls to public users. Thanks @justlevine!
- ([#2473](https://github.com/wp-graphql/wp-graphql/pull/2473)): fix: Update GraphiQL "user switch" to be accessible. Thanks @nickcernis!
- ([#2477](https://github.com/wp-graphql/wp-graphql/pull/2477)): fix(graphiql): graphiql fails if variables are invalid json

## 1.9.0

### Upgrading

There are 2 changes that **might** require action when updating to 1.9.0.

1. ([#2464](https://github.com/wp-graphql/wp-graphql/pull/2464))

When querying for a `nodeByUri`, if your site has the "page_for_posts" setting configured, the behavior of the `nodeByUri` query for that uri might be different for you.

Previously a bug caused this query to return a "Page" type, when it should have returned a "ContentType" Type.

The bug fix might change your application if you were using the bug as a feature.

2. ([#2457](https://github.com/wp-graphql/wp-graphql/pull/2457))

There were a lot of bug fixes related to connections to ensure they behave as intended. If you were querying lists of data, in some cases the data might be returned in a different order than it was before.

For example, using the "last" input on a Comment or User query should still return the same nodes, but in a different order than before.

This might cause behavior you don't want in your application because you had coded around the bug. This change was needed to support proper backward pagination.

### Chores / Bugfixes

- ([#2450](https://github.com/wp-graphql/wp-graphql/pull/2450)): Fix PHPCompatibility lint config. Thanks @justlevine!
- ([#2452](https://github.com/wp-graphql/wp-graphql/pull/2452)): Fixes a bug with `Comment.author` connections not properly resolving for public (non-authenticated) requests.
- ([#2453](https://github.com/wp-graphql/wp-graphql/pull/2453)): Update Github Workflows to use PHP 7.3. Thanks @justlevine!
- ([#2454](https://github.com/wp-graphql/wp-graphql/pull/2454)): Add linter to ensure Pull Requests use "Conventional Commit" standards.
- ([#2455](https://github.com/wp-graphql/wp-graphql/pull/2455)): Refactors and Lints the WPUnit tests. Cleans up some "leaky" data in test suites. Thanks @justlevine!
- ([#2457](https://github.com/wp-graphql/wp-graphql/pull/2457)): Refactor Connection Resolvers to better adhere to Relay Connection spec. This fixes several bugs related to pagination across connections, specifically User and Comment connections which didn't properly support backward pagination at all. Thanks @justlevine!
- ([#2460](https://github.com/wp-graphql/wp-graphql/pull/2460)): Update documentation for running tests with Docker. Thanks @markkelnar!
- ([#2463](https://github.com/wp-graphql/wp-graphql/pull/2463)): Add Issue templates to the repo. Thanks @justlevine!
- ([#2464](https://github.com/wp-graphql/wp-graphql/pull/2464)): Fixes node resolver when "page_for_posts" setting is set to a page.

## 1.8.7

### Chores / Bugfixes

- ([#2441](https://github.com/wp-graphql/wp-graphql/pull/2441)): Fix `contentNodes` field not showing if a taxonomy is registered without connected post types. Thanks @saimonh3!
- ([#2446](https://github.com/wp-graphql/wp-graphql/pull/2446)): Update "terser" from 5.11.0 to 5.14.2 (GraphiQL Dependency)
- ([#2440](https://github.com/wp-graphql/wp-graphql/pull/2440)): Update JS dependencies for GraphiQL

### New Features

- ([#2435](https://github.com/wp-graphql/wp-graphql/pull/2435)): Add filter in execute for query string. Thanks @markkelnar!
- ([#2432](https://github.com/wp-graphql/wp-graphql/pull/2432)): Add `query_id` to `after_execute_actions` for batch requests. Thanks @markkelnar!

## 1.8.6

### Chores / Bugfixes

- ([#2427](https://github.com/wp-graphql/wp-graphql/pull/2427)): Fixes a regression of the 1.8.3 release where there could be fatal errors when GraphQL Tracing is enabled and a queryId is used as a query param.

## 1.8.5

### Chores / Bugfixes

- ([#2422](https://github.com/wp-graphql/wp-graphql/pull/2422)): Fixes a regression of the 1.8.3 release where there could be fatal errors when GraphQL Tracing is enabled.

## 1.8.4

### Chores / Bugfixes

- ([#2416](https://github.com/wp-graphql/wp-graphql/pull/2416)): Fixes schema artifact workflow in Github.

## 1.8.3

### New Features

- ([#2388](https://github.com/wp-graphql/wp-graphql/pull/2388)): Adds ability to query menus by SLUG and LOCATION. Thanks @justlevine!

### Chores / Bugfixes

- ([#2412](https://github.com/wp-graphql/wp-graphql/pull/2412)): Update tests to run in PHP 8, 8.1 and with WordPress 6.0. Updates Docker Deploy workflow as well.
- ([#2411](https://github.com/wp-graphql/wp-graphql/pull/2411)): Fixes bug where menuItems "location" arg was conflicting if a taxonomy is also registered with "location" as its name.
- ([#2410](https://github.com/wp-graphql/wp-graphql/pull/2410)): Fixes a regression with Taxonomy Connection pagination.
- ([#2406](https://github.com/wp-graphql/wp-graphql/pull/2406)): Updates PHPUnit, WPBrowser and WPGraphQL Test Case for use in workflows. Thanks @justlevine!
- ([#2387](https://github.com/wp-graphql/wp-graphql/pull/2387)): Fixes a bug with asset versions when querying for Enqueued Scripts and Styles. Thanks @justlevine!

## 1.8.2

### New Features

- ([#2363](https://github.com/wp-graphql/wp-graphql/pull/2363)): Adds "uri" field to MenuItem type which resolves the path of the node which can then be used in a `nodeByUri` query to get the linked node. The path is relative and does not contain subdirectory path in a subdirectory multisite. the `path` field does include the multisite subdirectory path, still. Thanks @josephfusco and @justlevine!
- ([#2337](https://github.com/wp-graphql/wp-graphql/pull/2337)): Allows for either global ID or databaseId to be supplied in the ID field for user mutations. Thanks @justlevine!
- ([#2338](https://github.com/wp-graphql/wp-graphql/pull/2338)): Allows either global "relay" ID or databaseId for post object mutations. Thanks @justlevine!
- ([#2336](https://github.com/wp-graphql/wp-graphql/pull/2336)): Allows either global "relay" ID or databaseId for term object mutations. Thanks @justlevine!
- ([#2331](https://github.com/wp-graphql/wp-graphql/pull/2331)): Allows either global "relay" ID or databaseId for MediaItem object mutations. Thanks @justlevine!
- ([#2328](https://github.com/wp-graphql/wp-graphql/pull/2328)): Allows either global "relay" ID or databaseId for Comment object mutations. Thanks @justlevine!

### Chores / Bugfixes

- ([#2368](https://github.com/wp-graphql/wp-graphql/pull/2368)): Updates dependencies for Schema Linter workflow.
- ([#2369](https://github.com/wp-graphql/wp-graphql/pull/2369)): Replaces the Codecov badge in the README with Coveralls badge. Thanks @justlevine!
- ([#2374](https://github.com/wp-graphql/wp-graphql/pull/2374)): Updates descriptions for PostObjectFieldFormatEnum. Thanks @justlevine!
- ([#2375](https://github.com/wp-graphql/wp-graphql/pull/2375)): Sets up the testing integration workflow to be able to run in multisite. Adds one workflow that runs in multisite. Fixes tests related to multisite.
- ([#2376](https://github.com/wp-graphql/wp-graphql/pull/2276)): Adds support for `['auth']['callback']` and `isPrivate` for the `register_graphql_mutation()` API.
- ([#2379](https://github.com/wp-graphql/wp-graphql/pull/2379)): Fixes a bug where term mutations were adding slashes when being stored in the database.
- ([#2380](https://github.com/wp-graphql/wp-graphql/pull/2380)): Fixes a bug where WPGraphQL wasn't sending the Wp class to the `parse_request` filter as a reference.
- ([#2382](https://github.com/wp-graphql/wp-graphql/pull/2382)): Fixes a bug where `register_graphql_field()` was not being respected by GraphQL Types added to the schema to represent Setting Groups of the core WordPress `register_setting()` API.

## 1.8.1

### New Features

- ([#2349](https://github.com/wp-graphql/wp-graphql/pull/2349)): Adds tags to wpkses_post for WPGraphQL settings pages to be extended further. Thanks @eavonius!

### Chores / Bugfixes

- ([#2358](https://github.com/wp-graphql/wp-graphql/pull/2358)): Updates NPM dependencies. Thanks @dependabot!
- ([#2357](https://github.com/wp-graphql/wp-graphql/pull/2357)): Updates NPM dependencies. Thanks @dependabot!
- ([#2356](https://github.com/wp-graphql/wp-graphql/pull/2356)): Refactors codebase to take advantage of the work done in #2353. Thanks @justlevine!
- ([#2354](https://github.com/wp-graphql/wp-graphql/pull/2354)): Fixes console warnings in GraphiQL related to missing React keys.
- ([#2353](https://github.com/wp-graphql/wp-graphql/pull/2353)): Refactors the WPGraphQL::get_allowed_post_types() and WPGraphQL::get_allowed_taxonomies() functions. Thanks @justlevine!
- ([#2350](https://github.com/wp-graphql/wp-graphql/pull/2350)): Fixes bug where Comment Authors were not always properly returning

## 1.8.0

### New Features

- ([#2286](https://github.com/wp-graphql/wp-graphql/pull/2286)): Introduce new `Utils::get_database_id_from_id()` function to help DRY up some code around inputs that can accept Global IDs or Database IDs. Thanks @justlevine!
- ([#2327](https://github.com/wp-graphql/wp-graphql/pull/2327)): Update capability for plugin queries. Changes from `update_plugins` to `activate_plugins`. Thanks @justlevine!
- ([#2298](https://github.com/wp-graphql/wp-graphql/pull/2298)): Adds `$where` arguments to Plugin Connections. Thanks @justlevine!
- ([#2332](https://github.com/wp-graphql/wp-graphql/pull/2332)): Adds new Github workflow to build the GraphiQL App on pushes to `develop` and `master`. This should allow users that install WPGraphQL to install/update with Composer and have the GraphiQL app running, instead of having to run `npm install && npm run build` in addition to `composer install`.

### Chores / Bugfixes

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

## 1.7.2

### Chores / Bugfixes

- ([#2276](https://github.com/wp-graphql/wp-graphql/pull/2276)): Fixes a bug where `generalSettings.url` field was not in the Schema for multisite installs.
- ([#2278](https://github.com/wp-graphql/wp-graphql/pull/2278)): Adds a composer post-install script that installs JS dependencies and builds the JS app when `composer install` is run
- ([#2277](https://github.com/wp-graphql/wp-graphql/pull/2277)): Adds a condition to the docker image to only run `npm` scripts if the project has a package.json. Thanks @markkelnar!

## 1.7.1

### Chores / Bugfixes

- ([#2268](https://github.com/wp-graphql/wp-graphql/pull/2268)): Fixes a bug in GraphiQL that would update browser history with every change to a query param.

## 1.7.0

### Chores / Bugfixes

- ([#2228](https://github.com/wp-graphql/wp-graphql/pull/2228)): Allows optional fields to be set to empty values in the `updateUser` mutation. Thanks @victormattosvm!
- ([#2247](https://github.com/wp-graphql/wp-graphql/pull/2247)): Add WordPress 5.9 to the automated testing matrix. Thanks @markkelnar!
- ([#2242](https://github.com/wp-graphql/wp-graphql/pull/2242)): Adds End 2 End tests to test GraphiQL functionality in the admin.
- ([#2261](https://github.com/wp-graphql/wp-graphql/pull/2261)): Fixes a bug where the `pageByUri` query might return incorrect data when custom permalinks are set. Thanks @blakewilson!
- ([#2263](https://github.com/wp-graphql/wp-graphql/pull/2263)): Adds documentation entry for WordPress Application Passwords guide. Thanks @abhisekmazumdar!
- ([#2262](https://github.com/wp-graphql/wp-graphql/pull/2262)): Fixes a bug where settings registered via the core `register_setting()` API would cause Schema Introspection failures, causing GraphiQL and other tools to not work properly.

### New Features

- ([#2248](https://github.com/wp-graphql/wp-graphql/pull/2248)): WPGraphiQL (the GraphiQL IDE in the WordPress dashboard) has been re-built to have an extension architecture and some updated user interfaces. Thanks for contributing to this effort @scottyzen!
- ([#2246](https://github.com/wp-graphql/wp-graphql/pull/2246)): Adds support for querying the `avatar` for the CommentAuthor Type and the Commenter Interface type.
- ([#2236](https://github.com/wp-graphql/wp-graphql/pull/2236)): Introduces new `graphql_model_prepare_fields` filter and deprecates `graphql_return_modeled_data` filter. Thanks @justlevine!
- ([#2265](https://github.com/wp-graphql/wp-graphql/pull/2265)): Adds opt-in telemetry tracking via Appsero, to allow us to collect helpful information for prioritizing future feature work, etc.

## 1.6.12

### Chores / Bugfixes

- ([#2209](https://github.com/wp-graphql/wp-graphql/pull/2209)): Adds WordPress 5.8 to the testing matrix. Thanks @markkelnar!
- ([#2211](https://github.com/wp-graphql/wp-graphql/pull/2211)): ([#2216](https://github.com/wp-graphql/wp-graphql/pull/2216)), ([#2221](https://github.com/wp-graphql/wp-graphql/pull/2221)), ([#2223](https://github.com/wp-graphql/wp-graphql/pull/2223)): Bumps NPM dependencies for GraphiQL
- ([#2212](https://github.com/wp-graphql/wp-graphql/pull/2212)): Fixes how the `TermObject.uri` strips the link down to the path. Thanks @theodesp!
- ([#2215](https://github.com/wp-graphql/wp-graphql/pull/2215)): Fixes testing environment to play nice with a recent wp-browser update.
- ([#2218](https://github.com/wp-graphql/wp-graphql/pull/2218)): Update note on settings page explaining that Public Introspection is enabled when GraphQL Debug mode is enabled.
- ([#2220](https://github.com/wp-graphql/wp-graphql/pull/2220)): Adds CodeQL workflow to analyze JavaScript on PRs

## 1.6.11

### Chores / Bugfixes

- ([#2177](https://github.com/wp-graphql/wp-graphql/pull/2177)): Prevents PHP notice when clientMutationId is not set on mutations. Thanks @oskarmodig!
- ([#2182](https://github.com/wp-graphql/wp-graphql/pull/2182)): Fixes bug where the graphql endpoint couldn't be accessed by a site domain other than the site_url(). Thanks @moommeister!
- ([#2184](https://github.com/wp-graphql/wp-graphql/pull/2184)): Fixes regression where duplicate type warning was not being displayed after lazy type loading was added in v1.6.0.
- ([#2189](https://github.com/wp-graphql/wp-graphql/pull/2189)): Fixes bug with content node previews
- ([#2196](https://github.com/wp-graphql/wp-graphql/pull/2196)): Further bug fixes for content node previews. Thanks @apmattews!
- ([#2197](https://github.com/wp-graphql/wp-graphql/pull/2197)): Fixes call to prepare_fields() to not be called statically. Thanks @justlevine!

### New Features

- ([#2188](https://github.com/wp-graphql/wp-graphql/pull/2188)): Adds `contentTypeName` to the `ContentNode` type.
- ([#2199](https://github.com/wp-graphql/wp-graphql/pull/2199)): Pass the TypeRegistry instance through to the `graphql_schema_config` filter.
- ([#2204](https://github.com/wp-graphql/wp-graphql/pull/2204)): Allow a `root_value` to be set when calling the `graphql()` function.
- ([#2203](https://github.com/wp-graphql/wp-graphql/pull/2203)): Adds new filter to mutations to filter the input args before execution, and a new action after execution, before returning the mutation, to allow additional data to be stored during mutations. Thanks @markkelnar!

## 1.6.10

- Updating stable tag for WordPress.org

## 1.6.9

- No functional changes from v1.6.8. Fixing an issue with deploy to WordPress.org

## 1.6.8

### Chores / Bugfixes

- ([#2143](https://github.com/wp-graphql/wp-graphql/pull/2143)): Adds `taxonomyName` field to the `TermNode` interface. Thanks @jeanfredrik!
- ([#2168](https://github.com/wp-graphql/wp-graphql/pull/2168)): Allows the GraphiQL screen markup to be filtered
- ([#2150](https://github.com/wp-graphql/wp-graphql/pull/2150)): Updates GraphiQL npm dependency to v1.4.7
- ([#2145](https://github.com/wp-graphql/wp-graphql/pull/2145)): Fixes a bug with cursor pagination stability

### New Features

- ([#2141](https://github.com/wp-graphql/wp-graphql/pull/2141)): Adds a new `graphql_wp_connection_type_config` filter to allow customizing connection configurations. Thanks @justlevine!

## 1.6.7

### Chores / Bugfixes

- ([#2135](https://github.com/wp-graphql/wp-graphql/pull/2135)): Fixes permission check in the Post model layer. Posts of a `'publicly_queryable' => true` post_type can be queried publicly (non-authenticated requests) via WPGraphQL, even if the post_type is set to `'public' => false`. Thanks @kellenmace!
- ([#2093](https://github.com/wp-graphql/wp-graphql/pull/2093)): Fixes `Post.pinged` field to properly return an array. Thanks @justlevine!
- ([#2132](https://github.com/wp-graphql/wp-graphql/pull/2132)): Fix issue where querying posts by slug could erroneously return null. Thanks @ChrisWiegman!
- ([#2127](https://github.com/wp-graphql/wp-graphql/pull/2127)): Update endpoint in documentation examples. Thanks @RafidMuhyim!

## 1.6.6

### New Features

- ([#2106](https://github.com/wp-graphql/wp-graphql/pull/2106)): Add new `pre_graphql_execute_request` filter to better support full query caching. Thanks @markkelnar!
- ([#2123](https://github.com/wp-graphql/wp-graphql/pull/2123)): Add new `graphql_dataloader_get_cached` filter to better support persistent object caching in the Model Layer. Thanks @kidunot89!

### Chores / Bugfixes

- ([#2094](https://github.com/wp-graphql/wp-graphql/pull/2094)): fix broken link in docs. Thanks @duffn!
- ([#2108](https://github.com/wp-graphql/wp-graphql/pull/2108)): Update lucatume/wp-browser dependency. Thanks @markkelnar!
- ([#2111](https://github.com/wp-graphql/wp-graphql/pull/2111)): Correct variable name passed to filter. Thanks @markkelnar!
- ([#2112](https://github.com/wp-graphql/wp-graphql/pull/2112)): Doc typo corrections. Thanks @nexxai!
- ([#2115](https://github.com/wp-graphql/wp-graphql/pull/2115)): Updates to GraphiQL npm dependencies. Thanks @alexghirelli!
- ([#2124](https://github.com/wp-graphql/wp-graphql/pull/2124)): Updates `tmpl` npm dependency.

## 1.6.5

### Chores / Bugfixes

- ([#2081](https://github.com/wp-graphql/wp-graphql/pull/2081)): Set `is_graphql_request` earlier in Request.php. Thanks @jordanmaslyn!
- ([#2085](https://github.com/wp-graphql/wp-graphql/pull/2085)): Bump codeception from 4.1.21 to 4.1.22

### New Features

- ([#2076](https://github.com/wp-graphql/wp-graphql/pull/2076)): Add `$graphiql` global variable to allow extensions the ability to more easily remove hooks/filters from the class.

## 1.6.4

### Chores / Bugfixes

- ([#2076](https://github.com/wp-graphql/wp-graphql/pull/2076)): Updates WPGraphiQL IDE to use latest react, GraphiQL and other dependencies.

### New Features

- ([#2076](https://github.com/wp-graphql/wp-graphql/pull/2076)): WPGraphiQL IDE now resizes when the browser window is resized.

## 1.6.3

### Chores / Bugfixes

- ([#2064](https://github.com/wp-graphql/wp-graphql/pull/2064)): Fixes bug where using `asQuery` argument could return an error instead of a null when the ID passed could not be previewed.
- ([#2072](https://github.com/wp-graphql/wp-graphql/pull/2072)): Fixes bug (regression with 1.6) where Object Types for page templates were not properly loading in the Schema after Lazy Loading was introduced in 1.6.
- ([#2059](https://github.com/wp-graphql/wp-graphql/pull/2059)): Update typos and links in docs. Thanks @nicolnt!
- ([#2058](https://github.com/wp-graphql/wp-graphql/pull/2058)): Fixes bug in the filter_post_meta_for_previews was causing PHP warnings. Thanks @zolon4!

## 1.6.2

### Chores / Bugfixes

- ([#2051](https://github.com/wp-graphql/wp-graphql/pull/2051)): Fixes a bug where Types that share the same name as a PHP function (ex: `Header` / `header()`) would try and call the function when loading the Type. See ([Issue #2047](https://github.com/wp-graphql/wp-graphql/issues/2047))
- ([#2055](https://github.com/wp-graphql/wp-graphql/pull/2055)): Fixes a bug where Connections registered from Types were adding connections to the registry too late causing some queries to fail. See Issue ([Issue #2054](https://github.com/wp-graphql/wp-graphql/issues/2054))

## 1.6.1

### Chores / Bugfixes

- ([#2043](https://github.com/wp-graphql/wp-graphql/pull/2043)): Fixes a regression with GraphQL Request Execution that was causing Gatsby and some other CI tools to fail builds.

## 1.6.0

### Chores / Bugfixes

- ([#2000](https://github.com/wp-graphql/wp-graphql/pull/2000)): This fixes issue where all Types of the Schema were loaded for each GraphQL request. Now only the types required to fulfill the request are loaded on each request. Thanks @chriszarate!
- ([#2031](https://github.com/wp-graphql/wp-graphql/pull/2031)): This fixes a performance issue in the WPGraphQL model layer where determining whether a User is a published author was generating expensive MySQL queries on sites with a lot of users and a lot of content. Thanks @chriszarate!

## 1.5.8

### Chores / Bugfixes

- ([#2038](https://github.com/wp-graphql/wp-graphql/pull/2038)): Exclude documentation directory from code archived by composer and deployed to WordPress.org

## 1.5.7

### Chores / Bugfixes

- Update to trigger a missed deploy to WordPress.org. no functional changes from v1.5.6

## 1.5.6

### Chores / Bugfixes

- ([#2035](https://github.com/wp-graphql/wp-graphql/pull/2035)): Fixes a bug where variables passed to `after_execute_actions` weren't properly set for Batch Queries.

### New Features

- ([#2035](https://github.com/wp-graphql/wp-graphql/pull/2035)): (Yes, same PR as the bugfix above). Adds 2 new actions `graphql_before_execute` and `graphql_after_execute` to allow actions to run before/after the execution of entire Batch requests vs. the hooks that currently run _within_ each the execution of each operation within a request.

## 1.5.5

### Chores / Bugfixes

- ([#2023](https://github.com/wp-graphql/wp-graphql/pull/2023)): Fixes issue with deploying Docker Testing Images. Thanks @markkelnar!
- ([#2025](https://github.com/wp-graphql/wp-graphql/pull/2025)): Update test workflow to test against WordPress 5.8 (released today) and updates the readme.txt to reflect the plugin has been tested up to 5.8
- ([#2028](https://github.com/wp-graphql/wp-graphql/pull/2028)): Update Codeception test environment to prevent WordPress from entering maintenance mode during tests.

## 1.5.4

### Chores / Bugfixes

- ([#2012](https://github.com/wp-graphql/wp-graphql/pull/2012)): Adds functional tests back to the Github testing workflow!
- ([#2016](https://github.com/wp-graphql/wp-graphql/pull/2016)): Ignore Schema Linter workflow on releases, run on PRs only.
- ([#2019](https://github.com/wp-graphql/wp-graphql/pull/2019)): Deploy Docker Testing Image on releases. Thanks @markkelnar!

### New Features

- ([#2011](https://github.com/wp-graphql/wp-graphql/pull/2011)): Introduces a new API to allow Types to register connections at the Type registration level and refactors several internal Types to use this new API.

## 1.5.3

### Chores / Bugfixes

- ([#2001](https://github.com/wp-graphql/wp-graphql/pull/2001)): Updates Docker environment to use MariaDB instead of MySQL to play nice with those fancy M1 Macs. Thanks @chriszarate!
- ([#2002](https://github.com/wp-graphql/wp-graphql/pull/2002)): Add PHP8 Docker image to deploy upon releases. Thanks @markkelnar!
- ([#2006](https://github.com/wp-graphql/wp-graphql/pull/2006)): Update Docker to use $PROJECT_DIR variable instead of hardcoded value to allow composed docker images to run their own tests from their own project. Thanks @markkelnar!
- ([#2007](https://github.com/wp-graphql/wp-graphql/pull/2007)): Update broken links to Relay spec. Thanks @ramyareye!

### New Features

- ([#2009](https://github.com/wp-graphql/wp-graphql/pull/2009)): Adds new WPConnectionType class and refactors register_graphql_connection() to use the class. Functionality should be the same, but this sets the codebase up for some new connection APIs.

## 1.5.2

### Chores / Bugfixes

- ([#1992](https://github.com/wp-graphql/wp-graphql/pull/1992)): Fixes bug that caused conflict with the AmpWP plugin.
- ([#1994](https://github.com/wp-graphql/wp-graphql/pull/1994)): Fixes bug where querying a node by uri could return a node of a different post type.
- ([#1997](https://github.com/wp-graphql/wp-graphql/pull/1997)): Fixes bug where Enums could be generated with no values when a taxonomy was set to show in GraphQL but it's associated post_type(s) are not shown in graphql.

## 1.5.1

### Chores / Bugfixes

- ([#1987](https://github.com/wp-graphql/wp-graphql/pull/1987)): Fixes Relay Spec link in documentation Thanks @ramyareye!
- ([#1988](https://github.com/wp-graphql/wp-graphql/pull/1988)): Fixes docblock and parameter Type in preview filter callback. Thanks @zolon4!
- ([#1986](https://github.com/wp-graphql/wp-graphql/pull/1986)): Update WP environment variables for testing with PHP8. Thanks @markkelnar!

### New Features

- ([#1984](https://github.com/wp-graphql/wp-graphql/pull/1984)): Support using WPGraphQL with PHP 8!
- ([#1990](https://github.com/wp-graphql/wp-graphql/pull/1990)): Adds `isTermNode` and `isContentNode` to the `UniformResourceIdentifiable` Interface

## 1.5.0

## Chores / Bugfixes

- ([#1865](https://github.com/wp-graphql/wp-graphql/pull/1865)): Change `MenuItem.path` field from `nonNull` to nullable as the value can be null in WordPress. Thanks @furedal!
- ([#1978](https://github.com/wp-graphql/wp-graphql/pull/1978)): Use "docker compose" instead of docker-compose in the run-docker.sh script. Thanks @markkelnar!
- ([#1974](https://github.com/wp-graphql/wp-graphql/pull/1974)): Separates app setup and app-post-setup scripts for use in the Docker/test environment setup. Thanks @markkelnar!
- ([#1972](https://github.com/wp-graphql/wp-graphql/pull/1972)): Pushes Docker images when new releases are tagged. Thanks @markkelnar!
- ([#1970](https://github.com/wp-graphql/wp-graphql/pull/1970)): Change Docker Image names specific to the WP and PHP versions. Thanks @markkelnar!
- ([#1967](https://github.com/wp-graphql/wp-graphql/pull/1967)): Update xdebug max nesting level to allow coverage to pass with resolver instrumentation active. Thanks @markkelnar!

## New Features

- ([#1977](https://github.com/wp-graphql/wp-graphql/pull/1977)): Allow same string to be passed for "graphql_single_name" and "graphql_plural_name" (ex: "deer" and "deer") when registering Post Types and Taxonomies. Same strings will be prefixed with "all" for plurals. Thanks @apmatthews!
- ([#1787](https://github.com/wp-graphql/wp-graphql/pull/1787)): Adds a new "ContentTypesOf. Thanks @plong0!

## 1.4.3

- See previous release. This was a version bump to fix a missed deploy.

## 1.4.2

### Chores / Bugfixes

- ([#1963](https://github.com/wp-graphql/wp-graphql/pull/1963)): Fixes a regression in v1.4.0 where the `uri` field on Terms was returning `null`. The issue was actually wider than that as resolvers on Object Types that implement interfaces weren't being fully respected.
- ([#1956](https://github.com/wp-graphql/wp-graphql/pull/1956)): Adds `SpaceAfterFunction` Code Sniffer rule and adjusts the codebase to respect the rule. Thanks @markkelnar!

## 1.4.1

### Chores / Bugfixes

- ([#1958](https://github.com/wp-graphql/wp-graphql/pull/1958)): Fixes a regression in 1.4.0 where `register_graphql_interfaces_to_types` was broken.

## 1.4.0

### Chores / Bugfixes

- ([#1951](https://github.com/wp-graphql/wp-graphql/pull/1951)): Fixes bug with the `uri` field. Some Types in the Schema had the `uri` field as nullable field and some as a non-null field. This fixes it and makes the field consistently nullable as some Nodes with a URI might have a `null` value if the node is private.
- ([#1953](https://github.com/wp-graphql/wp-graphql/pull/1953)): Fixes bug with Settings groups with underscores not showing in the Schema properly. Thanks @markkelnar!

### New Features

- ([#1951](https://github.com/wp-graphql/wp-graphql/pull/1951)): Updates GraphQL-PHP to v14.8.0 (from 14.4.0) and Introduces the ability for Interfaces to implement other Interfaces!

## 1.3.10

### Chores / Bugfixes

- ([#1940](https://github.com/wp-graphql/wp-graphql/pull/1940)): Adds Breaking Change inspector to run on new Pull Requests. Thanks @markkelnar!
- ([#1937](https://github.com/wp-graphql/wp-graphql/pull/1937)): Fixed typo in documentation. Thanks @LeonardoDB!
- ([#1923](https://github.com/wp-graphql/wp-graphql/issues/1923)): Fixed bug where User Model didn't support the databaseId field

### New Features

- ([#1938](https://github.com/wp-graphql/wp-graphql/pull/1938)): Adds new functionality to the `register_graphql_connection()` API. Thanks @kidunot89!

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

### Notable changes

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
- ([#1699](https://github.com/wp-graphql/wp-graphql/pull/1699)) Set default value for 2nd parameter on `Tracker->get_info()` method. Thanks @SpartakusMd!

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
