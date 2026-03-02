# AGENTS.md

Instructions for AI coding agents working in the WPGraphQL repository. For full technical detail (architecture, API, dependencies), see the sections below. CLAUDE.md in this repo points here as the single source of truth.

---

## Project knowledge

- **Tech stack**: Monorepo (npm workspaces, Turborepo). PHP 7.4+, WordPress 6.0+. Core GraphQL via `webonyx/graphql-php` and `ivome/graphql-relay-php`. JavaScript: `@wordpress/scripts`, ESLint, Prettier.
- **Directory structure**: 
  - `plugins/wp-graphql/` — Core plugin (entry: `wp-graphql.php`; namespace `WPGraphQL\`, source in `src/`: Registry, Type, Connection, Data, Model, Mutation, Server, Admin, Utils).
  - `plugins/wp-graphql-ide/`, `plugins/wp-graphql-smart-cache/`, `plugins/wp-graphql-acf/` — Extensions.
  - `docs/` — Contributor docs (DEVELOPMENT.md, TESTING.md, CONTRIBUTING.md, ARCHITECTURE.md).
  - `bin/` — Scripts (e.g. `setup-wp-env.sh`, `smoke-test.sh`, `run-acf-e2e-local.sh`).
- **Dependencies**: Composer for PHP (per plugin); npm at root for workspaces. wp-env (Docker) for local WordPress.

---

## Commands

**Environment (MUST have wp-env running for tests and lint):**
```bash
npm install
npm run wp-env start    # Dev: http://localhost:8888  Test: http://localhost:8889
npm run wp-env stop
npm run wp-env destroy  # Reset everything
```

**Build:**
```bash
npm run build
npm run -w @wpgraphql/wp-graphql build
```

**Tests (paths relative to `plugins/wp-graphql/`):**
```bash
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit
npm run -w @wpgraphql/wp-graphql test:codecept:wpunit -- tests/wpunit/PostObjectQueriesTest.php
npm run -w @wpgraphql/wp-graphql test:codecept:acceptance
npm run -w @wpgraphql/wp-graphql test:codecept:functional
npm run -w @wpgraphql/wp-graphql test:e2e
npm run -w @wpgraphql/wp-graphql-acf test:e2e   # ACF: see docs/TESTING.md; full local run: ./bin/run-acf-e2e-local.sh
```

**Lint (paths relative to `plugins/wp-graphql/`):**
```bash
npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run check-cs -- src/Data/NodeResolver.php
npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run fix-cs -- src/Data/NodeResolver.php
npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run phpstan -- --memory-limit=2G
npm run -w @wpgraphql/wp-graphql lint:js
npm run format
```

**User-facing verification (after code changes):**
- **Smoke test** (quick sanity check): Ensures the plugin has no glaring fatal errors — endpoint responds, introspection and basic queries work. Does not load a browser. Run: `./bin/smoke-test.sh` (optionally `--verbose`).
- **Acceptance / E2E (Playwright)**: Load a browser and step through flows with Playwright. Use when you need to verify UI, admin screens, or full user flows (e.g. GraphiQL IDE, editor behavior). See test commands above for `test:codecept:acceptance`, `test:e2e`, and `test:e2e:ui`.

```bash
./bin/smoke-test.sh
./bin/smoke-test.sh --verbose
```

---

## Conventions to follow

- **Branch naming**: Conventional commit style — `fix/xxx`, `feat/xxx`, `chore/xxx`, etc.
- **PRs**: Always mention what issue the PR closes.
- **Commit / PR title**: Conventional commits; PRs are squash-merged so the title becomes the commit message. Use `feat:`, `fix:`, `docs:`, `chore:`, etc. Use `!` for breaking (e.g. `feat!:`).
- **Testing**: Prefer WPUnit first (we have code coverage; WPUnit runs in WordPress so integration-style). Use acceptance/E2E for UI or browser behavior (JavaScript). For debugging or stepping through E2E tests, suggest Playwright UI mode: `npm run -w @wpgraphql/wp-graphql test:e2e:ui` or `npm run -w @wpgraphql/wp-graphql-acf test:e2e:ui` (same workspace as headless).
- **Bug fix workflow**: Reproduce the bug before making code changes. Identify expected behavior, write a test for it (test should fail). Fix the bug, ensure the test passes. Ideally: push one commit with only the test → let it fail in GitHub CI → push the fix → add links in the PR to both the failing and passing CI runs.
- **File paths**: For lint and test commands, paths are relative to `plugins/wp-graphql/` (e.g. `src/Data/NodeResolver.php`), not repo root.

---

## Architectural decisions

- **TDD preferred** for bug fixes and behavior changes.
- **Model layer**: Access control and data transformation live in Model classes (e.g. `Post.php`, `User.php`) before resolution; do not bypass for new fields.
- **Relay-style connections**: Pagination uses `first`/`after`/`last`/`before`; follow existing connection patterns.
- **No auth in core**: Authentication is handled by extension plugins (e.g. JWT, Application Passwords); core does not ship auth.
- **Public API**: `access-functions.php` — use `register_graphql_type()`, `register_graphql_field()`, etc. Do not edit WordPress core files.

---

## Common pitfalls (MUST / CRITICAL)

- **CRITICAL**: wp-env MUST be running for tests and linting commands to work.
- **CRITICAL**: Lint and PHPStan paths are relative to `plugins/wp-graphql/` (e.g. `src/Data/NodeResolver.php`), not the repo root.
- **CRITICAL**: PHPStan requires `--memory-limit=2G` to avoid memory exhaustion.
- **MUST**: Do not edit WordPress core files.
- ACF E2E: requires ACF installed in wp-env; see docs/TESTING.md. Full local CI-like run: `./bin/run-acf-e2e-local.sh`.

---

## Verification (user perspective)

After making code changes, verify from a user perspective:

1. Start the environment: `npm run wp-env start`.
2. Dev site: http://localhost:8888 — GraphQL endpoint: http://localhost:8888/graphql. WP Admin: http://localhost:8888/wp-admin (admin / password).
3. **Smoke test** (quick check for fatal errors): `./bin/smoke-test.sh` (optionally `--verbose`). This hits the API and basic queries; it does not load a browser.
4. **Browser-based verification** (when the change affects UI or full flows): Run acceptance tests or E2E (Playwright) — they load a browser and step through with Playwright. Use `test:e2e` or `test:e2e:ui` for JS E2E; use `test:codecept:acceptance` for acceptance.
5. Run relevant unit tests (WPUnit) as needed.

---

## Sandbox

The wp-env environment is isolated Docker. It does not touch production or shared state. Safe to experiment. For a clean slate: `npm run wp-env destroy` then `npm run wp-env start`.

---

## Git worktrees / multiple wp-env instances

To run multiple wp-env instances (e.g. different git worktrees or agents), use different ports per worktree:

- **Option A (env vars):** `WP_ENV_PORT=8890 WP_ENV_TESTS_PORT=8891 npm run wp-env start` (e.g. worktree 2 → 8890/8891, worktree 3 → 8892/8893).
- **Option B (override file):** In that worktree, create `.wp-env.override.json` with `{"port": 8890, "testsPort": 8891}` (already gitignored).

When using a custom dev port, run the smoke test with that endpoint: `./bin/smoke-test.sh --endpoint http://localhost:8890/graphql`. If `WP_ENV_PORT` is set, some setups use it as the default for the smoke script.

---

## Available skills

Project-specific procedures live in `.ai/skills/`. They are also exposed via `.cursor/skills` and `.claude/skills` (symlinks to `.ai/skills/`) so Cursor and Claude Code can auto-discover them. Invoke a skill when the task matches:

| Skill | When to use |
|-------|-------------|
| **wpgraphql-dev-cycle** | Before running tests or lint: when to run wpunit vs acceptance vs E2E, exact commands, smoke test; wp-env must be running. |
| **wpgraphql-php** | PHP changes: standards, file paths for lint, `@since next-version`, deprecation. |
| **wpgraphql-worktree** | Setting up or using a worktree with its own wp-env ports. |
| **wpgraphql-acf-e2e** | ACF plugin E2E: install, full run (`./bin/run-acf-e2e-local.sh`), when to use. |
| **wpgraphql-wordpress-agent-skills** | When the task would benefit from general WordPress patterns (plugin development, PHPStan, WP-CLI): guides installing and using [WordPress/agent-skills](https://github.com/WordPress/agent-skills). |

WordPress also publishes [WordPress/agent-skills](https://github.com/WordPress/agent-skills) (blocks, themes, plugins, PHPStan, WP-CLI). Use the **wpgraphql-wordpress-agent-skills** meta-skill to install or reference them; do not duplicate their content in this repo.

### Verifying skills

- **Discovery (Cursor)**: Open Cursor Settings → Rules (or Agent Decides) and confirm the five WPGraphQL skills appear. In Agent chat, type `/` and search for e.g. `wpgraphql-dev-cycle`; the skill should be listed.
- **Explicit invocation**: In Agent chat, run `/wpgraphql-dev-cycle` and ask "What do I run before a PR?" The agent should follow the skill (wp-env, workspace flags, test/lint commands).
- **Implicit use**: Ask "Run PHPCS for the core plugin on `src/Data/NodeResolver.php`." The agent should use the correct workspace and path: `npm run -w @wpgraphql/wp-graphql wp-env:cli -- composer run check-cs -- src/Data/NodeResolver.php`.
- **ACF E2E**: Ask "How do I run E2E for wp-graphql-acf?" The answer should reference **wpgraphql-acf-e2e** and use `-w @wpgraphql/wp-graphql-acf` and optionally `./bin/run-acf-e2e-local.sh`.

---

## Where to find more

- **Contributor docs**: [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md), [docs/TESTING.md](docs/TESTING.md), [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md), [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
- **Cursor rules**: [.cursor/rules/](.cursor/rules/) (wpgraphql.mdc, wpgraphql.json, php_files.json) for additional context in Cursor.
