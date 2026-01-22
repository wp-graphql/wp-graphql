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
│   └── wp-graphql/           # WPGraphQL core plugin
│       ├── src/              # PHP source code
│       ├── tests/            # Test suites
│       ├── packages/         # JavaScript packages (GraphiQL)
│       ├── docs/             # User documentation (→ wpgraphql.com)
│       └── package.json      # Plugin-specific npm config
├── bin/
│   ├── after-start.sh        # Environment setup script (runs outside containers)
│   └── setup.sh              # Plugin setup script (runs inside containers via after-start.sh)
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

## Custom Environment Configuration

Create a `.wp-env.override.json` file in the root to customize the environment:

```json
{
  "core": "WordPress/WordPress#6.5",
  "phpVersion": "8.1",
  "plugins": ["./plugins/wp-graphql", "./path/to/another/plugin"]
}
```

See the [@wordpress/env documentation](https://www.npmjs.com/package/@wordpress/env) for all options.

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
