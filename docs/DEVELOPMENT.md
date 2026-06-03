# Development Setup

This guide covers setting up the WPGraphQL monorepo for local development.

## Prerequisites

- **Node.js 22+** and npm 10+ ([nvm](https://github.com/nvm-sh/nvm) recommended)
- **Docker** (for wp-env)
- **Git**
- **PHP 7.4+** and Composer (optional, for local PHP tooling)

## Quick Start

```bash
# Clone the repository
git clone git@github.com:wp-graphql/wp-graphql.git
cd wp-graphql

# Use correct Node version (if using nvm)
nvm install && nvm use

# Install dependencies
npm install

# Start WordPress environment
npm run wp-env start
```

The WordPress site will be available at:
- **Development site**: http://localhost:8888
- **WP Admin**: http://localhost:8888/wp-admin (user: `admin`, pass: `password`)
- **Test site**: http://localhost:8889

## Repository Structure

```
wp-graphql/
├── plugins/
│   ├── wp-graphql/           # WPGraphQL core plugin
│   ├── wp-graphql-ide/       # IDE extension plugin
│   ├── wp-graphql-smart-cache/ # Smart Cache extension plugin
│   └── wp-graphql-acf/       # ACF extension plugin
├── bin/
│   └── setup-wp-env.sh       # Shared environment setup script
├── docs/                     # Contributor documentation (this folder)
├── .wp-env.json              # WordPress environment config
├── package.json              # Root workspace config
└── turbo.json                # Turborepo build config
```

## npm Workspaces

This monorepo uses [npm workspaces](https://docs.npmjs.com/cli/v10/using-npm/workspaces). All plugins are in the `plugins/` directory.

```bash
# Run a command in a specific workspace
npm run -w @wpgraphql/wp-graphql <script>

# Example: Run tests for wp-graphql
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit

# Install a dependency to a specific workspace
npm install <package> -w @wpgraphql/wp-graphql
```

## Environment Commands

```bash
# Start the WordPress environment
npm run wp-env start

# Start with XDebug enabled
npm run wp-env start -- --xdebug

# Stop the environment
npm run wp-env stop

# Destroy the environment (removes all data)
npm run wp-env destroy

# Run WP-CLI commands
npm run wp-env run cli -- wp <command>

# Run commands in the test environment
npm run wp-env run tests-cli -- wp <command>
```

## Installing Composer Dependencies

Composer dependencies are installed inside the Docker container automatically when `wp-env` starts. To manually install:

```bash
# Install Composer deps in the tests container
npm run wp-env -- run tests-cli --env-cwd=wp-content/plugins/wp-graphql/ -- composer install
```

## Running Tests

See the [Testing Guide](./TESTING.md) for detailed instructions.

```bash
# Run all WPUnit tests
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit

# Run a specific test file
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit -- tests/wpunit/PostObjectQueriesTest.php

# Run acceptance tests
npm run -w @wpgraphql/wp-graphql test:codecept:acceptance

# Run functional tests
npm run -w @wpgraphql/wp-graphql test:codecept:functional
```

## Building Assets

```bash
# Build all workspaces
npm run build

# Build a specific workspace
npm run -w @wpgraphql/wp-graphql build
```

## Internationalization (i18n)

WPGraphQL supports internationalization. Translation files are in `plugins/wp-graphql/languages/`.

```bash
# Regenerate the POT file (extracts translatable strings from PHP)
npm run -w @wpgraphql/wp-graphql i18n:pot

# Generate JSON translation files for JavaScript (from PO files)
npm run -w @wpgraphql/wp-graphql i18n:json
```

The POT file is automatically regenerated during releases via the `update-release-pr` workflow.

## Custom Environment Configuration

Create a `.wp-env.override.json` file in the root to customize the environment per-developer. The override file is gitignored, so changes never affect other contributors. wp-env merges it on top of the shared `.wp-env.json` at startup.

```json
{
  "core": "WordPress/WordPress#6.5",
  "phpVersion": "8.1",
  "plugins": ["./plugins/wp-graphql", "./path/to/another/plugin"]
}
```

See the [@wordpress/env documentation](https://www.npmjs.com/package/@wordpress/env) for all options.

### Resolving port conflicts

WPGraphQL follows the WordPress convention of running the dev site on `:8888` and the test site on `:8889`. Several other WordPress projects use the same defaults — WordPress core's [develop.git](https://github.com/WordPress/wordpress-develop) Docker setup, the @wordpress/scripts Playwright runner, Local by Flywheel exports, and so on. If you work on more than one of those at the same time, `npm run wp-env start` will fail with `port is already allocated`.

Don't change the shared `.wp-env.json` — moving the ports universally fights `@wordpress/scripts`'s hardcoded `localhost:8889` baseURL and forces every contributor (and CI) to drift in lockstep. Instead, pick non-default ports in your local override:

```json
{
  "port": 8898,
  "testsPort": 8899
}
```

Restart the env after editing (`npm run wp-env stop && npm run wp-env start`). When running Playwright E2E tests against the moved test site, override the baseURL too:

```bash
WP_BASE_URL=http://localhost:8899 npm run -w @wpgraphql/wp-graphql test:e2e
```

The override file is the right tool for this — it's `.wp-env`'s designed escape hatch for per-machine collisions, and it keeps every other contributor on convention.

## XDebug Setup

XDebug is available in the wp-env environment. To enable:

```bash
npm run wp-env start -- --xdebug
```

### VS Code Configuration

Create `.vscode/launch.json`:

```json
{
  "version": "0.2.0",
  "configurations": [
    {
      "name": "Listen for Xdebug",
      "type": "php",
      "request": "launch",
      "port": 9003,
      "pathMappings": {
        "/var/www/html/wp-content/plugins/wp-graphql": "${workspaceFolder}/plugins/wp-graphql"
      }
    }
  ]
}
```

## Troubleshooting

### Docker Issues

```bash
# Reset everything
npm run wp-env destroy
npm run wp-env start
```

### Composer Dependencies Missing

```bash
npm run wp-env -- run tests-cli --env-cwd=wp-content/plugins/wp-graphql/ -- composer install
```

### Tests Fail with Theme Error

Ensure the test theme is set:

```bash
TEST_THEME=twentytwentyone npm run -w @wpgraphql/wp-graphql test:codecept:wpunit
```

## Next Steps

- [Contributing Guide](./CONTRIBUTING.md) - How to contribute to WPGraphQL
- [Testing Guide](./TESTING.md) - Detailed testing instructions
- [Architecture](./ARCHITECTURE.md) - Understanding the codebase
