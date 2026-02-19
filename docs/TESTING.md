# Testing Guide

This guide covers how to run tests for plugins in the WPGraphQL monorepo.

## Quick Start

```bash
# Start the test environment
npm run wp-env start

# Run WPUnit tests for wp-graphql
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit
```

## Test Environment

Tests run inside Docker containers managed by `wp-env`. The test environment includes:

- WordPress installation at `http://localhost:8889`
- MySQL database
- PHP with Xdebug (optional)
- All plugins in `plugins/` directory

### Starting the Environment

```bash
# Standard start
npm run wp-env start

# With PCOV code coverage enabled
PCOV_ENABLED=1 npm run wp-env start

# With Xdebug debugging enabled
npm run wp-env start -- --xdebug
```

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `TEST_THEME` | WordPress theme for tests | `twentytwentyone` |
| `MULTISITE` | Run as multisite | `false` |
| `WP_ENV_PHP_VERSION` | PHP version | `8.2` |

Example:
```bash
TEST_THEME=twentytwentyfive npm run -w @wpgraphql/wp-graphql test:codecept:wpunit
```

## Test Suites

### WPUnit Tests

Integration tests that run within WordPress:

```bash
# Run all WPUnit tests
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit

# Run a specific test file
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit -- tests/wpunit/PostObjectQueriesTest.php

# Run a specific test method
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit -- tests/wpunit/PostObjectQueriesTest.php:testPostQuery

# Run with verbose output
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit -- --debug
```

### Acceptance Tests

End-to-end tests via HTTP:

```bash
npm run -w @wpgraphql/wp-graphql test:codecept:acceptance
```

### Functional Tests

Tests that interact with WordPress functions:

```bash
npm run -w @wpgraphql/wp-graphql test:codecept:functional
```

## Testing wp-graphql-acf

The `wp-graphql-acf` plugin requires Advanced Custom Fields (ACF) to be installed for tests to run. ACF Extended is optional and is only used with ACF Pro (not with ACF Free).

### Option 1: Install ACF Free (Recommended for Local Development)

```bash
# From repo root: install ACF Free only (no ACF Extended)
cd plugins/wp-graphql-acf && npm run install-test-deps && cd ../..

# Or use the alias
npm run -w @wpgraphql/wp-graphql-acf install-acf

# Then run tests
npm run -w @wpgraphql/wp-graphql-acf test:codecept:wpunit
```

### Option 2: Install ACF Pro (Requires License Key)

You can provide the license key in three ways:

**Option A: Using a .env file (Recommended)**
```bash
# Create or edit plugins/wp-graphql-acf/.env
echo "ACF_LICENSE_KEY=your_license_key_here" >> plugins/wp-graphql-acf/.env

# Install ACF Pro + ACF Extended Free
npm run -w @wpgraphql/wp-graphql-acf install-acf:pro
```

**Option B: Environment variable (session)**
```bash
export ACF_LICENSE_KEY=your_license_key_here
npm run -w @wpgraphql/wp-graphql-acf install-acf:pro
```

**Option C: Inline (one-time use)**
```bash
ACF_LICENSE_KEY=your_license_key_here npm run -w @wpgraphql/wp-graphql-acf install-acf:pro
```

Then run tests:
```bash
npm run -w @wpgraphql/wp-graphql-acf test:codecept:wpunit
```

### Option 3: Install ACF Pro + ACF Extended Pro (Requires Both License Keys)

**Using a .env file (Recommended):**
```bash
# Create or edit plugins/wp-graphql-acf/.env
cat >> plugins/wp-graphql-acf/.env << EOF
ACF_LICENSE_KEY=your_acf_pro_license_key
ACF_EXTENDED_LICENSE_KEY=your_acf_extended_pro_license_key
EOF

# Install ACF Pro + ACF Extended Pro
npm run -w @wpgraphql/wp-graphql-acf install-acf:pro-extended
```

**Or using environment variables:**
```bash
export ACF_LICENSE_KEY=your_acf_pro_license_key
export ACF_EXTENDED_LICENSE_KEY=your_acf_extended_pro_license_key
npm run -w @wpgraphql/wp-graphql-acf install-acf:pro-extended
```

Then run tests:
```bash
npm run -w @wpgraphql/wp-graphql-acf test:codecept:wpunit
```

**Note:** For local development, you can use ACF Free (Option 1) which doesn't require any license keys. The `.env` file is gitignored, so your license keys won't be committed. ACF Extended is only installed when using ACF Pro (Options 2 or 3).

### Manual Installation

You can also install ACF plugins manually via WP-CLI:

```bash
# Install ACF Free only (no ACF Extended with ACF Free)
npm run wp-env run tests-cli -- wp plugin install advanced-custom-fields --activate --allow-root

# With ACF Pro: install ACF Pro first, then optionally ACF Extended Free or Pro
export ACF_LICENSE_KEY=your_license_key
npm run wp-env run tests-cli -- wp plugin install "https://connect.advancedcustomfields.com/v2/plugins/download?p=pro&k=${ACF_LICENSE_KEY}" --activate --allow-root
# Then: npm run wp-env run tests-cli -- wp plugin install acf-extended --activate --allow-root  # Extended Free
```

**Note:** ACF plugins only need to be installed once. They will persist in your wp-env environment until you destroy it (`npm run wp-env destroy`).

### E2E Tests (Playwright)

wp-graphql-acf has Playwright E2E tests. Install ACF first (e.g. `npm run install-test-deps` from `plugins/wp-graphql-acf`), then run:

```bash
npm run -w @wpgraphql/wp-graphql-acf test:e2e
```

Tests skip Pro-only or ACF Extended–only specs when running with ACF Free (set `INSTALL_ACF_PRO` / `INSTALL_ACF_EXTENDED_PRO` to match your install so skips match CI).

**Local-only: full CI-like run** (build, wp-env, install ACF, run E2E, stop) from repo root:

```bash
./bin/run-acf-e2e-local.sh
# Or with a specific ACF variant:
INSTALL_ACF_PRO=false INSTALL_ACF_EXTENDED_PRO=false ./bin/run-acf-e2e-local.sh   # ACF Free
```

### E2E Tests (GraphiQL – wp-graphql plugin)

Playwright-based browser tests for the core plugin:

```bash
npm run -w @wpgraphql/wp-graphql test:e2e
```

## Code Coverage

Generate code coverage reports:

```bash
# Start with coverage enabled
PCOV_ENABLED=1 npm run wp-env start

# Run tests with coverage
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit -- --coverage --coverage-xml

# Coverage report will be in plugins/wp-graphql/tests/_output/
```

## Writing Tests

### Test File Location

Tests are organized by type in `plugins/wp-graphql/tests/`:

```
tests/
├── wpunit/          # WordPress unit/integration tests
├── acceptance/      # HTTP acceptance tests
├── functional/      # Functional tests
└── e2e/             # End-to-end browser tests
```

### WPUnit Test Structure

```php
<?php

class MyFeatureTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

    public function setUp(): void {
        parent::setUp();
        // Test setup
    }

    public function tearDown(): void {
        // Cleanup
        parent::tearDown();
    }

    public function testMyFeature(): void {
        // Create test data
        $post_id = $this->factory()->post->create([
            'post_title' => 'Test Post',
            'post_status' => 'publish',
        ]);

        // Execute GraphQL query
        $query = '
            query GetPost($id: ID!) {
                post(id: $id, idType: DATABASE_ID) {
                    title
                }
            }
        ';

        $response = $this->graphql([
            'query' => $query,
            'variables' => ['id' => $post_id],
        ]);

        // Assert results
        $this->assertQuerySuccessful($response, [
            $this->expectedField('post.title', 'Test Post'),
        ]);
    }
}
```

### Test Helpers

The `WPGraphQLTestCase` base class provides helpers:

```php
// Execute a GraphQL query
$response = $this->graphql(['query' => $query]);

// Assert successful response
$this->assertQuerySuccessful($response, [
    $this->expectedField('post.title', 'Test Post'),
]);

// Assert error response
$this->assertQueryError($response);

// Create test users
$admin_id = $this->factory()->user->create(['role' => 'administrator']);

// Set current user
wp_set_current_user($admin_id);
```

## Smoke Tests

Smoke tests validate that the production plugin zip works correctly. These are lightweight tests that verify core functionality without running the full test suite.

### Running Smoke Tests Locally

For basic local testing (plugin already installed via wp-env):

```bash
# Start your WordPress environment
npm run wp-env start

# Run smoke tests against the local environment
./bin/smoke-test.sh

# Or specify a custom endpoint
./bin/smoke-test.sh --endpoint http://wpgraphql.local/graphql

# With verbose output (shows full responses)
./bin/smoke-test.sh --verbose
```

### Testing the Production Zip Artifact

To test the actual production zip (mimicking what CI does):

```bash
# 1. Build the zip
cd plugins/wp-graphql && composer run-script zip && cd ../..

# 2. Start wp-env with a clean config (no plugins pre-installed)
cat > .wp-env.override.json << 'EOF'
{
  "plugins": [],
  "lifecycleScripts": { "afterStart": null }
}
EOF
npm run wp-env start

# 3. Copy zip to container and install via WP-CLI
CONTAINER=$(docker ps --format '{{.Names}}' | grep 'cli-1' | grep -v 'tests' | head -1)
docker cp plugin-build/wp-graphql.zip "$CONTAINER":/tmp/wp-graphql.zip
npm run wp-env -- run cli wp plugin install /tmp/wp-graphql.zip --activate

# 4. Flush permalinks and enable introspection
npm run wp-env -- run cli wp rewrite flush --hard
npm run wp-env -- run cli wp option update graphql_general_settings \
  '{"graphql_endpoint":"graphql","public_introspection_enabled":"on"}' --format=json

# 5. Run smoke tests
./bin/smoke-test.sh

# 6. Clean up override file when done
rm .wp-env.override.json
```

### What Smoke Tests Verify

| Test | Description |
|------|-------------|
| GraphQL endpoint | Basic connectivity check |
| Introspection | Schema introspection works |
| Posts query | Can query posts |
| Pages query | Can query pages |
| Users query | Can query users |
| GeneralSettings | Can query site settings |
| ContentTypes | Can query content types |
| Taxonomies | Can query taxonomies |
| Menus | Can query menus (empty is OK) |
| MediaItems | Can query media items |

### CI Smoke Tests

The `smoke-test.yml` workflow:
1. Builds the production plugin zip
2. Starts a clean WordPress environment (no plugins pre-installed)
3. Installs the plugin from the zip via WP-CLI
4. Flushes permalinks and enables public introspection
5. Runs all smoke tests

This catches issues that unit tests might miss:
- Missing files from `.distignore`
- Build/bundling problems
- Activation errors
- Missing production dependencies

## CI/CD Testing

Tests run automatically on:
- Pull requests to `main`
- Pushes to `main`

### Test Matrix

The CI runs tests across multiple configurations:

| WordPress | PHP | Theme | Multisite |
|-----------|-----|-------|-----------|
| 6.9 | 8.4 | twentytwentyfive | No |
| 6.9 | 8.4 | twentytwentyfive | Yes |
| 6.9 | 8.4 | twentytwentyone | No |
| 6.9 | 8.4 | twentytwentyone | Yes |
| 6.5 | 8.1 | twentytwentyone | No |
| 6.1 | 7.4 | twentytwentyone | No |
| trunk | 8.4 | twentytwentyfive | No |

## Troubleshooting

### Tests Won't Run

**Composer dependencies missing:**
```bash
npm run wp-env -- run tests-cli --env-cwd=wp-content/plugins/wp-graphql/ -- composer install
```

**Docker not running:**
```bash
# Start Docker, then:
npm run wp-env start
```

**Environment corrupted:**
```bash
npm run wp-env destroy
npm run wp-env start
```

### Test Theme Errors

If tests fail with theme errors:

```bash
# Activate the test theme
npm run wp-env run tests-cli -- wp theme activate twentytwentyone

# Or specify the theme when running tests
TEST_THEME=twentytwentyone npm run -w @wpgraphql/wp-graphql test:codecept:wpunit
```

### Database Issues

```bash
# Reset the test database
npm run wp-env run tests-cli -- wp db reset --yes
```

### Slow Tests

If tests are running slowly:

1. Ensure Docker has sufficient resources allocated
2. Use `--group` to run specific test groups
3. Run individual test files instead of full suite

## Local Testing (Without Docker)

For performance or other reasons, you can run tests directly on your machine:

### Prerequisites

- PHP 7.4+ with required extensions
- MySQL/MariaDB
- Composer

### Setup

1. Copy `.env.dist` to `.env` in `plugins/wp-graphql/`
2. Update database credentials
3. Run: `composer -d plugins/wp-graphql install-test-env`
4. Copy test config: `cp tests/wpunit.suite.dist.yml tests/wpunit.suite.yml`
5. Update `tests/wpunit.suite.yml` with your database details

### Running

```bash
cd plugins/wp-graphql
vendor/bin/codecept run wpunit
```
