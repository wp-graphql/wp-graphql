# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working in this repository. It covers the monorepo as a whole and the conventions shared across plugins. Each plugin has its own `CLAUDE.md` (linked below) with plugin-specific commands, architecture, and gotchas — those load automatically when you work inside the plugin's directory.

## Repository Overview

WPGraphQL is a monorepo containing the WPGraphQL ecosystem of WordPress plugins. The core plugin adds a `/graphql` endpoint to WordPress and defines a GraphQL Schema based on WordPress registries (post types, taxonomies, settings, etc.). Uses npm workspaces and Turborepo for orchestration.

### Plugins in the Monorepo

- **plugins/wp-graphql/** — Core GraphQL API plugin (PHP 7.4+, WP 6.0+) — [`CLAUDE.md`](plugins/wp-graphql/CLAUDE.md)
- **plugins/wp-graphql-ide/** — GraphiQL IDE for WordPress admin (React-based) — [`CLAUDE.md`](plugins/wp-graphql-ide/CLAUDE.md)
- **plugins/wp-graphql-smart-cache/** — Caching and cache invalidation — [`CLAUDE.md`](plugins/wp-graphql-smart-cache/CLAUDE.md)
- **plugins/wp-graphql-acf/** — ACF field groups and fields in GraphQL — [`CLAUDE.md`](plugins/wp-graphql-acf/CLAUDE.md)
- **plugins/wp-graphql-schema-monitor/** — Schema change monitoring (experimental)

### Branding & design system

The `design/brand/` directory holds the WPGraphQL product-family brand guides
(a shared navy foundation + a per-product accent for core, IDE, ACF, and Smart
Cache) and the scripts that generate each plugin's WordPress.org assets (icons,
banners, screenshots). Start at [`design/brand/README.md`](design/brand/README.md)
— it documents the guides, the scoped-theme conventions the `wpgraphql.com` site
uses, and how to (re)generate or contribute assets.

## Development Environment

Requires Docker. Uses `@wordpress/env` for local WordPress.

```bash
npm install                          # Install all workspace dependencies
npm run wp-env start                 # Dev site: localhost:8888, Test site: localhost:8889
npm run wp-env start -- --xdebug    # Start with XDebug
npm run wp-env stop                  # Stop environment
npm run wp-env destroy               # Reset everything
```

wp-env must be running for tests and linting commands to work.

## Build, Test, and Lint

Turborepo orchestrates per-workspace tasks. Run a task across all workspaces, or scope it to one plugin with `-w <workspace>`:

```bash
npm run build                            # Build all workspaces
npm run -w @wpgraphql/wp-graphql build   # Build one plugin
npm run format                           # Prettier across all workspaces
```

Tests and PHP linting run inside the wp-env Docker containers and are invoked per workspace. The exact scripts, file-path conventions, and single-test syntax differ per plugin — see that plugin's `CLAUDE.md`. Workspace names:

| Plugin | Workspace |
| --- | --- |
| Core | `@wpgraphql/wp-graphql` |
| IDE | `@wpgraphql/wp-graphql-ide` |
| Smart Cache | `@wpgraphql/wp-graphql-smart-cache` |
| ACF | `@wpgraphql/wp-graphql-acf` |

## Development Workflow

- **Every bug fix ships with a regression test.** A fix is not done until a test that fails before the fix and passes after it is committed alongside the change. No fix-only commits for reproducible bugs.
- **Test the widest user-facing surface first.** Prefer the test that exercises the software the way a user does — e2e (Playwright) and integration/functional/acceptance (Codeception) — over a narrow unit test. Reach for the broadest layer that can reproduce the bug; reproduce it there first. Unit tests are valuable and we want them, but the core priority is proving the software works as a whole, not that one function does. When a unit test is faster or more precise for the specific logic, add it _in addition to_ — not _instead of_ — the surface-level test.
- **TDD preferred**: For bug fixes, write the failing test(s) first, confirm they fail, implement the fix, confirm they pass.
- **Conventional Commits**: PR titles must follow the format (`feat:`, `fix:`, `perf:`, `docs:`, `chore:`, etc.). PRs are squash-merged, so the title becomes the commit message. The `!` suffix (e.g., `feat!:`) signals a breaking change.
- **CI matrix**: Tests run across WordPress 6.1–trunk, PHP 7.4–8.4, block and classic themes, single and multisite.

### Issue tracker conventions

These apply when **we (the maintainers) open an issue**. Community-filed issues get triaged and labeled afterward, so don't hold them to this.

- **Issue titles are plain descriptions, not Conventional Commits.** Describe the problem or request (e.g. "WPGraphQL IDE enables the block editor for the graphql_document post type"). The `fix:` / `feat:` / `chore:` prefixes are for **PR** titles, not issues — don't prefix an issue title.
- **Label accurately at creation.** When we open the issue we already understand its scope, so apply the right labels up front rather than leaving it for triage:
  - a `type:` label (`type: bug`, `type: enhancement`, …),
  - `effort:` (`low` ≈ a day or less, `med` < a week, `high` > a week),
  - `impact:` (`low` / `med` / `high` — `high` is reserved for major bugs or newly-unblocked use cases),
  - and any area label that fits (e.g. `graphiql ide`, `regression`).
  - Calibrate `effort` / `impact` against existing labeled issues rather than in the abstract.
- **Link the fix back.** Reference the issue from the PR (`Fixes #1234`) so the squash-merge closes it.

## Shared Coding Conventions

These apply across the PHP plugins; see each plugin's `CLAUDE.md` for its specifics (PHP floor, PHPStan level, JS tooling).

- **PHP**: WordPress Coding Standards (PHPCS), checked/fixed and statically analyzed via each plugin's Composer scripts (`check-cs` / `fix-cs` / `phpstan`).
- **JavaScript**: `@wordpress/scripts` (ESLint + Prettier).
- **Version placeholders**: Use `@since x-release-please-version` in PHPDoc `@since` tags; release-please rewrites them on release.
- **Deprecation**: `_deprecated_argument( __METHOD__, 'x-release-please-version', 'Message.' );`
