# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

WPGraphQL is a monorepo containing the WPGraphQL ecosystem of WordPress plugins. The core plugin adds a `/graphql` endpoint to WordPress and defines a GraphQL Schema based on WordPress registries (post types, taxonomies, settings, etc.). Uses npm workspaces and Turborepo for orchestration.

### Plugins in the Monorepo

- **plugins/wp-graphql/** — Core GraphQL API plugin (PHP 7.4+, WP 6.0+)
- **plugins/wp-graphql-ide/** — GraphiQL IDE for WordPress admin (React-based)
- **plugins/wp-graphql-smart-cache/** — Caching and cache invalidation
- **plugins/wp-graphql-schema-monitor/** — Schema change monitoring (experimental)

## Development Environment

Requires Docker. Uses `@wordpress/env` for local WordPress.

```bash
npm install                          # Install all workspace dependencies
npm run wp-env start                 # Dev site: localhost:8888, Test site: localhost:8889
npm run wp-env start -- --xdebug    # Start with XDebug
npm run wp-env stop                  # Stop environment
npm run wp-env destroy               # Reset everything
```

wp-env must be running for tests and linting commands to work.

## Build Commands

```bash
npm run build                        # Build all workspaces (Turborepo)
npm run -w @wpgraphql/wp-graphql build   # Build specific plugin
```

## Testing

Tests run inside wp-env Docker containers. Test file paths are relative to `plugins/wp-graphql/`.

```bash
# WPUnit tests (Codeception + PHPUnit)
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit

# Single test file
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit -- tests/wpunit/PostObjectQueriesTest.php

# Single test method
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit -- tests/wpunit/PostObjectQueriesTest.php:testPostQuery

# Acceptance and functional tests
npm run -w @wpgraphql/wp-graphql test:codecept:acceptance
npm run -w @wpgraphql/wp-graphql test:codecept:functional

# E2E (Playwright)
npm run -w @wpgraphql/wp-graphql test:e2e

# Other plugins
npm run -w @wpgraphql/wp-graphql-smart-cache test:codecept:wpunit
npm run -w @wpgraphql/wp-graphql-ide test:unit
npm run -w @wpgraphql/wp-graphql-ide test:e2e
```

## Linting and Static Analysis

File paths in linting commands are relative to `plugins/wp-graphql/` (e.g., use `src/Data/NodeResolver.php` not `plugins/wp-graphql/src/Data/NodeResolver.php`).

```bash
# PHP coding standards (WordPress Coding Standards)
npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run check-cs -- src/Data/NodeResolver.php
npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run fix-cs -- src/Data/NodeResolver.php

# PHPStan (level 8, strict) — requires memory limit flag
npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run phpstan -- --memory-limit=2G

# JavaScript
npm run -w @wpgraphql/wp-graphql lint:js
npm run format                       # Prettier across all workspaces
```

## Core Plugin Architecture (plugins/wp-graphql/)

**Entry point**: `wp-graphql.php` → loads `constants.php`, `activation.php`, `access-functions.php`, then `WPGraphQL::instance()->setup()`.

**Namespace**: `WPGraphQL\` (PSR-4 autoloading via Composer, maps to `src/`).

Key source directories under `src/`:

- **Registry/** — `TypeRegistry` and `SchemaRegistry` for registering all GraphQL types and fields
- **Type/** — GraphQL type definitions (ObjectType, Enum, Interface, Union, Input)
- **Connection/** — Relay-style cursor-based pagination (`first`/`after`/`last`/`before`)
- **Data/** — Data loaders that batch-load and cache DB queries to prevent N+1 problems
- **Model/** — Access control and data transformation layer before resolution (e.g., `Post.php`, `User.php`)
- **Mutation/** — GraphQL mutation definitions
- **Server/** — GraphQL server configuration and validation rules
- **Admin/** — WordPress admin UI
- **Utils/** — Utilities like `InstrumentSchema`

**Public API**: `access-functions.php` contains functions like `register_graphql_type()`, `register_graphql_field()`, `register_graphql_connection()`, etc.

### Registering Types and Fields

```php
add_action( 'graphql_register_types', function( $type_registry ) {
    register_graphql_field( 'Post', 'customField', [
        'type' => 'String',
        'resolve' => function( $post ) {
            return get_post_meta( $post->databaseId, 'custom_field', true );
        }
    ]);
});
```

## Coding Conventions

- **PHP**: WordPress Coding Standards (PHPCS), PHPStan level 8. Minimum PHP 7.4.
- **JavaScript**: `@wordpress/scripts` with ESLint and Prettier.
- **Version placeholders**: Use `@since next-version` in PHPDoc `@since` tags and deprecation functions.
- **Deprecation**: `_deprecated_argument( __METHOD__, '@since next-version', 'Message.' );`
- **Autoloading**: `WPGRAPHQL_AUTOLOAD` constant can disable vendor autoload for environments with a global autoloader.

## Development Workflow

- **TDD preferred**: For bug fixes, write failing tests first, confirm they fail, implement the fix, confirm they pass.
- **Conventional Commits**: PR titles must follow format (`feat:`, `fix:`, `perf:`, `docs:`, `chore:`, etc.). PRs are squash-merged so the title becomes the commit message. The `!` suffix (e.g., `feat!:`) signals a breaking change.
- **CI matrix**: Tests run across WordPress 6.1–trunk, PHP 7.4–8.4, block and classic themes, single and multisite.

## Debugging

- `define('GRAPHQL_DEBUG', true)` or enable via WPGraphQL Settings
- Query Logs: requires Query Monitor plugin, enable in WPGraphQL Settings
- Query Tracing: shows resolver timing, enable in WPGraphQL Settings

## Key Dependencies

- `webonyx/graphql-php` — Core GraphQL PHP implementation
- `ivome/graphql-relay-php` — Relay specification implementation
