---
uri: "/field-types/wysiwyg/"
title: "WYSIWYG"
---

`wysiwyg`

The WYSIWYG (What You See is What You Get) field type is native to ACF (free) and provides users with a TinyMCE Editor input field.

## Resolve Type

Fields of the "wysiwyg" field type resolve to "String" int he GraphQL Schema

## Field Settings

| Setting | Description | Impact on WPGraphQL |
| --- | --- | --- |
| `acfe_field_group_condition` | Enable Global Conditional Logic for a specific field, which can then be used in an another Field Group as condition, both as Field Group Condition and Field Condition. | This is a presentational field in the WordPress admin and has no impact on the GraphQL Schema or GraphQL resolvers. |
| `graphql_non_null` | Whether the field should be Non-Null in the GraphQL Schema. Use with caution. Only check this if you can guarantee there will be data stored for this field on all objects that have this field. i.e. the field should be required and should have data entered for all previous entries with this field. Unchecking this, if already checked, is considered a breaking change to the GraphQL Schema. | Checking this field will set the field as a NonNull field in the WPGraphQL Schema. Changing a field from nullable to NonNull is a non-breaking change to the Schema. BUT, changing a field from NonNull to nullable IS a breaking change to the schema, so be careful with this option. Also, because of the dynamic nature of ACF, it’s difficult to guarantee a value will exist for the field. For example, you might already have 100 posts published with no value for the field, so setting the field to “Non-Null” in GraphQL will lead to errors for those posts that have no value to return. It’s recommended to only use this setting if the field has been marked as “required” the entire time the field has been available. |
| `show_in_graphql` | Whether the field should be queryable via GraphQL. NOTE: Changing this to false for existing field can cause a breaking change to the GraphQL Schema. Proceed with caution. | Checking this will expose the field to the GraphQL Schema. NOTE: If a field is added to the GraphQL Schema, then later removed from the Schema, this is considered a breaking change as client applications that were querying for the field would be breaking once it’s been removed from the Schema. |
| `graphql_description` | The description of the field, shown in the GraphQL Schema. Should not include any special characters. | The description of the field that is returned when using Schema Introspection queries, used by tools such as the GraphiQL IDE. |
| `graphql_field_name` | The name of the field in the GraphQL Schema. Should only contain numbers and letters. Must start with a letter. Recommended format is "snakeCase". | The name of the field in the GraphQL Schema. The name must be unique to the Field Group (i.e. there cannot be 2 fields in one ACF Field Group with the same “GraphQL Field Name”, including when using [Clone Fields](https://acf.wpgraphql.com/field-types/clone-field/)). |
| `name` | Single word, no spaces. Underscores and dashes allowed | This is the name that is used to store field data in meta tables. The name will not affect the GraphQL Schema, but if the name is changed after data is already saved, it might impact resolution of the previously stored data. Changing the field name _could_ negatively impact the GraphQL experience. |
| `label` | This is the name which will appear on the EDIT page. | This field is presentational for the WordPress admin and will not impact the GraphQL Schema. |
| `default_value` | Appears when creating a new post | This value should be returned as the GraphQL field value if no value has been set for the field. |
| `required` | Whether the field should be required when inputting new data | The “required” setting on an ACF Field does not directly impact the WPGraphQL Schema. While it might seem like setting an ACF Field to “required” should enforce the field to be a “Non Null” field in the GraphQL Schema, we believe this would be a mistake. Setting a field in the GraphQL Schema as “NonNull” will return errors if no data is present to be returned. Since the “required” setting can be toggled “on” on an ACF Field long after content already exists with no data for the field, this would cause errors to be returned for older content, and we believe this to be unexpected behavior. Instead of tying “GraphQL Non Null” to the ACF “Required” setting, we’ve provided a “GraphQL: NonNull” setting where you can explicitly opt-in to a field being “Non Null” in the Schema. |
| `instructions` | Instructions for authors. Shown when submitting data | This field is used to tell people in the WordPress admin how to use the field. If a “GraphQL Description” is not provided for a field, the “instructions” will be used as a fallback in GraphQL Introspection queries, used in tools such as the GraphiQL IDE. |
| `conditional_logic` | Allow the field to be displayed conditionally in the Admin based on dynamic conditions. | Conditional Logic should not impact the GraphQL Schema. Fields that are conditionally available in the admin should _always_ be available in the Schema. The data that is resolved for a field might be impacted by conditional logic. |


## Field Configuration

An example of registering a field group with a `wysiwyg` field in PHP (the same can be configured in the ACF admin UI, or via ACF JSON):

```php
<?php
add_action( 'acf/include_fields', function() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	acf_add_local_field_group( [
		'key'                              => 'my_field_group_wysiwyg',
		'title'                            => 'My Field Group with wysiwyg',
		'show_in_graphql'                  => 1,
		'graphql_field_name'               => 'myFieldGroupWithWysiwyg',
		'map_graphql_types_from_location_rules' => 0,
		'graphql_types'                    => [ 'Page' ],
		'fields'                           => [
			[
				'key'                => 'my_field_wysiwyg',
				'label'              => 'My Field',
				'name'               => 'my_field',
				'type'               => 'wysiwyg',
				'show_in_graphql'    => 1,
				'graphql_field_name' => 'myFieldWithWysiwyg',
			],
		],
		'location'                         => [
			[
				[
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'page',
				],
			],
		],
	] );
} );
```

## Querying the WYSIWYG field

```graphql
query WysiwygField($uri: String! = "kitchen-sink") {
  nodeByUri(uri: $uri) {
    id
    uri
    ...WithAcfFreeKitchenSink
  }
}

fragment WithAcfFreeKitchenSink on WithAcfAcfFreeKitchenSink {
  acfFreeKitchenSink {
    wysiwyg
  }
}
```

**Example response:**

```json
{
  "data": {
    "nodeByUri": {
      "id": "cG9zdDozNTI=",
      "uri": "/kitchen-sink/",
      "acfFreeKitchenSink": {
        "wysiwyg": "<p>Here&#8217;s where we can add some content using the TinyMCE editor.</p>\n"
      }
    }
  }
}
```
