---
uri: "/acf-options-pages/"
title: "ACF Options Pages"
---

ACF PRO includes a feature called [Options Pages](https://www.advancedcustomfields.com/resources/options-page/), admin pages for managing site-wide data that isn't tied to a specific post, term, or user. WPGraphQL for ACF can expose Options Pages, and the ACF field groups assigned to them, to the GraphQL Schema so client applications can query the data.

This document covers how to add an Options Page to the schema, how it maps to GraphQL, and, importantly, what exposing an Options Page means for who can read its data.

## Registering an Options Page for GraphQL

### Using the ACF UI

ACF PRO 6.2+ lets you register Options Pages from the WordPress admin (ACF > Options Pages). WPGraphQL for ACF adds a "GraphQL" tab to the registration screen with the following settings:

- **Show in GraphQL (show_in_graphql):** Whether the Options Page should be added to the GraphQL Schema. Defaults to **off** for Options Pages registered via the UI.
- **GraphQL Type Name (graphql_type_name):** How the Options Page should be referenced in the GraphQL Schema. Defaults to a formatted version of the Page Title.

### Using PHP

Options Pages registered with [`acf_add_options_page()`](https://www.advancedcustomfields.com/resources/acf_add_options_page/) accept the same settings in the config array:

```php
acf_add_options_page( [
	'page_title'        => 'Site Settings',
	'menu_slug'         => 'site-settings',
	'capability'        => 'edit_posts',
	'show_in_graphql'   => true,
	'graphql_type_name' => 'SiteSettings',
] );
```

**NOTE:** Options Pages registered with PHP are treated as `show_in_graphql => true` unless you explicitly set `show_in_graphql => false`. This differs from UI-registered pages, which default to off. If you register an Options Page in PHP and do not want it in the schema, opt it out explicitly:

```php
acf_add_options_page( [
	'page_title'      => 'Internal Settings',
	'menu_slug'       => 'internal-settings',
	'show_in_graphql' => false,
] );
```

The default for pages that don't declare a value can also be controlled with the `wpgraphql/acf/options_page/show_in_graphql` filter.

## How Options Pages map to the Schema

When an Options Page is shown in GraphQL, WPGraphQL for ACF registers:

- An object type named after the page (from `graphql_type_name`, or formatted from the Page Title), implementing the `AcfOptionsPage` and `Node` interfaces. It includes fields such as `id`, `pageTitle`, `menuTitle`, and `parentId`.
- A field on `RootQuery` named after the type (e.g. a `SiteSettings` type is queryable via the `siteSettings` root field).

Field groups are assigned to an Options Page using the "Options Page" location rule. An assigned field group's fields become queryable on the Options Page's type unless the field group is opted out of GraphQL. Note that field groups are **shown in GraphQL by default**: the "Show in GraphQL" toggle in the field group UI defaults to on, and field groups registered in PHP are included unless they set `show_in_graphql => false`.

```php
acf_add_local_field_group( [
	'key'                => 'group_site_settings',
	'title'              => 'Site Settings Fields',
	'show_in_graphql'    => true,
	'graphql_field_name' => 'siteSettingsFields',
	'fields'             => [
		[
			'key'   => 'field_site_tagline',
			'label' => 'Tagline',
			'name'  => 'tagline',
			'type'  => 'text',
		],
	],
	'location'           => [
		[
			[
				'param'    => 'options_page',
				'operator' => '==',
				'value'    => 'site-settings',
			],
		],
	],
] );
```

This can then be queried like so:

```graphql
{
  siteSettings {
    pageTitle
    siteSettingsFields {
      tagline
    }
  }
}
```

## Who can query Options Page data?

**Adding an Options Page to the GraphQL Schema makes its data publicly queryable.** The GraphQL endpoint is public, and Options Page data (the page's metadata and the values of any field groups assigned to it that are shown in GraphQL) resolves for any request, including unauthenticated ones.

This is consistent with how ACF Options data works elsewhere: any theme or plugin can read the values with `get_field()` and render them on the public front end, which is the primary use case for Options Pages (global site settings, header/footer content, etc.).

### The `capability` setting does not restrict GraphQL queries

The `capability` argument on `acf_add_options_page()` controls who can see the admin menu page and **edit** the options in wp-admin. It is not a read restriction, in GraphQL or anywhere else in ACF. Setting `capability => 'manage_options'` on an Options Page does not prevent its data from being queried once the page is shown in GraphQL.

### Do not expose sensitive data

Be aware that the defaults are permissive: Options Pages registered in PHP and field groups (registered in PHP or via the UI) are included in the schema unless explicitly opted out. If an Options Page holds data that should not be public (API keys, credentials, internal configuration), do not show it in GraphQL:

- Leave **Show in GraphQL** off (UI), or set `show_in_graphql => false` (PHP) on the Options Page.
- Alternatively, keep the page in the schema but exclude a sensitive field group (`show_in_graphql => false` on the field group) or an individual field (`show_in_graphql => false` on the field).

More granular, capability-based access control for fields, field groups, and Options Pages (a `graphql_capability` setting, enforced when values resolve) is being designed in [wp-graphql/wp-graphql#4275](https://github.com/wp-graphql/wp-graphql/issues/4275).

## Breaking change considerations

Like other schema mappings, removing an Options Page from the schema, or changing its GraphQL Type Name after client applications are querying it, is a breaking change for those clients. Make changes with caution.
