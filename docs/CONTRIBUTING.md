# Contributing to WPGraphQL

Thank you for your interest in contributing to WPGraphQL! This guide covers how to contribute to any plugin in the monorepo.

## Getting Started

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone git@github.com:YOUR_USERNAME/wp-graphql.git
   cd wp-graphql
   ```
3. **Set up the development environment** (see [Development Setup](./DEVELOPMENT.md))
4. **Create a feature branch**:
   ```bash
   git checkout -b feat/your-feature-name
   ```

## Development Workflow

### 1. PR Titles and Conventional Commits

**PR titles** must follow [Conventional Commits](https://www.conventionalcommits.org/) format. This is important because:

1. PRs are **squash merged** - your PR title becomes the commit message
2. [release-please](https://github.com/googleapis/release-please) reads these commits to determine version bumps
3. Your PR title is validated by the `lint-pr.yml` workflow

| Prefix | Description | Version Bump |
|--------|-------------|--------------|
| `feat:` | New feature | Minor |
| `fix:` | Bug fix | Patch |
| `perf:` | Performance improvement | Patch |
| `docs:` | Documentation only | None |
| `refactor:` | Code change (no feature/fix) | None |
| `test:` | Adding/fixing tests | None |
| `chore:` | Maintenance tasks | None |
| `ci:` | CI/CD changes | None |
| `feat!:` | Breaking change (feature) | **Major** |
| `fix!:` | Breaking change (fix) | **Major** |
| `perf!:` | Breaking change (performance) | **Major** |

**Breaking Change Marker (`!`)**: The `!` suffix can only be used with `feat`, `fix`, or `perf` prefixes. Using `!` with other prefixes (like `chore!:` or `ci!:`) will fail CI validation since those types don't trigger releases.

> **⚠️ Breaking changes target the `next` branch, not `main`.** `main` is the current stable (2.x) release line — a breaking change merged there would force its next release to be a major. Breaking changes for the next major (e.g. 3.0) are collected on the long-lived **`next`** branch, which cuts release candidates. Open `feat!:` / `fix!:` / `perf!:` PRs against `next`.

**Examples:**
- `feat: add support for custom post type archives`
- `fix: resolve N+1 query issue in connections`
- `feat!: change default behavior of user queries`
- `perf: optimize resolver execution time`

> **Note:** Your individual commits within a PR don't need to follow this format—only the PR title matters.

### 2. Pull Request Templates

When creating a PR, select the appropriate template:

- 🐛 **Bug Fixes** - For fixing bugs
- ✨ **Features** - For new features
- 🧪 **Experiments** - For experimental features
- 📚 **Documentation** - For docs improvements
- 🔧 **Refactoring** - For code improvements
- 📦 **Dependencies** - For dependency updates
- 🛠️ **Maintenance** - For CI/CD and tooling

### 3. Code Standards

**PHP:**
- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Run PHPCS: `composer -d plugins/wp-graphql check-cs`
- Run PHPStan: `composer -d plugins/wp-graphql analyse`

**JavaScript:**
- Follow WordPress JavaScript standards
- Run linting: `npm run -w @wpgraphql/wp-graphql lint`

### 4. Documentation

**For new features:**
- Add PHPDoc blocks with `@since x-release-please-version` tags
- Update relevant documentation in `plugins/wp-graphql/docs/`

**For deprecations:**
- Use `@deprecated x-release-please-version` in docblocks
- Use `x-release-please-version` as placeholder in deprecation function calls

These placeholders are automatically replaced with the actual version by release-please during the release PR.

### 5. Hook Naming and Deprecation Conventions

When introducing or changing actions/filters in `plugins/wp-graphql/`, follow these conventions:

- **Use the canonical prefix**: new hooks should use the `graphql_` prefix.
- **Do not introduce new legacy prefixes**: avoid adding new `wpgraphql_*` or `wp_graphql_*` hooks.
- **Document hook call sites**: `do_action()` and `apply_filters()` call sites should include docblocks with:
  - `@since x-release-please-version`
  - `@hookGroup <group>` (using a valid group from `scripts/hooks/groups.json`)
- **Deprecate old hooks safely**:
  - For actions, use `do_action_deprecated( 'legacy_hook', $args, 'x-release-please-version', 'graphql_new_hook' )`
  - For filters, use `apply_filters_deprecated( 'legacy_hook', $args, 'x-release-please-version', 'graphql_new_hook' )`
  - Keep behavior backward compatible while steering users to the canonical hook.
- **Update tests for deprecations**: when a deprecated hook is intentionally fired, tests should explicitly expect the deprecation notice.

### 6. Reference Docs and Recipes

The hook, function, and recipe reference docs (`plugins/wp-graphql/docs/{actions,filters,functions,recipes}/` and the inventories under `docs/generated/`) are **generated artifacts**. You do **not** regenerate or commit them in your PR — the release-please flow (`update-release-pr.yml`) regenerates them when a release PR is cut, which is also when `@since x-release-please-version` placeholders resolve. Your job is the source that feeds them:

- **Hooks / functions:** write a complete docblock at the call site — a description, a typed `@param` (with a description) per arg, `@since x-release-please-version` for a genuinely new symbol, and `@hookGroup <group>` from `scripts/hooks/groups.json`. The docblock linter checks this. See "Hook Naming and Deprecation Conventions" above.
- **Recipes:** add a markdown file under `plugins/wp-graphql/docs/recipes/` (see below).

The legacy coverage check runs in CI (`lint.yml`) and verifies that any hook removed from source stays represented in `scripts/hooks/legacy-hooks.json` so its docs remain available. You can run it locally with:

```bash
npm run hooks:check-legacy -- --plugin=wp-graphql --base-ref=origin/main
```

#### Contributing a recipe

Recipes are rendered directly from repo-backed markdown at `/recipes` on wpgraphql.com. To add one, create `plugins/wp-graphql/docs/recipes/<slug>.md` (the outer `~~~` below is just to show the file's own ` ``` ` code fences):

~~~markdown
---
title: "Add a custom field to the Post type"
group: "Custom Fields"
summary: "A short one-line description used on the recipe index card."
# Optional — otherwise inferred from hook/function names mentioned in the body:
relatedActions:
  - graphql_register_types
relatedFilters: []
relatedFunctions:
  - register_graphql_field
---

Prose explaining the recipe, followed by fenced code blocks:

```php
add_action( 'graphql_register_types', function () {
    // ...
} );
```

To embed a video, use the component (not a raw iframe):

<YouTube id="dQw4w9WgXcQ" />
~~~

Notes:
- `title` and `group` are required; `summary` is recommended (it's the index-card blurb).
- **Related APIs** are inferred automatically from static hook/function names mentioned in the body, unioned with any explicit `relatedActions` / `relatedFilters` / `relatedFunctions` frontmatter.
- The recipe index and cross-links are regenerated during the release-please flow — you don't regenerate them.
- Recipes today are served from these markdown files parsed into templates. This is the interim step toward the longer-term dogfooding goal — WordPress as the CMS, markdown as the content store, and WPGraphQL as the API for the headless front-end — so keep the markdown as the source of truth.

### 7. Testing

**All changes should include tests:**

```bash
# Run the full test suite
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit

# Run specific tests
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit -- tests/wpunit/YourTest.php
```

See the [Testing Guide](./TESTING.md) for detailed instructions.

## Automated Processes

### Release Process (release-please)

We use [release-please](https://github.com/googleapis/release-please) for automated releases:

1. **PR Merged**: Your PR is squash merged with a conventional commit title
2. **Release PR Created**: release-please creates/updates a Release PR with:
   - Version bump based on commit types (`feat:` → minor, `fix:` → patch, `!` → major)
   - Auto-generated changelog from commit messages
3. **Release PR Merged**: When the Release PR is merged:
   - GitHub Release is created
   - Plugin is deployed to WordPress.org (the workflow uses `wp_org_slug` from `release-please-config.json` when the .org slug differs from the repo folder name)
   - Artifacts are attached to the release

### Version Management

- Version numbers are updated automatically by release-please
- `@since x-release-please-version` placeholders are replaced with the actual version during the release PR
- Changelogs are generated from PR titles (via squash merge commits)
- **Upgrade Notices** are automatically added to `readme.txt` when there are breaking changes
- Hook docs are regenerated on release PR updates for supported components (`update-release-pr.yml`)
- Function docs are regenerated on release PR updates for supported components (`update-release-pr.yml`)
- `x-release-please-version` placeholders in `scripts/hooks/legacy-hooks.json` are automatically replaced for the releasing component (`update-release-pr.yml`)
- Hook legacy coverage is enforced in CI (`lint.yml` → `wp-graphql-hook-legacy-coverage`)

> **⚠️ Do not manually edit**: Version numbers, changelogs, or upgrade notices. These are all managed automatically by release-please and our CI workflows.

When adding a new plugin that is deployed to WordPress.org, ensure its entry in `release-please-config.json` includes `"wp_org_slug": "wpgraphql-*"` if the WordPress.org directory slug differs from the repo folder name (e.g. `wp-graphql-acf` → `wpgraphql-acf`). See [Architecture: Future Plugins](./ARCHITECTURE.md#future-plugins) and [Workflows README](../.github/workflows/README.md#when-adding-a-new-plugin).

## Working with the Monorepo

### Plugin Structure

Each plugin in `plugins/` is a self-contained WordPress plugin:

```
plugins/wp-graphql/
├── wp-graphql.php      # Main plugin file
├── src/                # PHP source
├── tests/              # Test suites
├── packages/           # JS packages
├── composer.json       # PHP dependencies
└── package.json        # JS dependencies
```

### Running Commands

```bash
# Commands for specific plugins use workspace flag
npm run -w @wpgraphql/wp-graphql <script>

# Root-level commands
npm run wp-env start
npm run build  # Builds all plugins
```

### Adding Dependencies

```bash
# Add to a specific plugin
npm install <package> -w @wpgraphql/wp-graphql

# Add to root (shared tooling)
npm install <package> -D
```

## Types of Contributions

### Bug Fixes

1. Create an issue describing the bug (if one doesn't exist)
2. Write a failing test that reproduces the bug
3. Fix the bug
4. Ensure the test passes
5. Submit PR with `fix:` prefix

### New Features

1. Open a discussion or issue first for significant features
2. Implement the feature
3. Add comprehensive tests
4. Update documentation
5. Submit PR with `feat:` prefix

### Documentation

1. Documentation lives in `plugins/wp-graphql/docs/`
2. Use Markdown format
3. Include code examples where helpful
4. Submit PR with `docs:` prefix

### Experiments

Experiments are features being validated before core inclusion:

1. See [Experiments documentation](../plugins/wp-graphql/docs/experiments.md)
2. Submit PR with `feat:` prefix and `experiment` label

## Code Review Process

1. All PRs require review from a maintainer
2. CI checks must pass (tests, linting, code quality)
3. Address review feedback
4. Once approved, maintainer will merge

## Getting Help

- **Discord**: [wpgraphql.com/discord](https://wpgraphql.com/discord)
- **Discussions**: [GitHub Discussions](https://github.com/wp-graphql/wp-graphql/discussions)
- **Issues**: [GitHub Issues](https://github.com/wp-graphql/wp-graphql/issues)

## License

By contributing to WPGraphQL, you agree that your contributions will be licensed under the GPL v3 license.
