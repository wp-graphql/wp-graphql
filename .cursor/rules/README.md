# WPGraphQL Cursor Rules

This directory contains project-specific rules for Cursor, an AI-powered code editor. These rules help the AI understand the structure and patterns of the WPGraphQL monorepo codebase.

## Monorepo Structure

This repository is a monorepo containing multiple WordPress plugins in the `plugins/` directory:
- **WPGraphQL** (core): The main GraphQL API plugin
- **WPGraphQL Smart Cache**: Caching and cache invalidation extension
- **Future plugins**: WPGraphQL IDE, WPGraphQL for ACF, and others

File patterns in the rules are relative to the repository root. For example, `plugins/wp-graphql/src/**/*.php` refers to the core plugin's source code.

## Rule Files

- **wpgraphql.json**: The main rule file that contains information about the WPGraphQL monorepo, including frameworks, key concepts, file patterns, dependencies, development workflows, and more.
- **php_files.json**: PHP-specific coding standards and conventions that apply to all plugins in the monorepo.

## How These Rules Help

These rules provide context to the AI when working with the WPGraphQL monorepo, enabling it to:

1. Understand the monorepo structure and architecture
2. Navigate between different plugins in the `plugins/` directory
3. Recognize common code patterns across plugins
4. Identify key files and directories (with correct paths relative to repository root)
5. Understand development workflows (conventional commits, testing, building)
6. Provide accurate commands for npm workspaces and Turborepo
7. Understand the purpose of different components across the ecosystem

## For Contributors

If you're contributing to any plugin in the WPGraphQL monorepo and using Cursor, these rules will automatically be applied when you open the project in Cursor. This ensures that all contributors have the same context when working with the codebase, regardless of which plugin they're working on.

The rules apply to all plugins in the monorepo, providing consistent guidance whether you're working on the core WPGraphQL plugin, WPGraphQL Smart Cache, or any future plugins.

## Updating Rules

If you need to update these rules, please modify the appropriate JSON file in this directory. Make sure to follow the existing structure and format. Remember that file patterns should be relative to the repository root (e.g., `plugins/wp-graphql/src/**/*.php`).

## Learn More

For more information about Cursor rules, visit the [Cursor documentation](https://docs.cursor.com/context/rules-for-ai).

For information about the monorepo structure, see [docs/ARCHITECTURE.md](../../docs/ARCHITECTURE.md) in the repository.
