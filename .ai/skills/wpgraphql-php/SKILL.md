---
name: wpgraphql-php
description: PHP coding standards and conventions for the WPGraphQL monorepo. Use when writing or editing PHP, running PHPCS or PHPStan, or adding deprecations and PHPDoc (@since next-version).
---

# wpgraphql-php

PHP coding standards and conventions for the WPGraphQL monorepo. Use when writing or editing PHP (core plugin or extensions).

## When to use this skill

- Writing or modifying PHP in `plugins/wp-graphql/` or other plugins.
- Running PHPCS or PHPStan; need correct paths and options.
- Adding deprecations or PHPDoc (e.g. `@since`).

## Standards summary

- **Coding standards**: WordPress Coding Standards (WPCS). See [.cursor/rules/php_files.json](.cursor/rules/php_files.json) for full rules.
- **PHP**: min 7.4; WordPress min 6.0. Indentation: tabs. Line length: 100. Text domain: `wp-graphql`.
- **Naming**: Classes PascalCase; methods/functions/variables snake_case; constants UPPER_SNAKE_CASE.
- **Arrays**: Short array syntax only.
- **Namespace**: `WPGraphQL\` for core; PSR-4, autoload via Composer. Sort `use` statements alphabetically; no leading backslash in `use`.

## Version and deprecation

- Use **`@since next-version`** in PHPDoc and in deprecation calls (e.g. `_deprecated_argument( __METHOD__, '@since next-version', 'Message.' );`). Do not use a literal version number for unreleased changes.
- Deprecation functions: `_deprecated_argument`, `_deprecated_function`, `_deprecated_file`, `_deprecated_hook`, `_deprecated_class`, `_deprecated_constructor`, `_doing_it_wrong`.

## Lint commands (use the workspace for the plugin you changed)

Use `-w @wpgraphql/wp-graphql` for core, `-w @wpgraphql/wp-graphql-acf` for ACF, etc. File paths are relative to that plugin’s directory (e.g. `plugins/wp-graphql/` or `plugins/wp-graphql-acf/`). Do not use repo-root paths. wp-env must be running.

**Core plugin (wp-graphql):**
```bash
# Check style
npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run check-cs -- src/Data/NodeResolver.php

# Fix style
npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run fix-cs -- src/Data/NodeResolver.php

# Static analysis (always use memory limit)
npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run phpstan -- --memory-limit=2G
```

**Other plugins:** Same pattern with that plugin’s workspace. Example for ACF: `npm run -w @wpgraphql/wp-graphql-acf wp-env:cli -- composer run check-cs -- src/Plugin.php`. See wpgraphql-dev-cycle for the full workspace list.

## Common patterns

- **Registration**: `register_graphql_type()`, `register_graphql_field()`, `register_graphql_connection()` from `access-functions.php`; hook into `graphql_register_types`.
- **Models**: Extend `WPGraphQL\Model\Model` for data access and authorization.
- **Resolvers**: Return data for GraphQL fields; often receive `$source`, `$args`, `$context`, `$info`.
