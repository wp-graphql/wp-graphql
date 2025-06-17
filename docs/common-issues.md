---
uri: "/docs/common-issues/"
title: "Common Issues"
---

This guide covers frequently encountered issues when working with WPGraphQL and their solutions.

## Authentication Issues

WPGraphQL itself doesn't include built-in authentication features. Authentication is handled through extension plugins. For a complete guide to authentication concepts, see the [Authentication and Authorization Guide](/docs/authentication-and-authorization/).

### Available Authentication Solutions

1. **Cookie-based Authentication**
   - Built into WordPress
   - Best for admin-context applications
   - Used by WPGraphiQL IDE and other WordPress dashboard integrations
   - Requires proper nonce handling
   - Limited to same-origin requests

2. **[WPGraphQL Headless Login](https://github.com/axewp/wp-graphql-headless-login)**
   - Most comprehensive authentication solution
   - Supports multiple authentication methods:
     - Traditional WordPress credentials
     - OAuth2/OpenID Connect
     - JWT tokens
     - Application Passwords
     - Custom providers

3. **[WPGraphQL JWT Authentication](https://github.com/wp-graphql/wp-graphql-jwt-authentication)**
   - Lightweight JWT-based authentication
   - Good for simple authentication needs
   - Works well for cross-origin requests

4. **WordPress Application Passwords**
   - Built into WordPress core (5.6+)
   - Limited WPGraphQL support currently
   - Best for server-to-server authentication

### Common Authentication Errors

#### "Authentication failed" Errors

This typically occurs when:
1. No authentication method is configured
2. The authentication headers are missing or malformed
3. The credentials are invalid

**Solution:** First, ensure you have an authentication plugin installed and configured. Then verify your authentication headers:

```php
// Example using Application Passwords
$headers = [
    'Authorization' => 'Basic ' . base64_encode( 'username:application_password' )
];

// Example using JWT Authentication
$headers = [
    'Authorization' => 'Bearer your.jwt.token'
];
```

#### Cookie Authentication Issues

For admin-context requests (like WPGraphiQL):

1. Verify you're logged into WordPress
2. Check if your session is valid
3. Ensure nonce is being sent correctly

```javascript
// Example of including nonce in requests
const response = await fetch('/graphql', {
  method: 'POST',
  credentials: 'same-origin',
  headers: {
    'X-WP-Nonce': wpApiSettings.nonce
  }
});
```

#### OAuth/Social Login Issues

When using WPGraphQL Headless Login with OAuth providers:

1. **Configuration Issues**
   - Verify OAuth provider credentials
   - Check redirect URI configuration
   - Ensure SSL is properly configured

2. **Integration Issues**
   - Validate frontend OAuth flow
   - Check provider response handling
   - Verify user creation/linking settings

For detailed setup instructions and best practices for each authentication method, refer to:
- [WPGraphQL Headless Login Documentation](https://github.com/AxeWP/wp-graphql-headless-login#readme)
- [Authentication and Authorization Guide](/docs/authentication-and-authorization/)

## Schema and Type Registration

Common issues related to GraphQL schema and type registration in WPGraphQL.

### Custom Post Types Not Showing in Schema

If your custom post type isn't appearing in the GraphQL schema:

1. **Check Show in GraphQL Setting**
   ```php
   register_post_type('book', [
     'show_in_graphql' => true,     // Must be true
     'graphql_single_name' => 'book',    // Required
     'graphql_plural_name' => 'books',    // Highly Recommended
     // ... other settings
   ]);
   ```

2. **Verify Registration Timing**
   ```php
   // Post Types must register on `init` hook or earlier
   add_action('init', function() {
     register_post_type('book', [ /* ... */ ]);
   });
   ```

3. **Common Mistakes**
   - Not setting the post type `show_in_graphql` to `true`
   - Missing required `graphql_single_name/graphql_plural_name
   - Registering too late in the WordPress lifecycle
   - Typos in registration arguments
   - Duplicate post type names
   - Duplicate `graphql_single_name`/`graphql_plural_name` (i.e. 2 post types with the same values)

### Missing Fields in Schema

If expected fields are not appearing in your schema:

1. **Check Field Registration**
   ```php
   register_graphql_field('Post', 'myCustomField', [
     'type' => 'String',
     'description' => 'My custom field description',
     'resolve' => function($post) {
       // note the $post is a WPGraphQL Post Model not a WordPress WP_Post object
       // so you need to use the $post->databaseId property in resolvers instead of the $post->ID property
       return get_post_meta($post->databaseId, 'my_custom_field', true);
     }
   ]);
   ```

2. **Common Issues**
   - Field registered too late (use `graphql_register_types` hook instead of hooks like `init`)
   - Invalid type specified
   - Missing resolve function
   - Incorrect field permissions

### Type Registration Errors

Common type registration problems and solutions:

1. **Invalid Type Names**
   ```php
   // ❌ Invalid: lowercase or special characters, or otherwise non-existing Type in the GraphQL Schema
   register_graphql_object_type('my-type', [ /* ... */ ]);

   // ✅ Correct: PascalCase naming
   register_graphql_object_type('MyType', [ /* ... */ ]);
   ```

2. **Duplicate Type Registration**
   ```php
   // Prevent duplicate registration
   if (!register_graphql_object_type('MyType')) {
     register_graphql_object_type('MyType', [
       'fields' => [
         'myField' => ['type' => 'String']
       ]
     ]);
   }
   ```

3. **Missing Required Fields**
   ```php
   register_graphql_object_type('MyType', [
     'description' => 'Required description field',
     'fields' => [
       // At least one field is required
       'id' => ['type' => 'ID']
     ]
   ]);
   ```

### Field Resolution Failures

When fields aren't resolving as expected:

1. **Debug Resolution Issues**
   ```php
   register_graphql_field('Post', 'debugField', [
     'type' => 'String',
     'resolve' => function($post) {
        // note the $post is a WPGraphQL Post Model not a WordPress WP_Post object
        // so you need to use the $post->databaseId property in resolvers instead of the $post->ID property
        // use graphql_debug() to log debug information in the "extensions" portion of the query response - requires GRAPHQL_DEBUG to be enabled
       graphql_debug('Resolving field for post: ' . $post->databaseId);
       $value = get_post_meta( $post->databaseId, 'my_field', true );
       graphql_debug('Retrieved value: ' . [
        '$value' => $value,
       ]);
       return $value;
     }
   ]);
   ```

2. **Common Resolution Problems**

   #### Incorrect Data Source Access
   ```php
   // ❌ Incorrect: Using WP_Post properties directly
   register_graphql_field( 'Post', 'myField', [
     'type' => 'String',
     'resolve' => function( $post ) {
       return $post->post_title; // Wrong! $post is not a WP_Post object
     }
   ]);

   // ✅ Correct: Using WPGraphQL Post Model properties
   register_graphql_field( 'Post', 'myField', [
     'type' => 'String',
     'resolve' => function( $post ) {
       return $post->title; // Correct! Using the Model property
     }
   ]);
   ```

   #### Missing Null Checks
   ```php
   // ❌ Incorrect: No null checks
   register_graphql_field( 'Post', 'myMetaField', [
     'type' => 'String',
     'resolve' => function( $post ) {
       $meta = get_post_meta( $post->databaseId, 'my_meta', true );
       return $meta; // Could return null/false and cause type coercion errors
     }
   ]);

   // ✅ Correct: With null checks
   register_graphql_field( 'Post', 'myMetaField', [
     'type' => 'String',
     'resolve' => function( $post ) {
       $meta = get_post_meta( $post->databaseId, 'my_meta', true );
       return ! empty( $meta ) ? (string) $meta : null;
     }
   ]);
   ```

   #### Permission/Capability Issues
   ```php
   // ❌ Incorrect: No capability check
   register_graphql_field( 'Post', 'secretField', [
     'type' => 'String',
     'resolve' => function( $post ) {
       return get_post_meta( $post->databaseId, 'secret_data', true );
     }
   ]);

   // ✅ Correct: With capability check
   register_graphql_field('Post', 'secretField', [
     'type' => 'String',
     'resolve' => function($post) {
       // Check user capabilities before resolving sensitive data
       if ( ! current_user_can( 'edit_post', $post->databaseId ) ) {
         throw new \GraphQL\Error\UserError( 'You do not have permission to access this field' );
       }
       return get_post_meta( $post->databaseId, 'secret_data', true );
     }
   ]);
   ```

   #### Type Mismatches
   ```php
   // ❌ Incorrect: Type mismatch
   register_graphql_field( 'Post', 'viewCount', [
     'type' => 'Int',
     'resolve' => function( $post ) {
       return get_post_meta( $post->databaseId, 'view_count', true ); // Returns string from meta
     }
   ]);

   // ✅ Correct: Proper type coercion
   register_graphql_field( 'Post', 'viewCount', [
     'type' => 'Int',
     'resolve' => function( $post ) {
       $count = get_post_meta( $post->databaseId, 'view_count', true );
       return ! empty( $count ) ? absint( $count ) : null;
     }
   ]);
   ```

   #### Common Debugging Tips
   - Enable GRAPHQL_DEBUG in wp-config.php:
     ```php
     define( 'GRAPHQL_DEBUG', true );
     ```
   - Use graphql_debug() in resolvers:
     ```php
     graphql_debug([
       'message' => 'Debugging resolver',
       'data' => $some_data
     ]);
     ```
   - Check the "extensions" field in responses for debug info
   - Use try/catch blocks to handle errors gracefully:
     ```php
     try {
       // Your resolver logic
     } catch (\Exception $e) {
       graphql_debug([
         'message' => 'Error in resolver',
         'error' => $e->getMessage()
       ]);
       return null;
     }
     ```

For more information about debugging and error handling, see:
- [Debugging Guide](/docs/debugging/)
- [Security Guide](/docs/security/)

For more detailed information about registering types and fields, see:
- [WPGraphQL Custom Post Types Guide](/docs/custom-post-types/)
- [Default Types and Fields](/docs/default-types-and-fields/)

## Query Performance

Common performance issues and their solutions when working with WPGraphQL.

### N+1 Query Problems

The N+1 query problem occurs when fetching related data results in multiple separate database queries. WPGraphQL implements several optimizations out of the box to prevent N+1 query problems:

- **Post Meta**: WordPress automatically batches post meta queries
- **Term Relationships**: WPGraphQL optimizes term relationship queries
- **Author Data**: Author data is efficiently loaded for posts
- **Comments**: Comment queries are optimized when requesting comment counts or comment data

#### Potential N+1 Scenarios

N+1 issues are most likely to occur when:

1. **Working with Custom Database Tables**
   ```graphql
   # This could trigger separate queries if not properly optimized
   {
     orders {  # WooCommerce example
       nodes {
         id
         # Each of these might query a custom table
         total
         lineItems {
           nodes {
             productId
             quantity
           }
         }
       }
     }
   }
   ```

2. **Custom External API Calls**
   ```graphql
   {
     posts {
       nodes {
         id
         title
         # Each of these could trigger separate API calls
         externalServiceData
         thirdPartyIntegrationField
       }
     }
   }
   ```

#### Solutions for Custom Data Sources

When working with custom database tables or external APIs, consider:

1. **Implement DataLoader Pattern**
   - Use DataLoader for custom database tables
   - Batch external API requests
   - Cache frequently accessed data

2. **Use Connection Resolvers**
   - Implement proper connection resolvers for custom relationships
   - Utilize WPGraphQL's built-in connection resolver patterns

For examples of implementing these patterns with custom data sources, see:
- [Using Data from Custom Database Tables](/docs/using-data-from-custom-database-tables/)
- [Performance Guide](/docs/performance/)

### Connection Pagination Issues

#### Common Problems

1. **Memory Limits with Large Datasets**
   ```graphql
   # ❌ Problematic: Fetching too many nodes at once
   {
     posts(first: 1000) {
       nodes {
         # ...fields
       }
     }
   }

   # ✅ Better: Use pagination with reasonable limits
   {
     posts(first: 10) {
       pageInfo {
         hasNextPage
         endCursor
       }
       nodes {
         # ...fields
       }
     }
   }
   ```

2. **Cursor-based Navigation**
   ```graphql
   # Example of proper cursor-based pagination
   {
     posts(first: 10, after: "cursor_string") {
       pageInfo {
         hasNextPage
         endCursor
       }
       nodes {
         id
         title
       }
     }
   }
   ```

### Query Optimization Tips

1. **Request Only Needed Fields**
   ```graphql
   # ❌ Inefficient: Requesting unnecessary fields
   {
     posts {
       nodes {
         id
         title
         content  # Don't request if not needed
         excerpt  # Don't request if not needed
         author {
           # Triggers additional queries
           posts {
             nodes {
               title
             }
           }
         }
       }
     }
   }

   # ✅ Efficient: Request only required fields
   {
     posts {
       nodes {
         id
         title
       }
     }
   }
   ```

### Memory Management

1. **Configure PHP Memory Limits**
   ```php
   // In wp-config.php
   define( 'WP_MEMORY_LIMIT', '256M' );
   ```

2. **Implement Query Batching**
   ```php
   // Example of batching multiple queries
   // Note: Query batching only works with POST requests, not GET requests
   $batch_queries = [
     [
       'query' => 'query { posts { nodes { id title } } }'
     ],
     [
       'query' => 'query { users { nodes { id name } } }'
     ]
   ];

   // Send as a single POST request
   $response = graphql( $batch_queries );
   ```

   > **Note**: Query batching is only supported when using POST requests. GET requests cannot be batched.

### Performance Monitoring

1. **Enable Query Logging**
   ```php
   // Log slow queries
   add_filter( 'graphql_debug_enabled', '__return_true' );

   // Track query execution time
   add_action( 'graphql_execute', function( $response, $query ) {
     graphql_debug([
       'query' => $query,
       'execution_time' => timer_stop()
     ]);
   }, 10, 2 );
   ```

2. **Use Query Tracing**
   ```php
   // Enable Apollo Tracing
   add_filter( 'graphql_tracing_enabled', '__return_true' );
   ```

For more information about performance optimization, see:
- [Performance Guide](/docs/performance/)
- [Connections Guide](/docs/connections/)

## Common Error Messages

### "Internal server error"

Common causes and solutions:

1. **PHP Memory Limits**
   ```php
   // In wp-config.php
   define( 'WP_MEMORY_LIMIT', '256M' );
   define( 'WP_MAX_MEMORY_LIMIT', '512M' );
   ```

2. **PHP Timeout**
   ```php
   // In php.ini or wp-config.php
   set_time_limit(300); // 5 minutes
   ```

3. **Server Logs**
   - Check PHP error logs
   - Enable WP_DEBUG for more information
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   ```

### "Sorry, you are not allowed to do that"

This error indicates a permissions issue:

1. **Authentication Problems**
   - Verify user is authenticated
   - Check user capabilities
   - Ensure authentication headers are correct
   - Verify the authentication plugin is properly configured

2. **Missing Capabilities**
   ```php
   // Check specific capabilities
   if ( ! current_user_can( 'edit_posts' ) ) {
     // User doesn't have required capabilities
   }
   ```

3. **Common Solutions**
   - Review the [Authentication and Authorization Guide](/docs/authentication-and-authorization/)
   - Check if user role has necessary permissions
   - Verify operation requires authentication

### "Field `fieldName` is not defined"

This error occurs when requesting non-existent fields:

1. **Schema Verification**
   ```graphql
   # Use introspection to check available fields
   {
     __type(name: "Post") {
       fields {
         name
         type {
           name
         }
       }
     }
   }
   ```

   Or browse the Schema using the WPGraphQL IDE.

2. **Common Causes**
   - Typos in field names (GraphQL is case-sensitive)
   - Fields not registered or registered too late
   - Fields registered in the wrong context (e.g., not in the correct post type or taxonomy)
   - Fields registered in the wrong hook (e.g., not in the correct hook like `graphql_register_types`)
   - Fields registered with an already used field name
   - Plugin/theme conflicts removing fields
   - Custom Post Type or Taxonomy not showing in GraphQL

3. **Debug Steps**
   ```php
   // Debug field registration
   add_action( 'graphql_register_types', function() {
     graphql_debug( 'Registering fields...' );
     // Your field registration code
   }, 9 ); // Check if fields are registered before core
   ```

### "Cannot return null for non-nullable field"

This error occurs when a non-nullable field resolver returns null:

1. **Check Field Definition**
   ```php
   // ❌ Problematic: Non-nullable field that might return null
   register_graphql_field( 'Post', 'myField', [
     'type' => [ 'non_null' => 'String' ], // Non-nullable
     'resolve' => function( $post ) {
       return get_post_meta( $post->databaseId, 'maybe_empty', true );
     }
   ]);

   // ✅ Better: Make field nullable if data might not exist
   register_graphql_field( 'Post', 'myField', [
     'type' => 'String', // Nullable
     'resolve' => function( $post ) {
       return get_post_meta( $post->databaseId, 'maybe_empty', true );
     }
   ]);
   ```

2. **Common Causes**
   - Meta fields that might not exist
   - Incorrect type definitions
   - Missing data in resolvers
   - Database queries returning no results

For more detailed debugging information, see:
- [Debugging Guide](/docs/debugging/)
- [Default Types and Fields](/docs/default-types-and-fields/)

## Plugin Conflicts

### Common Plugin Issues

1. **WPML (WordPress Multilingual)**

   **Common Problems:**
   - Missing translations in GraphQL responses
   - Language switcher not working with GraphQL queries
   - Incorrect language context in resolvers

   **Solutions:**
   - Use the [WPGraphQL WPML](https://github.com/valu-digital/wp-graphql-wpml) extension
   - Ensure proper language parameter passing in queries
   - Consider implementing custom resolvers for specific translation needs
   - Browse past issues and see if there are any solutions for your specific problem.
   - Contact the WPML plugin author for support.

2. **Post/Term Ordering Plugins**

   **Common Problems:**
   - Custom order not reflected in GraphQL queries
   - Ordering conflicts with WPGraphQL pagination
   - Performance issues with large datasets

   **Solutions:**
   - Use WPGraphQL's built-in ordering capabilities where possible
   - Implement custom order field resolvers if needed
   - Consider using `menu_order` for basic ordering needs
   - Browse past issues and see if there are any solutions for your specific problem.
   - Contact the ordering plugin author for support.

3. **Caching Plugins**
   - WP Super Cache
   - W3 Total Cache
   - WP Rocket

   **Common Problems:**
   - GraphQL responses being incorrectly cached
   - Not respecting GraphQL variables in cache keys

   **Solution:**
   It might be necessary to exclude the GraphQL endpoint from caching for some caching plugins and use something specific like WPGraphQL Smart Cache on a supported host.

4. **SEO Plugins**
   For proper SEO data integration, use recommended extensions:
   - [WPGraphQL for Yoast SEO](https://github.com/ashhitch/wp-graphql-yoast-seo)
   - [WPGraphQL for RankMath](https://github.com/harness-software/wp-graphql-rank-math)

### Troubleshooting Steps

1. **Identify Conflicts**
   - Disable plugins one by one to isolate issues
   - Use Query Monitor to track unexpected behavior
   - Check error logs for plugin-specific errors

2. **Common Solutions**
   - Update plugins to latest versions
   - Check for WPGraphQL compatibility
   - Look for officially recommended WPGraphQL extensions
   - Contact plugin authors about GraphQL support

For more information about plugin compatibility and integration:
- [WPGraphQL Extensions](/docs/extensions/)
- [Performance Guide](/docs/performance/)

## Development Environment Issues

### Local Development Setup

1. **Apache/Nginx Configuration**

   **Common Problems:**
   - Permalink issues
   - ModRewrite not enabled
   - CORS issues in headless setups

   **Solutions:**
   ```apache
   # Example Apache configuration
   <IfModule mod_rewrite.c>
   RewriteEngine On
   RewriteBase /
   RewriteRule ^graphql - [L]
   # ... other rules
   </IfModule>
   ```

2. **PHP Configuration**

   **Common Issues:**
   - Insufficient memory limits
   - Short execution time
   - Missing required PHP extensions

   **Solutions:**
   ```ini
   # php.ini or wp-config.php settings
   memory_limit = 256M
   max_execution_time = 300
   upload_max_filesize = 64M
   post_max_size = 64M
   ```

### Docker Environment

1. **Container Communication**

   **Common Issues:**
   - Cross-container networking
   - Volume mounting permissions
   - PHP extensions missing in container

   **Solutions:**
   ```yaml
   # Example docker-compose.yml adjustments
   services:
     wordpress:
       image: wordpress:php8.0
       volumes:
         - ./wp-content:/var/www/html/wp-content
       environment:
         WORDPRESS_DEBUG: 1
         PHP_INI_MEMORY_LIMIT: 256M
   ```

2. **Development Tools Integration**

   **Recommendations:**
   - Use xdebug for PHP debugging
   - Configure Query Monitor to track GraphQL queries
   - Enable WordPress and GraphQL debugging

   ```php
   // wp-config.php for local Docker environment
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   define( 'GRAPHQL_DEBUG', true );
   ```

   Query Monitor will automatically track GraphQL queries when WPGraphQL is installed and WPGraphQL will show the query logs when GraphQL Query Logging is enabled.

### Testing Environment

When developing with WPGraphQL, it's important to have a proper testing environment:

1. **Local Development Environment**
   - Use a development-specific wp-config.php
   - Enable debugging
   - Configure error logging
   ```php
   // wp-config.php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   define( 'GRAPHQL_DEBUG', true );
   ```

2. **Testing Best Practices**
   - Test queries in GraphiQL IDE before implementation
   - Verify schema changes with introspection queries
   - Test with different user roles and permissions (including public vs logged in users)
   - Validate mutations with sample data

For more detailed information about testing WPGraphQL, see:
- [Testing Guide](/docs/testing/)
- [Contributing Guide](/docs/contributing/)

## Debugging Strategies

WPGraphQL provides several debugging tools and strategies. For comprehensive debugging information, see the [Debugging Guide](/docs/debugging/).

Common debugging approaches:

1. **Enable Debug Mode**
   - Enable from WPGraphQL Settings page
   - Or add to wp-config.php:
   ```php
   define( 'GRAPHQL_DEBUG', true );
   ```

2. **Use Query Monitor**
   - Tracks GraphQL queries
   - Shows database queries
   - Monitors performance
   - Outputs to the GraphQL response when GraphQL Query Logging is enabled

3. **Debug Response Data**
   - Use `graphql_debug()` to add debug info to response
   - Enable Query Logging to see SQL queries in response
   - Enable Tracing to see resolver timing in response
   - Check the "extensions" portion of responses for debug data

   Note: Debug information is only available in the response and is not persisted to logs.

4. **Common Debug Steps**
   - Test queries in GraphiQL IDE first
   - Check server logs for PHP errors
   - Verify proper hook timing
   - Test with minimal active plugins

For detailed information about each debugging method, examples, and troubleshooting tips, see the [Debugging Guide](/docs/debugging/).

## Common Configuration Mistakes

### WordPress Configuration

1. **Permalink Structure**
   - Recommended to be set to "Post name" or another pretty permalink structure
   - Default permalink structure can technically work with WPGraphQL, but is not recommended.

2. **PHP Version**
   - WPGraphQL requires PHP 7.4 or higher
   - Recommended to use latest stable PHP version (8.x)
   - Some features may require newer PHP versions

### Server Configuration

1. **Memory Limits**
   ```php
   // Recommended minimum settings
   define( 'WP_MEMORY_LIMIT', '256M' );
   define( 'WP_MAX_MEMORY_LIMIT', '512M' );
   ```

2. **Execution Time**
   ```php
   // Adjust based on your needs
   set_time_limit(300); // 5 minutes
   ini_set('max_execution_time', 300);
   ```

3. **POST Request Size**
   ```ini
   # php.ini settings
   upload_max_filesize = 64M
   post_max_size = 64M
   ```

### Common Misconfigurations

1. **CORS Settings**
   - WPGraphQL allows all origins by default (`Access-Control-Allow-Origin: *`)
   - Only modify if you need to restrict to specific domains
   ```php
   // Example: Restrict to specific domain
   add_filter( 'graphql_response_headers_to_send', function( $headers ) {
     // Only modify if you need to restrict access
     $headers['Access-Control-Allow-Origin'] = 'https://your-frontend-domain.com';
     return $headers;
   });
   ```

   > Note: The default CORS configuration works for most setups. Only modify if you have specific security requirements.

2. **SSL Configuration**
   - Mixed content issues with HTTPS
   - Certificate validation problems
   - Incorrect site URL configuration
   ```php
   // Force HTTPS for GraphQL endpoint
   if ( ! is_ssl() && 'graphql' === $GLOBALS['wp']->request ) {
     wp_redirect( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
     exit();
   }
   ```

3. **File Permissions**
   - Upload directory permissions
   - Plugin directory access
   - WordPress core file permissions
   ```bash
   # Recommended permissions
   chmod 755 wp-content/plugins
   chmod 755 wp-content/themes
   chmod 755 wp-content/uploads
   ```

For more detailed configuration information, see:
- [Getting Started Guide](/docs/getting-started/)
- [Security Guide](/docs/security/)