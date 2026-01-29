# Legacy Workflows (Removed)

This directory previously contained GitHub Actions workflows from when WPGraphQL Smart Cache was a standalone repository. These workflows have been removed because the plugin is now part of the [WPGraphQL monorepo](https://github.com/wp-graphql/wp-graphql) and uses the monorepo's centralized workflows.

## Workflow Migration Summary

All functionality from the legacy workflows is now covered by the monorepo workflows:

| Legacy Workflow | Monorepo Coverage | Notes |
|----------------|-------------------|-------|
| `code-quality.yml` (PHPStan) | `.github/workflows/lint.yml` → `lint-reusable.yml` | PHPStan static analysis runs automatically on code changes |
| `wordpress-coding-standards.yml` (PHPCS) | `.github/workflows/lint.yml` → `lint-reusable.yml` | PHPCS coding standards checks run automatically on code changes |
| `tests-wordpress.yml` (Integration Tests) | `.github/workflows/integration-tests.yml` → `integration-tests-reusable.yml` | WordPress integration tests run with matrix strategy (PHP × WP versions) |
| `deploy-to-wordpress.yml` | `.github/workflows/release-please.yml` | Automatic deployment to WordPress.org on release |
| `upload-plugin-zip.yml` | `.github/workflows/release-please.yml` | Plugin zip is built and uploaded to GitHub release automatically |
| `update-wordpress-assets.yml` | `.github/workflows/release-please.yml` | Asset updates handled during deployment (Smart Cache doesn't have `.wordpress-org` assets) |

## Benefits of Monorepo Workflows

- **Centralized Configuration**: All plugins share the same workflow logic, reducing maintenance
- **Change Detection**: Workflows only run when relevant files change, improving CI efficiency
- **Consistent Testing**: All plugins use the same test matrix and standards
- **Automated Releases**: `release-please` handles versioning, changelogs, and deployments automatically

## For Developers

When working on WPGraphQL Smart Cache in the monorepo:

- **Linting**: Runs automatically on PRs via `.github/workflows/lint.yml`
- **Testing**: Runs automatically on PRs via `.github/workflows/integration-tests.yml`
- **Releases**: Handled automatically by `.github/workflows/release-please.yml` when Release PRs are merged

See the [main Development guide](https://github.com/wp-graphql/wp-graphql/blob/main/docs/DEVELOPMENT.md) for setup instructions.
