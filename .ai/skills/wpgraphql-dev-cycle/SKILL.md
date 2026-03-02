# wpgraphql-dev-cycle

When to run tests, lint, and smoke tests in the WPGraphQL monorepo, and exact commands.

## When to use this skill

- Before committing or opening a PR: you need to run the right tests and lint.
- After making code changes: you need to verify tests pass and optionally run a user-facing smoke test.
- When unsure whether to run WPUnit, acceptance, functional, or E2E tests.

## Prerequisite

**CRITICAL**: wp-env MUST be running. Start it with `npm run wp-env start` from the repo root. Tests and lint commands run inside the wp-env Docker environment.

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

All test file paths are relative to `plugins/wp-graphql/` (or the relevant plugin directory).

## Lint (PHP and JS)

Run from repo root. File paths in commands are relative to `plugins/wp-graphql/` (e.g. `src/Data/NodeResolver.php`).

- **PHPCS (check):** `npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run check-cs -- src/Data/NodeResolver.php`
- **PHPCS (fix):** `npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run fix-cs -- src/Data/NodeResolver.php`
- **PHPStan:** `npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run phpstan -- --memory-limit=2G` (memory limit is required)
- **JS:** `npm run -w @wpgraphql/wp-graphql lint:js`
- **Format (Prettier):** `npm run format`

## Smoke test (user-facing verification)

After code changes, verify the GraphQL endpoint works from a user perspective:

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
