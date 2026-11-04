---
uri: "/adding-acf-fields-to-the-wpgraphql-schema/"
title: "Adding ACF Fields to the WPGraphQL Schema"
---

On this page, you will find documentation about how to add ACF Fields to the WPGraphQL Schema.

In order to follow along with the documentation below, you should have the latest versions of each of the following plugins [installed and activated](./installation-and-activation.md):

-   WPGraphQL for ACF
-   WPGraphQL
-   ACF (FREE or PRO)

## Creating an ACF Field Group

In order to add ACF Fields to the GraphQL Schema

Advanced Custom Fields provides 3 ways to create Field Groups:

-   [Graphical User Interface (GUI)](https://www.advancedcustomfields.com/resources/creating-a-field-group/)
-   [PHP](https://www.advancedcustomfields.com/resources/register-fields-via-php/)
-   [JSON](https://www.advancedcustomfields.com/resources/local-json/)

Each of these methods is supported when using WPGraphQL for ACF to expose your ACF Field Groups to the GraphQL Schema.

### ACF Field Group Settings

With WPGraphQL for ACF active, each ACF Field Group will have a “GraphQL” settings tab:

![](images/CleanShot-2023-11-22-at-14.34.54.png)

Within this tab are the following settings that impact how the field group is added (or not added) to the GraphQL Schema:

-   **Show in GraphQL (show\_in\_graphql):** Checked (`true`), if the field group should be shown in the GraphQL Schema. Unchecked (`false`) if the field group should not be shown in the GraphQL Schema.
    -   **NOTE**: Field Groups that are “inactive” but set to Show in GraphQL will be exposed to the Schema. i.e. inactive field groups that are then used to “[clone](./field-types/clone-field.md)” onto other field groups.
-   **GraphQL Type Name** (**graphql\_type\_name):** The Type name that should be used in the GraphQL Schema to represent the field group. This must be unique across the entire GraphQL Schema. The Type cannot be already be present in the GraphQL Schema. It’s recommended to use Pascal casing to name the type (i.e. MyFieldGroupTypeName). This will also serve as the field name (with a lowercase first letter) to access the fields from.
-   **GraphQL Capability (graphql\_capability):** The capability a user needs to read the field group, such as `edit_posts`. Users without it get `null` for the field group, so none of its fields resolve. Leave empty to let anyone read it. See [Restricting who can read ACF data](#restricting-who-can-read-acf-data).
-   **Manually Set GraphQL Types for Field Group (map\_graphql\_types\_from\_location\_rules**): Checked (true) to use the “graphql\_types” value to add the field group to the Schema. Unchecked (false) to let WPGraphQL for ACF map the Field Group to the Schema based on the Location Rules (read more about how [location rules](#location-rules) work below)
-   **GraphQL Types (graphql\_types):** Array of Type Names the ACF Field Group should be accessible from in the Schema.
    -   The type(s) should correspond with the location rules for the field group. For example, if the ACF Field Group is shown on pages in the admin, the field group should be associated with the “Page” type in GraphQL.

## ACF Field Settings

When an ACF Field Group is set to “show\_in\_graphql” each field within the field group will also be shown in the GraphQL Schema, unless the field is not a supported post type, or if the field’s “show\_in\_graphql” setting is set to “false”.

Each ACF Field has its own “GraphQL” settings tab.

![](images/CleanShot-2023-11-22-at-15.10.23-1024x449.png)

Each [field type](./field-types/index.md) might have different settings, but the most common GraphQL Settings for each field type are:

-   **Show in GraphQL (show\_in\_graphql)**: Whether the field should show in the GraphQL Schema. If the Field Group is not shown in the GraphQL Schema, the individual fields will also not be. If the Field Group is shown in the GraphQL Schema, fields are default to “true” and will be shown in the schema unless disabled.
-   **GraphQL Description (graphql\_description):** This is how the field is documented in the Schema and will show in tools such as the GraphiQL IDE.
-   **GraphQL Field Name (graphql\_field\_name):** The name of the field shown in the Schema. The field _must_ start with a letter. Recommended to use “snakeCase”. The Field Name must be unique across the field group. (i.e. you cannot have multiple fields in the same field group with the same “graphql\_field\_name”).
-   **GraphQL Capability (graphql\_capability):** The capability a user needs to read the field’s value, such as `manage_options`. Users without it get `null` for the field. Leave empty to let anyone read it. See [Restricting who can read ACF data](#restricting-who-can-read-acf-data).
-   **GraphQL Non-Null** **(graphql\_non\_null):** Whether the field should be Non-Null in the Schema. If checked and the field doesn’t have a value, errors will be thrown when the field is queried for. Also changing this value in the future can cause breaking changes, so use with caution.

## Restricting who can read ACF data

By default, every field group and field in the GraphQL Schema can be read by anyone who can query the node it belongs to, including unauthenticated requests. The `graphql_capability` setting restricts reading a field group, a field or an [Options Page](./acf-options-pages.md) to users with a [capability](https://wordpress.org/documentation/article/roles-and-capabilities/).

-   **Unset, `null` or an empty string means public.** This is the default, so existing field groups, fields and Options Pages are unaffected.
-   **It is checked when the data is resolved.** The schema is the same for every user: a restricted field group or field is still in the schema, and resolves `null` for users without the capability.
-   **It only controls GraphQL.** It doesn't change who can edit the data in the WordPress admin, and isn't related to the `capability` setting of ACF Options Pages, which controls who can see and edit the page in the admin.

### Setting a capability

In the ACF admin, the “GraphQL” tab of a field group, a field, and an Options Page each have a “GraphQL Capability” setting.

In PHP, set `graphql_capability` on the field group or field config:

```php
acf_add_local_field_group( [
	'key'                => 'group_site_settings',
	'title'              => 'Site Settings',
	'show_in_graphql'    => true,
	'graphql_field_name' => 'siteSettings',
	'fields'             => [
		[
			'key'  => 'field_tagline',
			'name' => 'tagline',
			'type' => 'text',
		],
		[
			'key'                => 'field_api_secret',
			'name'               => 'api_secret',
			'type'               => 'text',
			// Only users who can manage options can read this field.
			'graphql_capability' => 'manage_options',
		],
	],
	'location'           => [
		[
			[
				'param'    => 'post_type',
				'operator' => '==',
				'value'    => 'page',
			],
		],
	],
] );
```

In [local JSON](https://www.advancedcustomfields.com/resources/local-json/), the setting is saved with the rest of the field group’s settings, and is kept when the field group is exported and imported:

```json
{
	"key": "group_internal_notes",
	"title": "Internal Notes",
	"show_in_graphql": 1,
	"graphql_field_name": "internalNotes",
	"graphql_capability": "edit_posts",
	"fields": [
		{
			"key": "field_note",
			"name": "note",
			"type": "text",
			"graphql_capability": "manage_options"
		}
	]
}
```

### What users without the capability get

A user without the capability gets `null`. With the field above, an unauthenticated request gets:

```json
{
	"data": {
		"page": {
			"siteSettings": {
				"tagline": "Hello World",
				"apiSecret": null
			}
		}
	}
}
```

When [`GRAPHQL_DEBUG`](./debugging.md) is enabled, the response also has a debug message with the type `RESTRICTED_FIELD` naming the field and the capability it requires:

```json
{
	"type": "RESTRICTED_FIELD",
	"message": "The \"SiteSettings.apiSecret\" field requires the \"manage_options\" capability and resolved to null.",
	"field": "SiteSettings.apiSecret",
	"required_capability": "manage_options"
}
```

A user with the capability gets the value, and no debug message.

A value that isn’t a string (such as an array) is a misconfiguration, and is treated as a capability no one has: the field group or field resolves `null` for every user.

### How field group and field capabilities combine

Each level is checked on its own, and a user needs every capability that applies:

-   A field group’s capability applies to every field in it. A user without it gets `null` for the whole field group, whatever its fields are set to.
-   A field’s capability is checked as well as its field group’s. A field can be more restrictive than its field group, but never less: a field without a capability in a restricted field group is still only readable with the field group’s capability.
-   The fields of a field group that is [cloned](./field-types/clone-field.md) into another field group keep the capability of the field group they were cloned from.
-   On an Options Page with a capability, the field groups on the page are only readable with the page’s capability, because the page itself resolves `null` without it.

For example, with a field group set to `edit_posts` and one of its fields set to `manage_options`, an unauthenticated user gets `null` for the field group, an author gets the field group with `null` for that field, and an administrator gets everything.

### Non-Null fields

A field that is both Non-Null (`graphql_non_null`) and restricted can’t resolve `null` for users without the capability: GraphQL reports an error for the field, and its parent (the field group, or the field it's nested in) resolves `null` instead, along with every other field in it. With `GRAPHQL_DEBUG` enabled, a debug message warns about fields with this combination. To return `null` for only the restricted field, turn off its “GraphQL Non-Null” setting.

### Filters

Each level has a filter that runs after the capability check, to restrict (or allow) reading based on anything else. Return `false` to resolve `null`, or `true` to resolve the data even when the capability check failed.

-   `wpgraphql/acf/field_group_should_resolve` filters `( bool $should_resolve, mixed $node, array $acf_field_group, AppContext $context, ResolveInfo $info )`. `$node` is the node the field group belongs to, such as a `\WPGraphQL\Model\Post`.
-   `wpgraphql/acf/field_should_resolve` filters `( bool $should_resolve, mixed $root, array $acf_field, AppContext $context, ResolveInfo $info )`. A field’s filter runs only once its field group has resolved, so it can’t make a field resolve in a field group that didn’t.
-   `wpgraphql/acf/options_page_should_resolve` filters `( bool $should_resolve, array $options_page )`.

For example, to hide a field group on posts in a members-only category from visitors who aren't logged in:

```php
add_filter( 'wpgraphql/acf/field_group_should_resolve', function ( $should_resolve, $node, $acf_field_group ) {
	if (
		'group_member_details' === ( $acf_field_group['key'] ?? '' ) &&
		! is_user_logged_in() &&
		$node instanceof \WPGraphQL\Model\Post &&
		has_term( 'members-only', 'category', $node->databaseId )
	) {
		return false;
	}

	return $should_resolve;
}, 10, 3 );
```

## ACF Field Group Location Rules

WPGraphQL for ACF attempts to automatically map the Field Group to the GraphQL Schema based on the Location Rules.

For example, if a field group had the location rule of “Post Type is equal to page”, then the field group would be mapped to the GraphQL Schema on the “Page” type.

Some Location Rules are easily mapped to the GraphQL Schema, but some are not. In the cases where the location rules are not easily mapped to the Schema, you might need to manually configure which “graphql\_types” the Field Group should be exposed to in the Schema.

Take the following Location Rules as an example:

-   “Post Author is equal to `${Name of Author}`”

In the WordPress admin, this rule will conditionally hide/show the ACF Field Group if the author of a post is not a specific author.  
  
However in the GraphQL Schema, this type of context about a particular object (post in this case) isn’t known at Schema generation time, so this rule cannot automatically map to the GraphQL Schema.

The GraphQL Schema needs to represent the ACF Field Group in association with a GraphQL type (or multiple Types). It isn’t known at Schema generation time what post type(s) an Author might be an author of. Is it just posts? Is it posts and pages? Just pages? Is it just a custom post type? There’s not enough information to automatically map to the Schema, so this type of rule might require manual mapping of “graphql\_types”.

Or consider another rule such as:

-   “Taxonomy Term Slug is equal to `${term-slug}`”  
      
    Again, this rule requires context that is available in the WordPress admin and the field group can show/hide, but it’s not available when the GraphQL Schema is generated. What type of term? Category? Tag? This would be another rule that would require manual assigning of “graphql\_types” the ACF Field Group should show on.

If you’ve created an ACF Field Group and have set it to “Show in GraphQL” but you’re not seeing the field type in the Schema, the location rules and manual mapping of “graphql\_types” is a common place to troubleshoot.

If you have ideas on how to better handle automatic mapping of ACF Field Groups to the GraphQL Schema, [let us know](https://github.com/wp-graphql/wp-graphql/issues)!

### Rules that use "All" or "is not equal to"

A rule set to "All", such as "Post Type is equal to All", "Taxonomy is equal to All" or "Block is equal to All", adds the field group to every type of that kind that is shown in GraphQL: every content type, every taxonomy, or every ACF block type.

A rule that excludes one value, such as "Post Type is not equal to Page", adds the field group to every other type of that kind that is shown in GraphQL, and not to the excluded one. Here, that's every content type except Page. A rule that excludes "All", such as "Block is not equal to All", matches nothing, the same as in ACF, so it doesn't add the field group to any type.

To show a field group on a type its location rules exclude, map its types manually, as described below. Manually mapped types replace the types the location rules would map to.

### Manually Mapping ACF Field Groups to the Schema

When ACF Location Rules cannot be automatically mapped to the Schema, based on complex rules as described above, you can manually map an ACF Field Group to the GraphQL Schema.

In the GUI, you can use the following UI to select the GraphQL Type(s) that the ACF Field Group should be accessible from.

Each Type mapped here will be given a field that corresponds with the ACF Field Group, allowing access to the fields of the Field Group.

If using PHP or JSON, the mapping can be done by defining the types in the `graphql_types` field.
