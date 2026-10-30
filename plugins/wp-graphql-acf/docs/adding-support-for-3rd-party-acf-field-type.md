---
uri: "/adding-support-for-3rd-party-acf-field-type/"
title: "Adding Support for 3rd Party ACF Field Type"
---

This guide provides developers with information on how to add support for custom Advanced Custom Fields (ACF) field types in the WPGraphQL schema using the WPGraphQL for ACF plugin.

## Initial Setup

1.  **Plugin Installation**: Ensure WPGraphQL and WPGraphQL for ACF are active in your WordPress environment. For detailed installation and activation instructions, visit the [Installation and Activation guide](./installation-and-activation.md).

## Registering Custom ACF Field Types

2.  **Basic Registration**: Use the `register_graphql_acf_field_type` function to map an ACF Field Type to WPGraphQL. For instance, registering a field as a “String” can be done with:

```php
   add_action( 'wpgraphql/acf/registry_init', function() {
       register_graphql_acf_field_type( 'your_custom_field_type' );
   });
```

3.  **Custom GraphQL Types**: To customize the GraphQL type for a custom ACF field, pass a `$config` array as the second argument. For example, to set the type to ‘Int’:

```php
   register_graphql_acf_field_type(
       'your_custom_field_type',
       [
           'graphql_type' => static function () {
               return 'Int';
           }
       ]
   );
```

4.  **Custom Resolvers**: Implement custom resolvers by adding a ‘resolve’ function in the `$config` array. This is useful for overriding default resolver behavior. Here’s an example:

```php
   register_graphql_acf_field_type(
       'your_custom_field_type',
       [
           'graphql_type' => [ 'list_of' => 'String' ],
           'resolve'      => static function ( $root, $args, $context, $info, $field_type, $field_config ) {
               // Custom resolver logic here
           },
       ]
   );
```

## Verifying and Debugging

5.  **Verification**: After registering a custom ACF Field Type, verify its functionality by creating a new field group or adding it to an existing group, then use the GraphiQL IDE to ensure the field appears in the schema and returns data correctly.
6.  **Debugging**: If issues arise, consult the debugging guides at [WPGraphQL for ACF Debugging](./debugging.md) and [WPGraphQL Debugging](https://www.wpgraphql.com/docs/debugging).

## Best Practices and Security

7.  **Best Practices**:

-   Consider the “graph” nature of the API. If data stored is an ID for another object, prefer returning the Object Type instead of an Integer.
-   Use WPGraphQL Model and Loader functions to secure sensitive data and ensure proper permissions are enforced. Example: For an ACF Field storing an unpublished post’s ID, ensure checks are in place to prevent unauthorized access to the unpublished content.

## Advanced Usage

### Resolving the Formatted Value

By default, a field resolves the value ACF stored for it, not the value `get_field()` returns after ACF formats it. For many field types those are the same. Some field types store one shape and format it into another, for example a field that stores JSON and formats it into an array. A field like that resolves the stored string unless its field type asks for the formatted value.

`format_value` is a boolean. Set it to `true` to resolve the formatted value:

```php
register_graphql_acf_field_type(
    'your_custom_field_type',
    [
        'graphql_type' => 'YourCustomFieldValue',
        'format_value' => true,
    ]
);
```

The formatting is the field type's own: the same formatting ACF applies when `get_field()` formats the value (the field type's `format_value()` method, or the `acf/format_value` filter). `format_value` only decides whether it's applied. It doesn't take a callback, and any value other than `true` or `false` (like a callable, `1` or `'true'`) is ignored, leaving the default. To change the value yourself, use a `resolve` callback (see [Custom Resolvers](#registering-custom-acf-field-types)) or the `wpgraphql/acf/field_value` filter.

The field then resolves the formatted value wherever it's used: at the top level of a field group, in a group, in repeater and flexible content rows, and in ACF blocks. Fields in repeater and flexible content rows already resolved formatted values, because ACF formats the rows.

The built-in select, WYSIWYG, oEmbed, repeater, flexible content and clone field types resolve their formatted value without setting `format_value`. Setting it to `false`, for example with the `wpgraphql/acf/field_type_config` filter, makes a top-level field of that type resolve its stored value instead. Fields in repeater and flexible content rows are formatted along with their row either way.

### Excluding Admin Fields

The `register_graphql_acf_field_type` function allows for the exclusion of certain admin fields from appearing under the “GraphQL” tab in the field settings. This can be achieved by using the `exclude_admin_fields` key.

#### Example:

To exclude specific settings fields from the GraphQL tab of the Field Type settings in the ACF user interface, you can pass them as an array:

```php
register_graphql_acf_field_type(
    'your_custom_field_type',
    [
        'exclude_admin_fields' => [ 'graphql_non_null' ],
        // Other configuration options...
    ]
);
```

### Registering New Admin Fields

You can also register new admin fields to appear under the “GraphQL” tab. This is done using the `admin_fields` key.

#### Example:

To add new admin fields, define them in an array and pass it to the function:

```php
register_graphql_acf_field_type(
    'your_custom_field_type',
    [
        'admin_fields' => static function ( $admin_fields, $field, $config, \WPGraphQL\Acf\Admin\Settings $settings ): array {
            // Define new admin fields here...
        },
        // Other configuration options...
    ]
);

```

* * *

These advanced features of `register_graphql_acf_field_type` provide more control and customization over how ACF fields interact with the WPGraphQL schema. By understanding and utilizing these options, developers can better tailor the GraphQL API to meet specific project requirements.

## Conclusion

This guide assists developers in extending the WPGraphQL schema with custom ACF field types, ensuring a secure, efficient, and seamless integration within the WordPress ecosystem.

For examples of the `register_graphql_acf_field_type` API in action, you can browse the code to see how custom field types provided by Advanced Custom Fields Extended are supported by WPGraphQL for ACF: [plugins/wp-graphql-acf/src/ThirdParty/AcfExtended/FieldType](https://github.com/wp-graphql/wp-graphql/tree/main/plugins/wp-graphql-acf/src/ThirdParty/AcfExtended/FieldType)
