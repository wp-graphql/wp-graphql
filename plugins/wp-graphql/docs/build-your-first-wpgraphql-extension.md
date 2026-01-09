---
uri: "/docs/build-your-first-wpgraphql-extension/"
title: "Build your First WPGraphQL Extension"
---

## Video Tutorial

The following video walks through how to build a WPGraphQL Extension plugin. After watching the video, viewers should be able to create a WordPress plugin that extends the WPGraphQL Schema with new fields.

If you prefer learning these concepts by reading, scroll down to the written tutorial below.

https://youtu.be/0plIW5hf6lM

## Prerequisites

Before starting this tutorial, you should have:

- WordPress (recommended: 6.4+)
- PHP (recommended: 8.0+)
- WPGraphQL (latest version)
- A local WordPress development environment
- Basic understanding of WordPress plugin development

## What We'll Build

In this tutorial, we'll learn how to:

1. Create a basic WPGraphQL extension plugin
2. Register custom types and fields to the GraphQL Schema
3. Work with WordPress data in your GraphQL API
4. Follow GraphQL naming conventions and best practices

We'll start with basic concepts using an abstract example, then move on to practical real-world implementations.

## Getting Started

### Setting Up Your Plugin

1. Navigate to your WordPress site's `wp-content/plugins` directory
2. Create a new folder named `my-first-wpgraphql-extension`
3. Create a PHP file with the same name: `my-first-wpgraphql-extension.php`

Add this code to your PHP file:

```php
<?php
/
Plugin Name: My First WPGraphQL Extension
Description: A tutorial plugin demonstrating WPGraphQL extension development
Version: 1.0.0
Author: Your Name
/
// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
  die( 'Unauthorized access!' );
}

```

After adding this code, activate the plugin in your WordPress admin panel. You should see it listed in the plugins page.

![Screenshot showing "My First WPGraphQL Extension" in the WordPress Plugins admin page](./images/extension-wordpress-admin-screen.png)

## Understanding GraphQL Naming Conventions

Before we start extending the schema, it's important to understand GraphQL naming conventions:

- Type use PascalCase: Post, Page, CustomType, BookType
- Fields use camelCase: title, date, bookTitle, customField
- Enum values use ALL_CAPS: POST_STATUS
- Descriptions should be clear, complete sentences

These conventions help maintain consistency and clarity in your GraphQL API.

## Your First Schema Extension

Let's start with a basic example of extending the schema. We'll create:

We'll create:

- A custom type
- A field that returns that type

```php
<?php
add_action( 'graphql_register_types', function() {
  // Register a custom type
  register_graphql_object_type( 'CustomType', [
    'description' => ( 'An example custom type demonstrating schema extension', 'my-graphql-extension' ),
    'fields' => [
      'message' => [
        'type' => 'String',
        'description' => ( 'A simple message field', 'my-graphql-extension' ),
      ],
      'number' => [
        'type' => 'Int',
        'description' => ( 'A simple number field', 'my-graphql-extension' ),
      ],
    ],
  ]);
  
  // Register a field that returns our custom type
  register_graphql_field( 'RootQuery', 'example', [
    'type' => 'CustomType',
    'description' => ( 'An example field returning our custom type', 'my-graphql-extension' ),
    'resolve' => function() {
      return [
        'message' => 'Hello from your first WPGraphQL extension!',
        'number' => 42,
      ];
    }
  ]);
});
```

## Testing Your First Extension

After adding this code, you can test your extension using GraphiQL in the WordPress admin panel. Navigate to GraphQL → GraphiQL IDE and try this query:

```graphql
{
  example {
    message
    number
  }
}
```

You should see a response like:

```json
{
  "data": {
    "example": {
      "message": "Hello from your first WPGraphQL extension!",
      "number": 42
    }
  }
}
```

## Real-World Example: Working with WordPress Options

Now let's look at a practical example. While WPGraphQL automatically exposes some WordPress settings when they're registered with `show_in_graphql => tru`e`, you might need to manually expose options or add custom resolution logic.

Here's an example of exposing a custom option:

```php
php
add_action( 'graphql_register_types', function() {
  register_graphql_field( 'RootQuery', 'siteCustomSetting', [
    'type' => 'String',
    'description' => ( 'A custom site setting', 'my-graphql-extension' ),
    'resolve' => function() {
      // Basic capability check
      if ( ! current_user_can( 'read' ) ) {
        throw new \GraphQL\Error\UserError( 'You do not have permission to access this setting' );
      }
      return get_option( 'my_custom_setting', 'default value' );
    }
  ]);
});
```

After adding this code, you can test your extension using GraphiQL in the WordPress admin panel. Navigate to GraphQL → GraphiQL IDE and try this query:

```graphql
{
  siteCustomSetting
}
```

You should see a response like:

```json
{
  "data": {
    "siteCustomSetting": "default value"
  }
}
```

## Advanced Example: Custom Settings Type

Let's create a more complex example with multiple fields and proper error handling:

```php
add_action( 'graphql_register_types', function() {
  // Register a custom settings type
  register_graphql_object_type( 'CustomSiteSettings', [
    'description' => ( 'Custom site settings', 'my-graphql-extension' ),
    'fields' => [
      'mainColor' => [
        'type' => 'String',
        'description' => ( 'The main color theme setting', 'my-graphql-extension' ),
      ],
      'featuredCategories' => [
        'type' => ['list_of' => 'String'],
        'description' => ( 'List of featured category slugs', 'my-graphql-extension' ),
      ],
      'lastUpdated' => [
        'type' => 'String',
        'description' => ( 'When the settings were last updated', 'my-graphql-extension' ),
      ],
    ],
  ]);

  // Register a field that returns our settings type
  register_graphql_field( 'RootQuery', 'customSiteSettings', [
    'type' => 'CustomSiteSettings',
    'description' => ( 'Custom site settings configuration', 'my-graphql-extension' ),
    'resolve' => function() {
      // Check permissions
      if ( ! current_user_can( 'manage_options' ) ) {
        throw new \GraphQL\Error\UserError(( 'You do not have permission to access site settings', 'my-graphql-extension' ));
      }
      try {
        return [
          'mainColor' => get_option( 'site_main_color', '#ffffff' ),
          'featuredCategories' => get_option( 'featured_categories', [] ),
          'lastUpdated' => get_option( 'settings_updated_at' ),
        ];
      } catch ( \Exception $e ) {
        throw new \GraphQL\Error\UserError(( 'Error fetching site settings', 'my-graphql-extension' ));
      }
    }
  ]);

});
```

You can query these settings with:

```graphql
{
  siteSettings {
    mainColor
    featuredCategories
    lastUpdated
  }
}
```

## Best Practices

### Naming and Documentation

1. Type Names: 
  - Use PascalCase: `ProductType`, `OrderStatus`
  - Be specific, but concise
  - Avoid WordPress-specific terminology when possible (e.g. use Product instead of WooCommerceProduct)

2. Field Names: 
  - Use camelCase: `productPrice`, `orderTotal`
  - Start with a verb for actions: `createPost`, `updateUser`
  - Use clear, descriptive names
  - Boolean fields should start with is, has, should, can: (e.g. `isPublished`, `hasChildren`, `shouldUpdate`, `canUpdate`)

3. Descriptions:
  - Write complete sentences
  - Explain the purpose, not just what it is
  - Include important details about permissions or side effects (e.g. `'description' => __( 'The product\'s base price, excluding taxes and discounts.', 'my-textdomain' )`)

## Security Considerations

### Access Control

```php
 register_graphql_field( 'RootQuery', 'sensitiveData', [
    'type' => 'String',
    'resolve' => function() {
        // Always check capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new \GraphQL\Error\UserError( 'Unauthorized access' );
        }
        
        return get_option( 'sensitive_data' );
    }
]);
```

### Data Sanitization

```php
   'resolve' => function( $source, array $args ) {
       // Always sanitize input
       $safe_input = sanitize_text_field( $args['input'] );
       return wp_kses_post( $safe_input );
   }
```

## Error Handling

### Use Try-Catch Blocks

```php
   'resolve' => function() {
       try {
           // Your resolution logic
           return get_option( 'some_option' );
       } catch ( \Exception $e ) {
           throw new \GraphQL\Error\UserError( 
               __( 'Failed to retrieve data', 'my-textdomain' )
           );
       }
   }
```

### Meaningful Error Messages

- Be specific but don't expose sensitive information
- Translate all error messages
- Include actionable information when possible

## Troubleshooting Common Issues

If you run into issues, common debugging tips can be found in the [Debugging guide](https://www.wpgraphql.com/docs/debugging). 

## Next Steps

### Explore More Features

- [WPGraphQL Documentation](https://www.wpgraphql.com/docs)
- [GraphQL Specification](https://spec.graphql.org/)


### Join the Community

- [WPGraphQL Discord](https://wpgraphql.com/discord)
- [GitHub Discussions](https://github.com/wp-graphql/wp-graphql/discussions)

Remember to always test your extensions thoroughly and keep security in mind when exposing data through GraphQL.
