---
name: wpgraphql-acf-e2e
description: Run E2E tests for the wp-graphql-acf plugin. Use when changing ACF-related code or running full local E2E (e.g. ./bin/run-acf-e2e-local.sh). Requires wp-env and ACF installed.
---

# wpgraphql-acf-e2e

Running E2E tests for the wp-graphql-acf plugin. Use when changing ACF-related GraphQL behavior or the ACF plugin’s E2E specs.

## When to use this skill

- You changed code in `plugins/wp-graphql-acf/` and need to run E2E tests.
- You need a full local run that mirrors CI (build, wp-env, install ACF, run E2E, optional stop).

## Prerequisites

- wp-env must be running: `npm run wp-env start` from repo root.
- ACF must be installed in the wp-env test environment. See [docs/TESTING.md](docs/TESTING.md) for ACF Free vs Pro and license setup. Typically:
  - ACF Free: `npm run wp-env run tests-cli -- wp plugin install advanced-custom-fields --activate --allow-root`
  - ACF Pro (license required): use the download URL with key as in docs/TESTING.md.

## Run ACF E2E (wp-env already running, ACF already installed)

```bash
npm run -w @wpgraphql/wp-graphql-acf test:e2e
```

**UI mode** (step through and watch tests in the browser): `npm run -w @wpgraphql/wp-graphql-acf test:e2e:ui`. Use when debugging a failing or flaky E2E test; suggest this to the user when they ask how to debug E2E.

For ACF, all test and lint commands use the **`@wpgraphql/wp-graphql-acf`** workspace (WPUnit, PHPCS, PHPStan, E2E). See **wpgraphql-dev-cycle** for the full list of workspace-based commands.

## Full local CI-like run (build, wp-env, install ACF, E2E, stop)

From repo root:

```bash
./bin/run-acf-e2e-local.sh
```

This script builds, starts wp-env, installs ACF (if configured), runs the ACF E2E suite, and can stop the environment. Use when you want to mimic CI locally or do a one-off full check. See the script and [docs/TESTING.md](docs/TESTING.md) for any required env vars (e.g. ACF license).

## When to use E2E vs WPUnit for ACF

- **WPUnit**: Use for PHP/schema/resolver logic in wp-graphql-acf when the behavior can be asserted without a browser.
- **E2E**: Use when testing flows that involve the editor, admin UI, or full request/response behavior that Playwright can drive.
