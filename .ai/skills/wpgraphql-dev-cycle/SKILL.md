---
name: wpgraphql-dev-cycle
description: When to run tests, lint, and smoke tests in the WPGraphQL monorepo; exact commands; wp-env must be running. Use before committing, opening a PR, or when unsure whether to run WPUnit, acceptance, or E2E.
---

# wpgraphql-dev-cycle

When to run tests, lint, and smoke tests in the WPGraphQL monorepo, and exact commands.

## When to use this skill

- Before committing or opening a PR: you need to run the right tests and lint.
- After making code changes: you need to verify tests pass and optionally run a user-facing smoke test.
- When unsure whether to run WPUnit, acceptance, functional, or E2E tests.

## Prerequisite

**CRITICAL**: wp-env MUST be running. Start it with `npm run wp-env start` from the repo root. Tests and lint commands run inside the wp-env Docker environment.

## Workspaces: which plugin you're changing

This monorepo uses npm workspaces. Use the `-w` (workspace) flag to run tests and lint **for the plugin you changed**:

- **Core plugin** (`plugins/wp-graphql/`): `-w @wpgraphql/wp-graphql`
- **ACF plugin** (`plugins/wp-graphql-acf/`): `-w @wpgraphql/wp-graphql-acf`
- **IDE** (`plugins/wp-graphql-ide/`): `-w @wpgraphql/wp-graphql-ide`
- **Smart Cache** (`plugins/wp-graphql-smart-cache/`): `-w @wpgraphql/wp-graphql-smart-cache`

Examples: run PHPCS for core vs ACF:
- `npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run check-cs -- src/Data/NodeResolver.php`
- `npm run -w @wpgraphql/wp-graphql-acf wp-env:cli -- composer run check-cs -- src/Plugin.php`

File paths in those commands are relative to that plugin’s directory (e.g. `plugins/wp-graphql/` or `plugins/wp-graphql-acf/`).

## Test types and when to use them

- **WPUnit** (preferred for most code changes): Integration-style tests in WordPress. We have code coverage for WPUnit. Use for resolver changes, schema changes, model logic, PHP behavior.
  - `npm run -w @wpgraphql/wp-graphql test:codecept:wpunit`
  - Single file: `npm run -w @wpgraphql/wp-graphql test:codecept:wpunit -- tests/wpunit/PostObjectQueriesTest.php`
- **Acceptance / Functional**: HTTP or WordPress function-level tests when WPUnit is not enough.
  - `npm run -w @wpgraphql/wp-graphql test:codecept:acceptance`
  - `npm run -w @wpgraphql/wp-graphql test:codecept:functional`
- **E2E (Playwright)**: Use when testing UI or browser/JavaScript behavior (e.g. GraphiQL IDE, admin screens).
  - `npm run -w @wpgraphql/wp-graphql test:e2e`
  - For wp-graphql-acf E2E: `npm run -w @wpgraphql/wp-graphql-acf test:e2e` (see wpgraphql-acf-e2e skill for setup). Full local run: `./bin/run-acf-e2e-local.sh`
  - **UI mode** (for humans debugging or stepping through tests): same workspace with `test:e2e:ui` — e.g. `npm run -w @wpgraphql/wp-graphql test:e2e:ui` or `npm run -w @wpgraphql/wp-graphql-acf test:e2e:ui`. Suggest this when the user is debugging a failing or flaky E2E test.

All test file paths are relative to `plugins/wp-graphql/` (or the relevant plugin directory).

## Lint (PHP and JS)

Run from repo root. Use the workspace (`-w`) for the plugin you changed. File paths are relative to that plugin’s directory.

**Core plugin (wp-graphql):**
- **PHPCS (check):** `npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run check-cs -- src/Data/NodeResolver.php`
- **PHPCS (fix):** `npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run fix-cs -- src/Data/NodeResolver.php`
- **PHPStan:** `npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run phpstan -- --memory-limit=2G` (memory limit is required)
- **JS:** `npm run -w @wpgraphql/wp-graphql lint:js`

**Other plugins (same pattern, different workspace):** e.g. for ACF: `npm run -w @wpgraphql/wp-graphql-acf wp-env:cli -- composer run check-cs -- src/Plugin.php` and `npm run -w @wpgraphql/wp-graphql-acf lint:js` if applicable.

- **Format (Prettier):** `npm run format` (root; formats all workspaces)

## Smoke test (quick sanity check)

The smoke test checks that the plugin has no glaring fatal errors: the GraphQL endpoint responds, introspection works, and basic queries succeed. It does **not** load a browser. For verification that involves loading a browser and stepping through with Playwright (e.g. GraphiQL IDE, admin UI), use **acceptance tests** or **E2E (Playwright)** instead (see "Test types" above).

After code changes:

```bash
./bin/smoke-test.sh
./bin/smoke-test.sh --verbose   # show full responses
```

Default endpoint: http://localhost:8888/graphql. If using custom ports (e.g. worktrees), use `--endpoint http://localhost:PORT/graphql`.

## Suggested order

1. Start wp-env if not running: `npm run wp-env start`
2. Run relevant tests (usually WPUnit first)
3. Run lint (fix-cs, then phpstan, then lint:js)
4. Run smoke test: `./bin/smoke-test.sh`
