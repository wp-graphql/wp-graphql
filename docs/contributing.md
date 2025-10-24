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

> [!NOTE]
> WPGraphQL uses Node.js for development tooling, including testing, changelog generation, and release automation.

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
   - Updates `@since next-version` / `@next-version` tags to the appropriate version during the release process
   - Maintains changelog in multiple formats (CHANGELOG.md, readme.txt)

4. **Testing**
   - Script tests for automation code
   - E2E tests for GraphiQL
   - Version management validation
   - Release simulation

In order to continue, you should follow steps to setup Docker running on your machine.

### Build the WordPress Site

The `app` docker image starts a running WordPress site with the local wp-graphql directory installed and activated. Local changes to the source code are immediately reflected in the app.

First step, clone the source for wp-graphql from GitHub.

```shell
git clone git@github.com:wp-graphql/wp-graphql.git
```

Build the plugin and dependencies:

```shell
composer install
```

Or if you don't have Composer installed or prefer building it in a Docker instance:

```shell
docker run -v $PWD:/app composer --ignore-platform-reqs install
```

Build the app and testing Docker images:

```shell
composer build-app
composer build-test
```

In one terminal window, start the WordPress app:

```shell
composer run-app
```

In your web browser, open the site, [http://localhost:8091](). And the WP admin at [http://localhost:8091/wp-admin](). Username is `admin`. Password is `password`.

### Using XDebug

#### Local WordPress Site With XDebug

Use the environment variable `USING_XDEBUG` to start the Docker image and WordPress with XDebug configured to use port 9003 to communicate with your IDE.

```shell
export USING_XDEBUG=1
composer run-app
```

You should see output in the terminal like the following examples that indicate XDebug is indeed enabled and running in the app:

```shell
app_1      | Enabling XDebug 3
app_1      | [01-Apr-2021 04:43:53 UTC] Xdebug: [Step Debug] Could not connect to debugging client. Tried: host.docker.internal:9003 (through xdebug.client_host/xdebug.client_port) :-(
```

Start your IDE, like VSCode. Enable XDebug and set breakpoints. Load pages in your browsers and you should experience the IDE pausing the page load and showing the breakpoint.

#### Using XDebug with Unit Tests

See the testing page on running the unit test suite. These instructions show how to enable XDebug for those unit tests and allow debugging in an IDE.

Use the environment variable `USING_XDEBUG` to run tests with XDebug configured to use port 9003 to communicate with your IDE.

```shell
export USING_XDEBUG=1
composer run-tests
```

Use the environment variable `SUITES` to specify individual test files for quicker runs.

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
        "/var/www/html": "${workspaceFolder}/wordpress"
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

2. **Use the appropriate PR template**:

   - Go to [Create New Pull Request](https://github.com/wp-graphql/wp-graphql/compare)
   - The default template will show a "chooser" with links to specialized templates
   - Click the appropriate template link for your contribution type:
     - 🐛 **Bug Fixes** - For fixing bugs with failing test → fix → passing test workflow
     - ✨ **Features** - For implementing new WPGraphQL features  
     - 🧪 **Experiments** - For implementing or updating experiments
     - 📚 **Documentation** - For documentation improvements
     - 🔧 **Refactoring** - For code improvements without functional changes
     - 📦 **Dependencies** - For dependency updates and security fixes
     - 🛠️ **Maintenance** - For CI/CD, tooling, and configuration updates
   - Fill out the template with your contribution details

3. Include in your PR description:

   - Clear explanation of changes
   - Breaking changes (if any)
   - Upgrade instructions (if breaking)

4. Add `@since next-version` tags to new functions/classes docblocks or use `@next-version` as a version placeholder for deprecation functions

   - These will be automatically updated during the release process.

5. **Changeset Generation Process**:
   - When your PR is ready for review, a maintainer will review it
   - After approval, the maintainer will merge the PR
   - This triggers an automated workflow that:
     - Creates a changeset based on your PR title and description
     - Adds the changeset to the develop branch
     - Creates/updates a PR from the develop branch to master
   - When one or more changesets are collected, they'll be released together
   - Merging the PR containing multiple changesets will trigger a release workflow that will publish and deploy the plugin
