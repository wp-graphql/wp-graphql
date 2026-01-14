---
uri: "/docs/contributing/"
title: "Contributing"
---

This document will be most useful for developers that want to contribute to WPGraphQL and want to run the docker container locally as well as utilize xdebug for debugging and tracing.

> **Note**: WPGraphQL is now a monorepo. For detailed setup instructions, see the [Development Setup Guide](https://github.com/wp-graphql/wp-graphql/blob/develop/docs/DEVELOPMENT.md) in the repository root.

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

In order to continue, you should follow steps to setup your local development environment.

## Local Setup

### Prerequisites

Ensure you have the following installed on your local machine:
- Node.js 22+ and npm >= 10 (NVM recommended)
- Docker
- Git
- PHP 7.4+ and Composer (if you prefer to run the Composer tools locally)

You can use Docker and the `wp-env` tool to set up a local development environment, instead of manually installing the specific testing versions of WordPress, PHP, and Composer. For more information, see the [wp-env documentation](https://developer.wordpress.org/block-editor/packages/packages-env/).

### Installation

WPGraphQL uses [`@wordpress/env`](https://www.npmjs.com/package/@wordpress/env) to manage the local WordPress development and testing environment using Docker.

1. Clone the repository:

   ```shell
   git clone git@github.com:wp-graphql/wp-graphql.git
   cd wp-graphql
   ```

2. Install the NPM dependencies (from the repository root):

   ```shell
   ## If you're using nvm, make sure to use the correct Node.js version:
   nvm install && nvm use

   ## Then install the NPM dependencies:
   npm ci
   ```

3. Build the JavaScript assets (required for GraphiQL IDE and Extensions page):

   ```shell
   npm run build
   ```

   > **Note:** The `/build` directory is gitignored. You must run this step for the GraphiQL IDE to function. If you skip this step, you'll see a helpful message in the admin with instructions.

4. Start the `wp-env` environment to download and set up the Docker containers for WordPress:

   (If you're not using `wp-env` you can skip this step.)

   ```shell
   npm run wp-env start
   ```

   When finished, the WordPress development site will be available at http://localhost:8888 and the WP Admin Dashboard will be available at http://localhost:8888/wp-admin/. You can log in to the admin using the username `admin` and password `password`.

   Composer dependencies are automatically installed when the environment starts via the `afterStart` lifecycle script.

5. (Optional) Manually install Composer dependencies if needed:

   ```shell
   ## To install Composer dependencies inside the Docker container:
   npm run wp-env -- run tests-cli --env-cwd=wp-content/plugins/wp-graphql/ -- composer install

   ## Or: if you're running Composer locally:
   composer -d plugins/wp-graphql install
   ```

### Using XDebug

XDebug is installed via the `wp-env` environment, but is turned off by default. You can enable XDebug by passing the `--xdebug` flag when starting the `wp-env` environment.

```shell
# To turn it on:
npm run wp-env start -- --xdebug

# Or enable specific modes with a comma-separated list:
npm run wp-env start -- --profile,trace,debug
```
You can also connect your IDE to XDebug.

The following example is for Visual Studio Code (VSCode) using the [PHP Debug extension](https://marketplace.visualstudio.com/items?itemName=felixfbecker.php-debug)

Create or add the following configuration to your `.vscode/launch.json` in the repository root. Restart VSCode. Start the debug listener before running the app or testing images.

```jsonc
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
        "/var/www/html/wp-content/plugins/wp-graphql": "${workspaceFolder}/plugins/wp-graphql",
        // If you have WordPress core files in a directory for local development, you can add the location to the `pathMappings` for debug step through. For example:
        "/var/www/html": "/path/to/your/local/wordpress"
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
