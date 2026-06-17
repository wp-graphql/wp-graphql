# CLAUDE.md

Guidance for Claude Code working in the core WPGraphQL plugin. The repo-root `CLAUDE.md` covers the monorepo and shared conventions; this file covers `plugins/wp-graphql/` specifics. Workspace: `@wpgraphql/wp-graphql`.

## Architecture

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
```

## Coding Conventions

- **PHP**: WordPress Coding Standards (PHPCS), PHPStan level 8 (strict). Minimum PHP 7.4.
- **Autoloading**: the `WPGRAPHQL_AUTOLOAD` constant can disable vendor autoload for environments with a global autoloader.
- See the repo-root `CLAUDE.md` for shared conventions (conventional commits, `@since x-release-please-version` placeholders, deprecation pattern).

## Debugging

- `define('GRAPHQL_DEBUG', true)` or enable via WPGraphQL Settings
- Query Logs: requires Query Monitor plugin, enable in WPGraphQL Settings
- Query Tracing: shows resolver timing, enable in WPGraphQL Settings

## Key Dependencies

- `webonyx/graphql-php` — Core GraphQL PHP implementation
- `ivome/graphql-relay-php` — Relay specification implementation
