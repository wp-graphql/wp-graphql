# WPGraphQL Persisted Query Cache

**Status**: Beta (v0.1.0-beta.1)  
**Requires**: WPGraphQL 2.0.0+, WPGraphQL Smart Cache

WPGraphQL Persisted Query Cache enables persisted GraphQL queries via permalink-based URLs instead of query strings, allowing surgical cache invalidation on hosts that don't support tag-based purging (WordPress VIP and similar). This plugin extends WPGraphQL Smart Cache's purge system.

## Overview

WPGraphQL Smart Cache provides excellent caching for hosts that support tag-based cache invalidation. However, many hosts (including WordPress VIP) only support URL-based purging. This plugin bridges that gap by:

1. **Permalink-based URLs**: Queries are accessed via clean URLs like `/graphql/persisted/{queryHash}/variables/{variablesHash}` instead of query parameters
2. **URL→Cache Key Index**: Maintains a server-side index mapping URLs to cache keys
3. **Surgical Purge**: When Smart Cache fires purge events, this plugin looks up affected URLs and purges them individually

## Quick Start

### Installation

1. Ensure **WPGraphQL** and **WPGraphQL Smart Cache** are installed and activated
2. Download the latest release from [GitHub Releases](https://github.com/wp-graphql/wp-graphql/releases) (look for `wp-graphql-pqc/v*` tags)
3. Upload and activate the plugin
4. Flush rewrite rules: Visit **Settings → Permalinks → Save Changes**

### Basic Usage

Once activated, the plugin automatically:

- **Stores queries** from POST requests (when enabled)
- **Serves persisted queries** via GET requests to `/graphql/persisted/{hash}`
- **Purges URLs** when Smart Cache fires invalidation events

The plugin returns `persistedQueryUrl` in GraphQL response extensions:

```json
{
  "data": { ... },
  "extensions": {
    "persistedQueryUrl": "/graphql/persisted/abc123.../variables/def456..."
  }
}
```

## Features

### Two-Phase Request Flow

**Phase 1 (Cold Start)**: Client sends POST with full query → Server stores index entry → Returns persisted URL

**Phase 2 (Warm Path)**: Client sends GET to persisted URL → Page cache serves response (fast!) or WordPress re-executes if cache miss

### Authentication-Aware

- **Authenticated users**: Execute as themselves, get `no-store` cache headers
- **Public users**: Execute as public, get cacheable headers (respects Smart Cache settings)

### Host Adapters

- **WordPress VIP**: Auto-detected, uses `wpcom_vip_purge_edge_cache_for_url()`
- **Null Adapter**: Development/testing (logs but doesn't purge)
- **Extensible**: Add custom adapters via filter

## Configuration

### Settings (Coming Soon)

- Enable/disable automatic persistence
- Allow public requests to persist queries
- Configure TTL for garbage collection

### Filters

#### `wpgraphql_pqc_url_base`
Filter the base path for persisted query URLs (default: `graphql/persisted/`)

```php
add_filter( 'wpgraphql_pqc_url_base', function() {
    return 'api/persisted/';
} );
```

#### `wpgraphql_pqc_allow_authenticated`
Allow authenticated requests to be stored (default: `false`)

```php
add_filter( 'wpgraphql_pqc_allow_authenticated', '__return_true' );
```

#### `wpgraphql_pqc_cache_max_age`
Filter the max-age for persisted query cache headers (default: `600` seconds)

```php
add_filter( 'wpgraphql_pqc_cache_max_age', function() {
    return 3600; // 1 hour
} );
```

## Development

### Requirements

- PHP 7.4+
- WordPress 6.0+
- WPGraphQL 2.0.0+
- WPGraphQL Smart Cache

### Local Development

The plugin is part of the WPGraphQL monorepo. See the [main Development guide](../../docs/DEVELOPMENT.md) for setup instructions.

```bash
# Start wp-env
npm run wp-env start

# Run tests
npm run -w @wpgraphql/wp-graphql-pqc test:codecept:wpunit

# Lint
npm run -w @wpgraphql/wp-graphql-pqc wp-env:cli -- composer run check-cs
```

### Database Schema

The plugin creates two custom tables:

- `wp_wpgraphql_pqc_documents`: Stores unique query documents (normalized)
- `wp_wpgraphql_pqc_url_keys`: Junction table mapping URLs to cache keys

See [PRD.md](./PRD.md) for detailed schema documentation.

## Status

**⚠️ Beta Software**: This plugin is currently in beta. Breaking changes may occur before v1.0.0.

**Current Version**: 0.1.0-beta.1

## Documentation

- [Product Requirements Document](./PRD.md) - Detailed specification
- [Testing Guide](./TESTING.md) - Manual testing instructions
- [Implementation Plan](../../.cursor/plans/wpgraphql_pqc_implementation_plan_8a9edd0f.plan.md) - Development roadmap

## Related

- [WPGraphQL Smart Cache](../wp-graphql-smart-cache/) - Core caching plugin
- [WPGraphQL Core](../wp-graphql/) - GraphQL API for WordPress

## License

GPL-3.0-or-later
