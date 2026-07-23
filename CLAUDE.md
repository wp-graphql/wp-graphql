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

### Workflow logic lives in `scripts/`, not inlined in YAML

Non-trivial logic a GitHub Actions workflow needs belongs in a committed script (`scripts/*.js`) that the workflow *calls*, not in an inline `run:` block or a `node - <<'NODE'` heredoc. A one-line `sed`/`grep` or a straight tool invocation is fine inline; anything with branching, parsing, or JSON manipulation goes in a script. This keeps the logic testable, reviewable, and runnable locally.

This is the target for new and changed workflow logic; the codebase isn't fully there yet. `update-release-pr.yml` still carries one inline `node - <<'NODE'` block (the "Replace legacy hook placeholder versions" step) that predates the convention — extract it to a tested script when you next touch it, rather than adding to it.

The pattern the release scripts follow (`scripts/update-*.js`, `scripts/reconcile-release-manifest.js`):

- `#!/usr/bin/env node` shebang and a JSDoc header with a `Usage:` line.
- CLI args as `--key=value`, parsed by a small `parseArgs()`.
- Pure, exported functions for the core logic; a `main()` that does the IO, guarded by `if (require.main === module)`.
- A sibling `scripts/<name>.test.js` using Node's built-in `assert` (no test runner), added to the `test:scripts` npm script so it runs in the **Test Release Scripts** workflow. Prefer flags that let the test drive the script without a live git remote/network (e.g. `--main-manifest=<path>` to stand in for a `git show` read).

### Hook conventions

- Prefer canonical `graphql_*` hook names for new actions/filters.
- Do not introduce new hooks with `wpgraphql_*` or `wp_graphql_*` prefixes.
- Every `do_action()` / `apply_filters()` call site needs a complete docblock: a description, a typed `@param` (with a description) for each passed arg, `@since x-release-please-version` (for a genuinely new hook), and `@hookGroup <group>` using `scripts/hooks/groups.json`. This is the contract the hook linter checks.
- For hook migrations, keep backward compatibility with:
  - `do_action_deprecated( 'legacy_hook', $args, 'x-release-please-version', 'graphql_new_hook' )`
  - `apply_filters_deprecated( 'legacy_hook', $args, 'x-release-please-version', 'graphql_new_hook' )`
- If deprecated hooks are intentionally fired in tests, assert expected deprecations instead of treating them as failures.
- You don't regenerate or commit the generated hook docs yourself — the release-please flow (`update-release-pr.yml`) regenerates them when a release PR is cut, which is also when `x-release-please-version` placeholders resolve. Your job is a complete, correct docblock at the call site.

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
- **Hooks are supported API.** A filter or action we ship is part of the public contract. Document a new hook like any other (purpose, `@param`s, `@since`) and don't hedge it with "experimental" or "may change" disclaimers to leave room for a future refactor. If a hook later has to change or go away, retire it through the normal deprecation path above, don't stamp new hooks as throwaway. We deliberately have **no** experimental/unstable hook tier, and we won't add one via a name prefix (`__experimental`-style) either: Gutenberg ran that experiment at scale and [walked it back](https://developer.wordpress.org/block-editor/contributors/code/coding-guidelines/), because the markers never stopped adoption and the hooks ended up under the back-compat policy anyway. A PHP hook can't truly be made private (anything can `add_filter()` once you `apply_filters()`), so we don't pretend otherwise. Formal experimental *features* have a home in `WPGraphQL\Experimental`.
- **`@internal` means "private, don't depend on this," not "unstable."** The one narrow carve-out from "hooks are public" is a hook that exists purely as plumbing behind a public function, where the function is the intended seam. Example: `graphql_wp_connection_{$type}_from_field_name` is an implementation detail of the public `rename_graphql_field()` function (authors call the function, they don't hook that filter). Tag those `@internal` (also recognized by PHPStan), alongside genuinely private symbols like admin/updater internals. `@internal` documents intent, it does not enforce anything, so keep the bar high: default to public-and-supported, and never use `@internal` to pre-excuse a change to a hook you're actually offering as an extension seam.
- **Schema descriptions (and naming) are self-describing and backend-agnostic.** The schema is the public API contract, and a developer introspecting it should be able to understand it with no prior WordPress knowledge, the backend could be WordPress, Supabase, or a spreadsheet. There should be no expectation that the reader knows WordPress. Two levels:
  - **Never leak implementation plumbing** in `description` strings: no post meta keys (`_wp_page_template`, `_thumbnail_id`), WP class properties (`WP_Post->guid`), DB tables/columns (the `post_objects` table, the `post_mime_type` column), function names (`wp_*`, `WP_Query`), or query-var names. Describe what the user provides (a template file name, a slug, an ID), not where it is stored.
  - **Prefer declarative, domain language over WordPress-specific vocabulary.** WPGraphQL deliberately abstracts its vocabulary, a `ContentNode` rather than a "post," a `ContentType` rather than a "post type", and descriptions should follow suit: describe the *thing* and what it does, not its WordPress name. Reach for a WordPress-specific term only when there is genuinely no clearer general word, and even then describe what it is rather than assuming the reader knows it.
  Code comments and developer-facing deprecation messages may still reference internals, this applies to the user-facing `description` strings (and type/field naming) only.
