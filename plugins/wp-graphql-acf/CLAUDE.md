# CLAUDE.md

Guidance for Claude Code working in the WPGraphQL for ACF plugin. The repo-root `CLAUDE.md` covers the monorepo and shared conventions; this file covers `plugins/wp-graphql-acf/`. Workspace: `@wpgraphql/wp-graphql-acf`.

## Overview

Integrates Advanced Custom Fields with WPGraphQL — exposes ACF field groups and fields in the GraphQL schema. Requires WPGraphQL and ACF (free or Pro). PHP 7.3+, WordPress 5.9+.

**Namespace**: `WPGraphQL\Acf\` (PSR-4 autoloading → `src/`). Entry point: `wpgraphql-acf.php`; public API in `access-functions.php`.

## Testing

Tests need ACF installed in the test environment first, so install it before running WPUnit or e2e:

```bash
npm run -w @wpgraphql/wp-graphql-acf install-test-deps   # test dependencies
npm run -w @wpgraphql/wp-graphql-acf install-acf         # free ACF (or install-acf:pro for ACF Pro)

npm run -w @wpgraphql/wp-graphql-acf test:codecept:wpunit
npm run -w @wpgraphql/wp-graphql-acf test:e2e            # Playwright (needs wp-env + the installs above)
```

For a full CI-like local run (build, start wp-env, install ACF, test, stop) use the helper from the repo root: `./bin/run-acf-e2e-local.sh`.

## Linting and Static Analysis

```bash
npm run -w @wpgraphql/wp-graphql-acf lint:js
npm run -w @wpgraphql/wp-graphql-acf lint:php        # PHPCS (WordPress Coding Standards)
npm run -w @wpgraphql/wp-graphql-acf lint:php:stan   # PHPStan
```

## Reference docs

Additional documentation lives in [`docs/`](docs/).

<!-- Maintainer note: starting point — expand with architecture notes (field-type → GraphQL-type mapping, the ACF field-group registry integration) as needed. -->

