# Architecture Overview

This document describes the architecture of the WPGraphQL monorepo.

## Monorepo Structure

```
wp-graphql/
├── plugins/                    # WordPress plugins
│   ├── wp-graphql/            # WPGraphQL core plugin
│   ├── wp-graphql-smart-cache/ # Smart Cache extension plugin
│   └── wp-graphql-ide/        # IDE extension plugin
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

**Note on Naming Conventions:**
- **Directory names** should match the core `wp-graphql` convention (use hyphens, e.g., `wp-graphql-smart-cache`)
- **WordPress.org slugs** may differ if WordPress.org policy requires it (e.g., `wpgraphql-smart-cache` without hyphen)
- The build process handles the mapping automatically - see [Plugin Naming Conventions](../.github/workflows/README.md#plugin-naming-conventions) for details

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

### 4. Version Constants Script

**Update `scripts/update-version-constants.js`:**

If your plugin defines a version constant (e.g., `define( 'YOUR_PLUGIN_VERSION', '1.0.0' );`), add a mapping in the `getConstantMapping()` function:

```javascript
function getConstantMapping(component) {
	const mappings = {
		'wp-graphql': {
			constantName: 'WPGRAPHQL_VERSION',
			fileName: 'constants.php',
		},
		'wp-graphql-smart-cache': {
			constantName: 'WPGRAPHQL_SMART_CACHE_VERSION',
			fileName: 'wp-graphql-smart-cache.php',
		},
		'wp-graphql-ide': {
			constantName: 'WPGRAPHQL_IDE_VERSION',
			fileName: 'wpgraphql-ide.php',
		},
		'your-plugin-name': {
			constantName: 'YOUR_PLUGIN_VERSION',
			fileName: 'your-plugin-name.php', // or constants.php if separate file
		},
	};
	return mappings[component] || null;
}
```

This ensures that version constants are automatically updated during the release PR process. The script handles both hardcoded versions and `x-release-please-version` placeholders.

**Note:** If your plugin doesn't use a version constant, you can skip this step. The script will gracefully handle missing mappings.

### 5. WordPress Environment

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

### 6. Integration Tests

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

### 7. Smoke Tests

**Add to `.github/workflows/smoke-test.yml`:**

1. **Add change detection pattern** in the `detect-changes` job (similar to integration tests)

2. **Add smoke test job** that calls the reusable workflow:

```yaml
your-plugin-name:
  name: 'Smoke Test: ${{ matrix.name }}'
  needs: detect-changes
  if: needs.detect-changes.outputs.your-plugin-name == 'true' || needs.detect-changes.outputs.test_all == 'true'
  strategy:
    fail-fast: false
    matrix:
      include:
        - name: 'your-plugin-name / WP 6.8 / PHP 8.3'
          wp: '6.8'
          php: '8.3'
        - name: 'your-plugin-name / WP 6.1 / PHP 7.4'
          wp: '6.1'
          php: '7.4'
  uses: ./.github/workflows/smoke-test-reusable.yml
  with:
    name: ${{ matrix.name }}
    plugin_path: plugins/your-plugin-name
    plugin_name: your-plugin-name
    composer_working_dir: plugins/your-plugin-name
    zip_name: wpgraphql-your-plugin.zip  # WordPress.org-compliant name (no hyphen between wp and graphql)
    plugin_slug: wpgraphql-your-plugin  # Must match directory name inside zip
    requires_wp_graphql: true  # Set to false if plugin doesn't depend on wp-graphql
    smoke_test_script: bin/smoke-test.sh
    needs_build: true  # Set to false if plugin has no JS assets
    wp: ${{ matrix.wp }}
    php: ${{ matrix.php }}
```

**⚠️ Critical: Zip Build Script Configuration**

The `composer.json` zip script must create a directory with the **WordPress.org slug name** (not the directory name). This is because WordPress uses the directory name inside the zip as the plugin identifier.

**Example for a plugin that needs WordPress.org-compliant naming:**

```json
{
  "scripts": {
    "zip": [
      "# Note: WordPress.org requires 'wpgraphql-your-plugin' (no hyphen between wp and graphql)",
      "# We keep the directory name as 'wp-graphql-your-plugin' to match core 'wp-graphql' convention",
      "# but the zip must use the WordPress.org-compliant slug for deployment",
      "mkdir -p ../../plugin-build/wpgraphql-your-plugin",
      "rsync -rc --exclude-from=.distignore --exclude=plugin-build . ../../plugin-build/wpgraphql-your-plugin/ --delete --delete-excluded -v",
      "cd ../../plugin-build ; zip -r wpgraphql-your-plugin.zip wpgraphql-your-plugin",
      "rm -rf ../../plugin-build/wpgraphql-your-plugin/"
    ]
  }
}
```

**Important points:**
- The directory created inside the zip (`wpgraphql-your-plugin`) must match the `plugin_slug` in the smoke test workflow
- The zip filename (`wpgraphql-your-plugin.zip`) should also use the WordPress.org slug
- WordPress uses the directory name inside the zip to identify the plugin when checking if it's active
- See [Plugin Naming Conventions](../.github/workflows/README.md#plugin-naming-conventions) for more details

### 8. Test Setup

**⚠️ Important:** The CI workflow expects all plugins to have test suite configurations and npm scripts, even if you don't have tests yet. Codeception will run successfully with no tests (reporting "No tests executed"), but it will fail if the suite files or scripts are missing.

**Codeception Configuration:**

1. Create `codeception.dist.yml` in your plugin directory (see `plugins/wp-graphql/codeception.dist.yml` as a reference)

2. **Create all three test suite files** (even if you don't have tests yet):
   - `tests/wpunit.suite.yml` - For unit/integration tests
   - `tests/acceptance.suite.yml` - For acceptance tests (see `plugins/wp-graphql-smart-cache/tests/acceptance.suite.yml` as reference)
   - `tests/functional.suite.yml` - For functional tests (see `plugins/wp-graphql-smart-cache/tests/functional.suite.yml` as reference)

3. **Create bootstrap files** for acceptance and functional tests:

```php
// In tests/acceptance/bootstrap.php
<?php
/**
 * Bootstrap file for acceptance tests.
 *
 * @package YourPlugin\Tests\Acceptance
 */

// Load common bootstrap from wp-graphql plugin (shared across monorepo)
// Path: plugins/your-plugin-name/tests/acceptance -> plugins/wp-graphql/tests
require_once dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-graphql/tests/bootstrap-common.php';
```

```php
// In tests/functional/bootstrap.php
<?php
/**
 * Bootstrap file for functional tests.
 *
 * @package YourPlugin\Tests\Functional
 */

// Load common bootstrap from wp-graphql plugin (shared across monorepo)
// Path: plugins/your-plugin-name/tests/functional -> plugins/wp-graphql/tests
require_once dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-graphql/tests/bootstrap-common.php';
```

**Test Scripts in `package.json`:**

**⚠️ Required:** You must add all three test scripts to your `package.json`, even if you don't have tests yet. The CI workflow calls these scripts and will fail if they're missing.

Add these test scripts that export required environment variables:

```json
{
  "scripts": {
    "test:codecept:wpunit": "npm run --prefix ../.. wp-env -- run tests-cli --env-cwd=wp-content/plugins/your-plugin-name/ -- bash -c 'export TEST_DB_HOST=${WORDPRESS_DB_HOST:-tests-mysql} TEST_DB_NAME=${WORDPRESS_DB_NAME:-tests-wordpress} TEST_DB_USER=${WORDPRESS_DB_USER:-root} TEST_DB_PASSWORD=${WORDPRESS_DB_PASSWORD:-password} TEST_WP_TABLE_PREFIX=${TEST_WP_TABLE_PREFIX:-wp_} TEST_WP_DOMAIN=${TEST_WP_DOMAIN:-localhost} TEST_ADMIN_EMAIL=${TEST_ADMIN_EMAIL:-admin@example.org} TEST_WP_ROOT_FOLDER=${TEST_WP_ROOT_FOLDER:-/var/www/html} TEST_THEME=${TEST_THEME:-twentytwentyone} && vendor/bin/codecept run wpunit \"$@\"' --"
  }
}
```

**Note:** The example above shows only `test:codecept:wpunit`. You must also add `test:codecept:acceptance` and `test:codecept:functional` scripts. See the reference examples below for complete implementations.

**Reference Examples:**

- See `plugins/wp-graphql-smart-cache/package.json` for complete script examples
- See `plugins/wp-graphql-ide/tests/` for suite file examples
- See `plugins/wp-graphql-smart-cache/tests/` for bootstrap file examples

### 9. UpdatesTest Filtering (if needed)

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
