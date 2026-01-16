# Architecture Overview

This document describes the architecture of the WPGraphQL monorepo.

## Monorepo Structure

```
wp-graphql/
├── plugins/                    # WordPress plugins
│   └── wp-graphql/            # WPGraphQL core plugin
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
| `testing-integration.yml` | PR, Push | Run test suites |
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

When adding new plugins to the monorepo:

1. Create directory in `plugins/`
2. Add `package.json` with workspace name
3. Update `.wp-env.json` to include plugin
4. Add to CI test matrix if needed

Example for WPGraphQL Smart Cache:
```
plugins/
├── wp-graphql/
└── wpgraphql-smart-cache/
    ├── wpgraphql-smart-cache.php
    ├── src/
    ├── tests/
    ├── composer.json
    └── package.json
```

## Resources

- [WPGraphQL Documentation](https://www.wpgraphql.com/docs)
- [GraphQL Specification](https://spec.graphql.org/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Turborepo Documentation](https://turbo.build/repo/docs)
- [@wordpress/env Documentation](https://www.npmjs.com/package/@wordpress/env)
