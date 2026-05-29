# CLAUDE.md

Guidance for Claude Code working in the WPGraphQL Smart Cache plugin. The repo-root `CLAUDE.md` covers the monorepo and shared conventions; this file covers `plugins/wp-graphql-smart-cache/`. Workspace: `@wpgraphql/wp-graphql-smart-cache`.

## Overview

Smart caching and cache invalidation for WPGraphQL. Smart Cache also owns the `graphql_document` persisted-query primitive (the `graphql_document` post type plus the `graphql_query_alias` / `graphql_document_grant` / `graphql_document_http_maxage` / `graphql_document_group` taxonomies) that other plugins — notably the IDE — build their saved-document features on.

Requires WPGraphQL 2.0+, PHP 7.4+, WordPress 6.0+.

**Namespace**: `WPGraphQL\SmartCache\` (PSR-4 autoloading → `src/`). Entry point: `wp-graphql-smart-cache.php`.

## Testing

Tests run inside wp-env Docker containers, scoped to this workspace:

```bash
npm run -w @wpgraphql/wp-graphql-smart-cache test:codecept:wpunit
npm run -w @wpgraphql/wp-graphql-smart-cache test:codecept:acceptance
npm run -w @wpgraphql/wp-graphql-smart-cache test:codecept:functional
```

## Linting and Static Analysis

PHPCS and PHPStan are Composer scripts. They're static analysis (no running WordPress needed), so run them from `plugins/wp-graphql-smart-cache/` after `composer install` — same as CI does:

```bash
composer check-cs    # PHPCS (WordPress Coding Standards)
composer fix-cs      # PHPCBF autofix
composer phpstan     # PHPStan (config in phpstan.neon.dist)
```

## Reference docs

Additional documentation lives in [`docs/`](docs/).

<!-- Maintainer note: starting point — expand with architecture notes (cache key/tag model, invalidation flow, the Query Analyzer) as needed. -->

