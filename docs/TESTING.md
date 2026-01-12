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

# With code coverage enabled
npm run wp-env start -- --xdebug=coverage

# With debugging enabled
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

### E2E Tests (GraphiQL)

Playwright-based browser tests:

```bash
npm run -w @wpgraphql/wp-graphql test:e2e
```

## Code Coverage

Generate code coverage reports:

```bash
# Start with coverage enabled
npm run wp-env start -- --xdebug=coverage

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

## CI/CD Testing

Tests run automatically on:
- Pull requests to `develop` or `master`
- Pushes to `develop` or `master`

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
