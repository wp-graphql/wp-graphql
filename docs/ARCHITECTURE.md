# Architecture Overview

This document describes the architecture of the WPGraphQL monorepo.

## Monorepo Structure

```
wp-graphql/
├── plugins/                    # WordPress plugins
│   └── wp-graphql/            # WPGraphQL core plugin
├── docs/                      # Contributor documentation
├── bin/                       # Shared scripts
├── .github/                   # GitHub workflows and templates
├── .wp-env.json              # WordPress environment config
├── package.json              # Root npm workspace config
└── turbo.json                # Turborepo configuration
```

## Why a Monorepo?

The WPGraphQL ecosystem includes multiple related plugins:
- **WPGraphQL** (core)
- **WPGraphQL Smart Cache**
- **WPGraphQL for ACF**
- **WPGraphQL IDE**

Benefits of the monorepo approach:

1. **Unified Testing** - Test plugins together to catch integration issues
2. **Shared Infrastructure** - One CI/CD setup, one test environment
3. **Atomic Changes** - Changes across plugins can be a single PR
4. **Simplified Dependencies** - Easier to keep plugins compatible
5. **Consistent Tooling** - Same linting, testing, and build tools

## Package Management

### npm Workspaces

The root `package.json` defines workspaces:

```json
{
  "workspaces": ["plugins/*"]
}
```

This means:
- `npm install` at root installs all workspace dependencies
- Dependencies are hoisted to root `node_modules/` when possible
- Each plugin can have its own `package.json` for plugin-specific deps

### Turborepo

Turborepo orchestrates tasks across workspaces:

```json
{
  "pipeline": {
    "build": {
      "dependsOn": ["^build"],
      "outputs": ["build/**", "dist/**"]
    },
    "test": {
      "dependsOn": ["build"]
    }
  }
}
```

Benefits:
- **Caching** - Skip unchanged work
- **Parallelization** - Run independent tasks concurrently
- **Dependency Awareness** - Ensure correct build order

## WordPress Environment

### wp-env

The development environment uses `@wordpress/env`:

```json
// .wp-env.json
{
  "plugins": ["./plugins/wp-graphql"],
  "env": {
    "tests": {
      "plugins": ["./plugins/wp-graphql"]
    }
  }
}
```

This provides:
- **Development site** at `localhost:8888`
- **Test site** at `localhost:8889`
- **MySQL database** in Docker
- **Consistent PHP/WordPress versions**

### Lifecycle Scripts

The `afterStart` lifecycle hook runs setup automatically:

```json
{
  "lifecycleScripts": {
    "afterStart": "bash bin/setup-wp-env.sh"
  }
}
```

## Plugin Architecture

### WPGraphQL Core (`plugins/wp-graphql/`)

```
wp-graphql/
├── wp-graphql.php           # Plugin entry point
├── constants.php            # Version constants
├── src/                     # PHP source code
│   ├── Admin/              # Admin UI
│   ├── Connection/         # GraphQL connections
│   ├── Data/               # Data loaders
│   ├── Model/              # Data models
│   ├── Mutation/           # GraphQL mutations
│   ├── Registry/           # Type registry
│   ├── Type/               # GraphQL types
│   └── Utils/              # Utilities
├── tests/                   # Test suites
│   ├── wpunit/             # WordPress unit tests
│   ├── acceptance/         # HTTP tests
│   ├── functional/         # Functional tests
│   └── e2e/                # Browser tests
├── packages/                # JavaScript packages
│   └── graphiql-app/       # GraphiQL IDE
├── docs/                    # User documentation
├── composer.json            # PHP dependencies
└── package.json             # JS dependencies
```

### Key Concepts

**Type Registry**: Central registry for all GraphQL types
```php
register_graphql_type('MyType', [...]);
register_graphql_field('Post', 'customField', [...]);
```

**Data Loaders**: Batch and cache database queries
```php
$loader = new PostObjectLoader($context);
$loader->load($id);
```

**Connections**: Relay-style pagination
```php
register_graphql_connection([
    'fromType' => 'RootQuery',
    'toType' => 'Post',
    'fromFieldName' => 'posts',
]);
```

## CI/CD Pipeline

### Workflows

| Workflow | Trigger | Purpose |
|----------|---------|---------|
| `integration-tests.yml` | PR, Push | Run integration tests (uses reusable workflow) |
| `integration-tests-reusable.yml` | Called by other workflows | Reusable integration test workflow |
| `code-quality.yml` | PR, Push | PHPStan analysis |
| `wordpress-coding-standards.yml` | PR, Push | PHPCS linting |
| `schema-linter.yml` | PR, Push | GraphQL schema validation |
| `release-please.yml` | Push to main | Automated releases via release-please |

### Testing Strategy

Tests run across a matrix of configurations:
- PHP versions: 7.4 - 8.4
- WordPress versions: 6.1 - trunk
- Themes: Classic (twentytwentyone), Block (twentytwentyfive)
- Single site and Multisite

### Release Process

We use [release-please](https://github.com/googleapis/release-please) for automated releases:

1. PRs are merged to `main` with conventional commit titles
2. release-please creates/updates a Release PR with changelog and version bump
3. When the Release PR is merged, a GitHub release is created
4. The release triggers deployment to WordPress.org

## Future Plugins

When adding new plugins to the monorepo, follow these steps:

### 1. Plugin Structure

Create the plugin directory structure in `plugins/`:

```
plugins/
├── wp-graphql/
└── your-plugin-name/
    ├── your-plugin-name.php    # Main plugin file
    ├── src/                     # PHP source code
    ├── tests/                   # Test suites
    │   ├── wpunit/
    │   ├── acceptance/
    │   └── functional/
    ├── composer.json            # PHP dependencies
    └── package.json             # JS dependencies (if needed)
```

### 2. Package Configuration

**Add to root `package.json` workspaces:**
The workspace is automatically included via the `"workspaces": ["plugins/*"]` pattern, but ensure your plugin's `package.json` has a unique workspace name:

```json
{
  "name": "@wpgraphql/your-plugin-name",
  "private": true
}
```

### 3. Release Configuration

**Add to `release-please-config.json`:**

Add a new entry in the `packages` object:

```json
{
  "packages": {
    "plugins/your-plugin-name": {
      "release-type": "simple",
      "component": "your-plugin-name",
      "package-name": "your-plugin-name",
      "bump-minor-pre-major": false,
      "bump-patch-for-minor-pre-major": false,
      "draft": false,
      "prerelease": false,
      "changelog-path": "CHANGELOG.md",
      "changelog-sections": [ /* ... */ ],
      "extra-files": [
        {
          "type": "generic",
          "path": "your-plugin-name.php",
          "glob": false
        },
        {
          "type": "generic",
          "path": "readme.txt",
          "glob": false
        },
        {
          "type": "json",
          "path": "package.json",
          "jsonpath": "$.version"
        }
      ]
    }
  }
}
```

**Important:** Include all files that contain version numbers in `extra-files`:
- Main plugin file (`.php`)
- `readme.txt` (for WordPress.org)
- `package.json` (if it has a version field)
- Any PHP constants files with version constants

### 4. WordPress Environment

**Update `.wp-env.json`:**

Add the plugin to both `plugins` and `env.tests.plugins` arrays:

```json
{
  "plugins": [
    "./plugins/wp-graphql",
    "./plugins/your-plugin-name"
  ],
  "env": {
    "tests": {
      "plugins": [
        "./plugins/wp-graphql",
        "./plugins/your-plugin-name"
      ],
      "mappings": {
        "/wp-content/plugins/your-plugin-name": "./plugins/your-plugin-name"
      }
    }
  }
}
```

### 5. Integration Tests

**Add to `.github/workflows/integration-tests.yml`:**

1. **Add change detection pattern** in the `detect-changes` job:

```yaml
files_yaml: |
  your-plugin-name:
    - plugins/your-plugin-name/**/*.php
    - plugins/your-plugin-name/composer.json
    - plugins/your-plugin-name/composer.lock
    - plugins/your-plugin-name/package.json
    - plugins/your-plugin-name/package-lock.json
    - plugins/your-plugin-name/tests/**
    - plugins/your-plugin-name/codeception.dist.yml
    - plugins/wp-graphql/**/*.php  # If plugin depends on wp-graphql
    - package.json
    - package-lock.json
    - .wp-env.json
    - bin/**
```

2. **Add test job** that calls the reusable workflow:

```yaml
your-plugin-name:
  name: "Integration: ${{ matrix.name }}"
  needs: detect-changes
  if: needs.detect-changes.outputs.your-plugin-name == 'true' || github.event_name == 'workflow_dispatch'
  strategy:
    fail-fast: false
    matrix:
      include:
        # Define your test matrix (see Test Matrix Strategy below)
  uses: ./.github/workflows/integration-tests-reusable.yml
  secrets: inherit
  with:
    name: ${{ matrix.name }}
    plugin_path: plugins/your-plugin-name
    plugin_name: your-plugin-name
    wp: ${{ matrix.wp }}
    php: ${{ matrix.php }}
    theme: ${{ matrix.theme }}
    multisite: ${{ matrix.multisite }}
    coverage: ${{ matrix.coverage }}
    experimental: ${{ matrix.experimental || false }}
```

**Note:** For Release PRs (branches starting with `release-please--`), all tests run for all plugins to ensure compatibility.

### 6. Test Setup

**Codeception Configuration:**

1. Create `codeception.dist.yml` in your plugin directory (see `plugins/wp-graphql/codeception.dist.yml` as a reference)
2. Create test suite files (`wpunit.suite.yml`, `acceptance.suite.yml`, `functional.suite.yml`)
3. Use the shared bootstrap file:

```php
// In tests/acceptance/bootstrap.php or tests/functional/bootstrap.php
<?php
// Load common bootstrap from wp-graphql plugin (shared across monorepo)
require_once dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-graphql/tests/bootstrap-common.php';
```

**Test Scripts in `package.json`:**

Add test scripts that export required environment variables:

```json
{
  "scripts": {
    "test:codecept:wpunit": "npm run --prefix ../.. wp-env -- run tests-cli --env-cwd=wp-content/plugins/your-plugin-name/ -- bash -c 'export TEST_DB_HOST=${WORDPRESS_DB_HOST:-tests-mysql} TEST_DB_NAME=${WORDPRESS_DB_NAME:-tests-wordpress} TEST_DB_USER=${WORDPRESS_DB_USER:-root} TEST_DB_PASSWORD=${WORDPRESS_DB_PASSWORD:-password} TEST_WP_TABLE_PREFIX=${TEST_WP_TABLE_PREFIX:-wp_} TEST_WP_DOMAIN=${TEST_WP_DOMAIN:-localhost} TEST_ADMIN_EMAIL=${TEST_ADMIN_EMAIL:-admin@example.org} TEST_WP_ROOT_FOLDER=${TEST_WP_ROOT_FOLDER:-/var/www/html} TEST_THEME=${TEST_THEME:-twentytwentyone} && vendor/bin/codecept run wpunit \"$@\"' --"
  }
}
```

### 7. UpdatesTest Filtering (if needed)

If your plugin extends WPGraphQL and has a "Requires WPGraphQL" header, you may need to update `plugins/wp-graphql/tests/wpunit/UpdatesTest.php` to filter it out. The test currently uses a whitelist approach, keeping only explicitly created test plugins. If your plugin is being detected as an untested dependency, you may need to adjust the filter logic.

### Test Matrix Strategy

We use "boundary testing" rather than testing every PHP × WP combination:

- **Coverage jobs**: Latest WP/PHP with all theme × multisite combinations
- **Boundary jobs**: Current stable, mid-range, oldest supported versions
- **Experimental**: WordPress trunk (allowed to fail)

If tests pass at version boundaries, they almost certainly pass for versions in between.

### Test Matrix Strategy

We use "boundary testing" rather than testing every PHP × WP combination:

- **Coverage jobs**: Latest WP/PHP with all theme × multisite combinations
- **Boundary jobs**: Current stable, mid-range, oldest supported versions
- **Experimental**: WordPress trunk (allowed to fail)

If tests pass at version boundaries, they almost certainly pass for versions in between.

## Resources

- [WPGraphQL Documentation](https://www.wpgraphql.com/docs)
- [GraphQL Specification](https://spec.graphql.org/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Turborepo Documentation](https://turbo.build/repo/docs)
- [@wordpress/env Documentation](https://www.npmjs.com/package/@wordpress/env)
