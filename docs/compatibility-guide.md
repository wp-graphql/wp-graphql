---
uri: "/docs/compatibility-guide/"
title: "Compatibility Guide"
---

# Compatibility Guide

This guide outlines compatibility requirements and considerations when using WPGraphQL.

## WordPress Compatibility

While WPGraphQL may work with WordPress 5.0+, we [actively test](https://github.com/wp-graphql/wp-graphql/blob/master/.github/workflows/testing-integration.yml) and support against newer versions of WordPress. For the best experience and support, we strongly recommend keeping WordPress updated to the latest stable version.

### Version Requirements

- **Minimum**: WordPress 5.0+ (not actively tested, but may work)
- **Recommended**: Latest WordPress version
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

- **WPGraphQL v1.x**: PHP 7.1 or higher
- **WPGraphQL v2.x**: PHP 7.4 or higher
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

WPGraphQL is compatible with many WordPress plugins, but some considerations apply:

1. **Official Extensions**
   - [WPGraphQL for Advanced Custom Fields](https://www.wpgraphql.com/acf)
   - [WPGraphQL for WooCommerce](https://www.wpgraphql.com/woocommerce)
   - [WPGraphQL for Gravity Forms](https://www.wpgraphql.com/gravity-forms)
   - And other [recommended extensions](/extensions)

2. **Common Plugin Types**
   - **Custom Field Plugins**: May require extension plugins or custom code to expose fields in the GraphQL Schema
   - **SEO Plugins**: Extensions available for popular SEO plugins like Yoast and RankMath
   - **E-commerce Plugins**: WooCommerce supported via extension, others may require custom integration
   - **Form Plugins**: Some form plugins have GraphQL extensions (i.e. [Gravity Forms](https://github.com/AxeWP/wp-graphql-gravity-forms))

3. **Plugin Development**
   - Follow [WPGraphQL extension development guidelines](/docs/build-your-first-wpgraphql-extension)
   - Use proper hooks and filters
   - Register custom types and fields appropriately
   - Consider performance implications
   - Consider user permissions and data access/visibility

### Theme Compatibility

WPGraphQL works with any WordPress theme, but there are special considerations:

1. **Headless WordPress**
   - WPGraphQL is commonly used in headless WordPress setups
   - Consider using a minimal theme for headless setups
   - Frontend rendering typically happens outside of WordPress

2. **Traditional Themes**
   - Can use WPGraphQL alongside traditional WordPress themes
   - Useful for progressive enhancement
   - Can power specific dynamic features via GraphQL

3. **Block Theme Considerations**
   - Full Site Editing (FSE) features exposed via GraphQL requires custom code at this time to expose the FSE features in the GraphQL Schema
     - Block templates accessible through the API
     - Theme.json data available through the schema
     - We're considering looking into supporting this more formally in the future

## API Compatibility

### GraphQL Specification

WPGraphQL follows the GraphQL specification:

- Compliant with the [GraphQL Specification](https://spec.graphql.org/)
- Supports introspection queries
- Implements standard GraphQL features (aliases, fragments, variables, etc.)
- Follows GraphQL best practices for schema design, adhering to the [Relay spec](https://relay.dev/docs/guides/graphql-server-specification/) for GraphQL

### Schema Versioning

WPGraphQL takes a stability-first approach to schema changes:

- Breaking changes are avoided in minor/patch releases
- Deprecated fields are marked before removal
- Major version changes may include breaking schema changes
- Schema changes are documented in release notes
- More info can be found in the [Upgrade Guide](/docs/upgrading/)

### HTTP API Considerations

The GraphQL endpoint follows standard HTTP conventions:

- Accepts POST requests for queries and mutations
- Accepts GET requests for queries
- Returns JSON responses
- Supports standard HTTP headers
- Follows REST API authentication patterns

### Client Compatibility

WPGraphQL works with any GraphQL client that follows the specification:

- [FaustJS](https://faustjs.org/) - Framework for building WordPress sites with Next.js
- [Apollo Client](https://www.apollographql.com/docs/react/) - Full-featured GraphQL client with React integration
- [Relay](https://relay.dev/) - Facebook's GraphQL client for React applications
- [urql](https://formidable.com/open-source/urql/) - Highly customizable GraphQL client
- And other standard GraphQL clients