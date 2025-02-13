---
uri: "/docs/compatibility-guide/"
title: "Compatibility Guide"
---

# Compatibility Guide

This guide outlines compatibility requirements and considerations when using WPGraphQL.

## WordPress Compatibility

WPGraphQL requires WordPress 6.0 or higher. We [actively test](https://github.com/wp-graphql/wp-graphql/blob/master/.github/workflows/testing-integration.yml) and support against newer versions of WordPress. For the best experience and support, we strongly recommend keeping WordPress updated to the latest stable version.

### Version Requirements

- **Minimum**: WordPress 6.0+
- **Recommended**: Latest WordPress version
- **Tested up to**: WordPress 6.7.1
- **Testing**: WPGraphQL is actively tested against the latest WordPress version and one major version back. We may test with older versions but do not actively support them and _may_ drop testing for older versions at any time.

### WordPress Features

WPGraphQL works with both Classic Editor and Block Editor (Gutenberg) installations. Some considerations:

- **Block Editor Content**: Block content is exposed in the GraphQL Schema as HTML returned as a String when querying the `content` field. It is the rendered version of the content and not the raw JSON data. Some plugins such as [WPGraphQL Content Blocks](https://github.com/wp-graphql/wp-graphql-content-blocks) provide support for returning Block content in more structured format.
- **Classic Editor Content**: Content is exposed as HTML returned as a String when querying the `content` field.
- **Custom Fields**: Custom Fields can be added to the schema using the `register_graphql_field` function. Advanced Custom Fields users can use the [WPGraphQL for ACF](https://acf.wpgraphql.com/) extension to manage how their ACF fields relate to the GraphQL Schema.
- **Post Types**: Built-in post types (posts, pages, media) are supported out of the box. Custom Post Types can be added to the schema by registering or filtering them to show in graphql. See the [Custom Post Types](/docs/custom-post-types/) documentation for more information.

### Multisite Compatibility

WPGraphQL works with WordPress Multisite installations with some considerations:

- Each site in the network has its own GraphQL endpoint
- Network admin functionality is not exposed in the GraphQL API by default.
- Each endpoint interacts with just the one site. There is no support for querying one endpoint and getting posts from different sites in the multisite network

## PHP Requirements

### Version Requirements

- **Minimum**: PHP 7.4 or higher
- **Recommended**: Latest stable PHP version (8.x)

### PHP Extensions

Required PHP extensions:
- `json`: For JSON encoding/decoding
- `mbstring`: For proper string handling
- `mysqli` or `pdo_mysql`: For database connectivity

### PHP Configuration

Recommended PHP configuration settings for optimal performance:

```ini
# Memory Limits
memory_limit = 256M

# Execution Time
max_execution_time = 300

# Upload Limits
upload_max_filesize = 64M
post_max_size = 64M
```

> Note: These are general recommendations. Your specific needs may vary based on your use case and server environment.

## Server Requirements

### Web Server Compatibility

WPGraphQL works with any web server that supports WordPress:

- **Apache**: Most common setup, works out of the box
- **Nginx**: Works with standard WordPress configuration
- **Other**: Any server that can run WordPress should work with WPGraphQL

### URL Rewrite Rules

WPGraphQL uses WordPress's rewrite rules to handle the `/graphql` endpoint:

- Ensure your server has URL rewriting enabled (mod_rewrite for Apache)
- The WordPress Permalink settings are recommended be set to anything other than "Plain"
- The `/graphql` endpoint should be accessible at your site's root

### SSL/HTTPS

While not strictly required, HTTPS is recommended:

- Secure communication between client and server
- Required by some authentication methods
- Best practice for production environments

### Server Environment

Your server environment should meet or exceed WordPress's requirements:

- Sufficient disk space for WordPress + plugins
- Adequate memory allocation
- Database access and permissions
- Write permissions for WordPress directories

## Database Compatibility

### MySQL/MariaDB Requirements

WPGraphQL works with the same database requirements as WordPress:

- **MySQL**: Version 5.7 or greater
- **MariaDB**: Version 10.3 or greater
- **Recommended**: Latest stable version of MySQL 8.0+ or MariaDB 10.6+
- **Character Set**: UTF-8 (utf8mb4)
- **Collation**: utf8mb4_unicode_ci or utf8mb4_general_ci recommended

### Database Configuration

For optimal performance:

- Ensure proper indexing on commonly queried fields
- Configure adequate connection limits
- Set appropriate max_allowed_packet size
- Consider using an object cache for better performance
- Consider using network cache (varnish, etc) along with WPGraphQL Smart Cache for better performance

### Custom Database Tables

If your application uses custom database tables:

- Tables must follow WordPress naming conventions
- Custom tables should be registered properly with `$wpdb`
- Consider implementing a DataLoader for efficient querying
- Follow WordPress best practices for database operations

## Plugin and Theme Compatibility

### Plugin Compatibility

WPGraphQL is compatible with many WordPress plugins through official and community extensions:

1. **Official Extensions**
   - [WPGraphQL for Advanced Custom Fields](https://acf.wpgraphql.com)
   - [WPGraphQL for WooCommerce](https://github.com/wp-graphql/wp-graphql-woocommerce)
   - [WPGraphQL JWT Authentication](https://github.com/wp-graphql/wp-graphql-jwt-authentication)
   - [WPGraphQL Content Blocks](https://github.com/wp-graphql/wp-graphql-content-blocks)

2. **Common Plugin Types**
   - **Custom Field Plugins**:
     - Advanced Custom Fields (via WPGraphQL for ACF)
     - Custom Field Suite (via custom code)
     - Meta Box (via custom code)
   - **SEO Plugins**:
     - Yoast SEO (via [wp-graphql-yoast-seo](https://github.com/ashhitch/wp-graphql-yoast-seo))
     - RankMath (via [wp-graphql-rank-math](https://github.com/AxeWP/wp-graphql-rank-math))
   - **E-commerce**:
     - WooCommerce (via WPGraphQL for WooCommerce)
     - Easy Digital Downloads (via community extensions)
   - **Form Plugins**:
     - Gravity Forms (via [wp-graphql-gravity-forms](https://github.com/harness-software/wp-graphql-gravity-forms))
     - Contact Form 7 (via community extensions)

3. **Plugin Development**
   - Follow [WPGraphQL extension development guidelines](/docs/build-your-first-wpgraphql-extension)
   - Use WordPress hooks and filters
   - Register types and fields using WPGraphQL's registration API
   - Consider data access and authorization
   - Follow performance best practices

### Theme Compatibility

WPGraphQL works with any WordPress theme:

1. **Headless WordPress**
   - Commonly used in decoupled/headless WordPress setups
   - Recommended to use a minimal theme
   - Frontend typically built with:
     - Next.js (via [FaustJS](https://faustjs.org/))
     - Gatsby
     - Other JavaScript frameworks

2. **Traditional Themes**
   - Compatible with all traditional WordPress themes
   - Can be used for:
     - Dynamic features
     - Custom admin interfaces
     - Progressive enhancement

3. **Block Theme Considerations**
   - Compatible with Full Site Editing (FSE) themes, but many FSE features require custom code to expose in the GraphQL Schema
   - Block templates and theme.json data are not exposed in the Schema by default and require custom code
   - Block content is rendered as HTML by default when querying the `content` field
   - Structured block data available via WPGraphQL Content Blocks extension
   - Full FSE support is planned for future releases

## API Compatibility

### GraphQL Specification

WPGraphQL follows the GraphQL specification through its core dependencies:

- Built on [graphql-php](https://github.com/webonyx/graphql-php) v15.19.1
- Implements [Relay Specification](https://relay.dev/docs/guides/graphql-server-specification/) via [graphql-relay-php](https://github.com/ivome/graphql-relay-php) v0.7.0
- Supports standard GraphQL features:
  - Queries and Mutations
  - Field Arguments
  - Aliases
  - Fragments
  - Variables
  - Directives
  - Interfaces
  - Unions
  - Input Types
  - Introspection

### Schema Versioning

WPGraphQL takes a stability-first approach to schema changes:

- Breaking changes are avoided in minor/patch releases
- Deprecated fields are marked before removal
- Major version changes may include breaking schema changes
- Schema changes are documented in release notes
- More info can be found in the [Upgrade Guide](/docs/upgrading/)

### HTTP API Considerations

The GraphQL endpoint follows standard HTTP conventions:

- Endpoint: `/graphql`
- Methods:
  - GET: Recommended for queries to benefit from network-level caching (Varnish, Litespeed, Cloudflare, etc.)
  - POST: Required for mutations, can be used for queries but won't benefit from network caching
- Content-Type: `application/json`
- Response Format: JSON
- Authentication: Supports standard WordPress authentication methods
  - Cookie Authentication
  - Application Passwords
  - JWT Authentication (via extension)
- Batching: Supports multiple operations in a single request
- File Uploads: Supported via multipart form data
- Caching:
  - GET requests can be cached at network level (CDN, reverse proxy)
  - WPGraphQL Smart Cache provides additional caching capabilities
  - Consider using object caching (Redis, Memcached) for better performance

### Client Compatibility

WPGraphQL works with any GraphQL client that follows the specification:

- [FaustJS](https://faustjs.org/) - Framework for building WordPress sites with Next.js
- [Apollo Client](https://www.apollographql.com/docs/react/) - Full-featured GraphQL client with React integration
- [Relay](https://relay.dev/) - Facebook's GraphQL client for React applications
- [urql](https://formidable.com/open-source/urql/) - Highly customizable GraphQL client
- And other standard GraphQL clients

### Testing Coverage

WPGraphQL is actively tested against:
- PHP versions 7.4 through 8.3
- WordPress versions 6.0 through 6.7
- Both single site and multisite configurations
- MariaDB 10.x