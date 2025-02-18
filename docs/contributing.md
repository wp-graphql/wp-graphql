---
uri: "/docs/contributing/"
title: "Contributing"
---

This document will be most useful for developers that want to contribute to WPGraphQL and want to run the docker container locally as well as utilize xdebug for debugging and tracing.

## Requirements

- Node.js >= 20 (LTS)
- npm >= 8
- Docker
- Composer
- PHP 7.4+

> **Note**: WPGraphQL uses Node.js for development tooling, including testing, changelog generation, and release automation.

## Development Workflow

WPGraphQL uses several automated processes to maintain consistency and quality:

1. **Conventional Commits**
   - PR titles must follow the format (e.g., `feat:`, `fix:`)
   - Breaking changes use `!` suffix (e.g., `feat!:`)
   - See [Conventional Commits](https://www.conventionalcommits.org/) for more details

2. **Automated Changesets**
   - Generated automatically when PRs are merged
   - Based on PR title and description
   - Includes breaking changes and upgrade notes

3. **Version Management**
   - Automatically updates version numbers
   - Updates `@since todo` tags
   - Maintains changelog in multiple formats

4. **Testing**
   - Script tests for automation code
   - E2E tests for GraphiQL
   - Version management validation
   - Release simulation

In order to continue, you should follow steps to setup Docker running on your machine.

### Build the WordPress Site

The `app` docker image starts a running WordPress site with the local wp-graphql directory installed and activated. Local changes to the source code is immediately reflects in the app.

First step, clone the source for wp-graphql from github.

```shell
git clone git@github.com:wp-graphql/wp-graphql.git
```

Build the plugin and dependencies:

```shell
composer install
```

Or if you don't have composer installed or prefer building it in a docker instance:

```shell
docker run -v $PWD:/app composer --ignore-platform-reqs install
```

Build the app and testing docker images:

```shell
composer build-app
composer build-test
```

In one terminal window, start the WordPress app:

```shell
composer run-app
```

In your web browser, open the site, [http://localhost:8091]().  And the WP admin at [http://localhost:8091/wp-admin](). Username is `admin`. Password is `password`.

### Using XDebug

#### Local WordPress Site With XDebug

Use the environment variable USING\_XDEBUG to start the docker image and WordPress with xdebug configured to use port 9003 to communicated with your IDE.

```shell
export USING_XDEBUG=1
composer run-app
```

You should see output in the terminal like the following examples that indicate xdebug is indeed enabled and running in the app:

```shell
app_1      | Enabling XDebug 3
app_1      | [01-Apr-2021 04:43:53 UTC] Xdebug: [Step Debug] Could not connect to debugging client. Tried: host.docker.internal:9003 (through   xdebug.client_host/xdebug.client_port) :-(
```

Start your IDE, like VSCode. Enable xdebug and set breakpoints. Load pages in your browsers and you should experience the IDE pausing the page load and showing the breakpoint.

#### Using XDebug with unit Tests

See the testing page on running the unit test suite.  These instructions show how to enable xdebug for those unit tests and allow debugging in an IDE.

Use the environment variable USING\_XDEBUG to run tests with xdebug configured to use port 9003 to communicated with your IDE.

```shell
export USING_XDEBUG=1
composer run-tests
```

Use the environment variable SUITES to specify individual test files for quicker runs.

#### Configure VSCode IDE Launch File

Create or add the following configuration to your `.vscode/launch.json` in the root directory. Restart VSCode. Start the debug listener before running the app or testing images.

If you have WordPress core files in a directory for local development, you can add the location to the `pathMappings` for debug step through.

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "xdebugSettings": {
                "max_children": 128,
                "max_data": 1024,
                "max_depth": 3,
                "show_hidden": 1
            },
            "pathMappings": {
                "/var/www/html/wp-content/plugins/wp-graphql": "${workspaceFolder}",
                "/var/www/html": "${workspaceFolder}/wordpress",
            }
        }
    ]
}
```

## Changesets and Releases

WPGraphQL uses [changesets](../.changeset/README.md) to manage versioning and changelogs. When contributing:

1. Your PR title must follow [conventional commits](https://www.conventionalcommits.org/) format:
   - `feat:` for new features (minor version bump)
   - `fix:` for bug fixes (patch version bump)
   - Add `!` suffix for breaking changes: `feat!:` or `fix!:`

2. Include in your PR description:
   - Clear explanation of changes
   - Breaking changes (if any)
   - Upgrade instructions (if breaking)

3. Add `@since todo` tags to new functions/classes
   - These will be automatically updated during release

## Testing

### Script Tests
```shell
# Run tests for our automation scripts
npm run test:changesets
```

### E2E Tests
```shell
# Run GraphiQL E2E tests
npm run test:e2e
```

### Version Management
```shell
# Check current versions across files
npm run check-versions

# Simulate a release (dry-run)
npm run simulate-release 2.0.0

# Simulate a beta release
npm run simulate-release 2.0.0-beta.1 --beta
```

> **Note**: The simulation commands help test the release process without actually creating releases.