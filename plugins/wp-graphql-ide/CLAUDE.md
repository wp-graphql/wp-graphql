# CLAUDE.md

Guidance for Claude Code working in this plugin. The repo-root `CLAUDE.md` covers the monorepo; this file covers wp-graphql-ide specifics.

## Common Development Commands

### Setup and Development
```bash
npm install                              # From monorepo root
npm run wp-env start                     # From monorepo root (Docker required)
npm run -w @wpgraphql/wp-graphql-ide start    # Dev server with hot reload
npm run -w @wpgraphql/wp-graphql-ide clean    # Remove node_modules + build dirs
```

### Building
```bash
npm run -w @wpgraphql/wp-graphql-ide build         # Production build
npm run -w @wpgraphql/wp-graphql-ide build:main    # Main app only (wp-scripts)
npm run -w @wpgraphql/wp-graphql-ide build:zip     # Distribution zip
```

### Testing
```bash
npm run -w @wpgraphql/wp-graphql-ide test:unit     # Jest
npm run -w @wpgraphql/wp-graphql-ide test:e2e      # Playwright (needs wp-env)
npm run -w @wpgraphql/wp-graphql-ide test:e2e:ui   # Playwright UI mode

# Codeception WPUnit (PHP integration)
npm run -w @wpgraphql/wp-graphql-ide test:codecept:wpunit
```

### Code Quality
```bash
npm run -w @wpgraphql/wp-graphql-ide lint:js
npm run -w @wpgraphql/wp-graphql-ide lint:js:fix
npm run -w @wpgraphql/wp-graphql-ide wp-env:cli -- composer run check-cs
npm run -w @wpgraphql/wp-graphql-ide wp-env:cli -- composer run fix-cs
npm run -w @wpgraphql/wp-graphql-ide wp-env:cli -- composer run phpstan -- --memory-limit=2G
```

### Versioning
Handled by release-please. Use `@since x-release-please-version` placeholders in new PHP docblocks; the `update-release-pr.yml` workflow rewrites them on release-PR creation. The plugin header `Version:` and `readme.txt` `Stable tag:` are bumped automatically — do not hand-edit.

## Architecture Overview

WPGraphQL IDE provides a modern GraphQL query editor for WordPress, built on `@wordpress/components` with a registry-based extension API. It depends on the WPGraphQL core plugin and optionally integrates with WPGraphQL Smart Cache for persisted documents.

### Access Modes
- **Drawer**: slide-up overlay from any admin page (admin bar trigger)
- **Dedicated page**: `/wp-admin/admin.php?page=graphql-ide`
- **Public endpoint** (optional): unauthenticated `/graphql` IDE for headless devs; saves are gated to logged-in users

Custom capability: `manage_graphql_ide` (assigned to administrators by default; overridable via the `wpgraphql_ide_capability_required` filter).

### Main Application (`src/`)

- **`api/`** — data layer (WPGraphQL client + REST fallback): `graphql-client.js`, `documents.js`, `history.js`, `preferences.js`
- **`stores/`** — `@wordpress/data` stores. The store names are public API and must remain backward-compatible:
  - `wpgraphql-ide/app` — query, variables, headers, response, schema
  - `wpgraphql-ide/document-editor` — open tabs, active document, drafts, dirty state
  - `wpgraphql-ide/activity-bar` — left sidebar panels and visibility
  - `wpgraphql-ide/document-tab-actions`, `editor-actions`, `editor-bottom-tabs`, `response-actions`, `response-extensions`, `response-view-modes`, `status-bar-items` — extension registries
- **`components/`** — React UI. `IDELayout.jsx` is the top-level layout; `ide-layout/` contains the major panes; `document-settings/`, `dialogs/`, `editors/`, `editor-bottom-tabs/`, `response-extensions/`, `response-view-modes/`, `settings/`, `status-bar-items/` host feature-specific UIs.
- **`hooks/`** — composable behavior: `useSchema`, `useExecution`, `useAutoSave`, `useDocumentDirty`, `useNotices`, `useLeftPanel`, `useParsedQuery`, `usePanelOrder`, `usePersistedSize`, `useResponseTabOrder`, `useToggleSet`, `useDebouncedCallback`
- **`access-functions.js`** — public JS API: `registerPreference`, `registerDocumentEditorToolbarButton`, `registerActivityBarPanel`, `registerResponseExtensionTab`, `registerEditorBottomTab`, `registerStatusBarItem`, `registerResponseViewMode`, `registerResponseAction`, `registerEditorAction`, `registerDocumentTabAction`, `registerWorkspaceTabType`, `registerTopbarAction`. Surfaced on `window.WPGraphQLIDE`.
- **`bootstrap.js`** — single source of truth for `endpointMode`, `isUserLoggedIn`, `loginUrl`, `allowEndpointSignIn` derived from `window.WPGRAPHQL_IDE_DATA`.

### PHP Integration (`includes/`)

PSR-4 autoloaded under namespace `WPGraphQLIDE\`. Key classes:
- `Access.php` — capability checks, per-user post-visibility filters
- `AdminUI.php` — admin pages, drawer trigger, admin bar
- `AssetEnqueue.php` — script/style enqueue with auto-detected asset hashes
- `SmartCacheBridge.php` — saved documents live in Smart Cache's `graphql_document` CPT; execution history is browser-local, implemented in `src/api/history-local.js`
- `Rest.php`, `ImportExport.php` — REST endpoints for document import/export
- `UserMeta.php` — owns `wpgraphql_ide_*` user-meta keys (theme, persist_headers, collection_order)
- `settings.php`, `SettingsPage.php`, `document-settings/` — admin settings + per-document settings drawer
- `public-endpoint.php` — opt-in unauthenticated IDE
- `Telemetry.php` — opt-in usage reporting

Public PHP API: `access-functions.php`. Hooks documented in `docs/actions-and-filters.md`.

### Bundled Extension Plugins (`plugins/`)

Each has its own webpack entry and PHP shell. They use the same public APIs that third-party extensions would:
- `help-panel` — documentation/help sidebar
- `query-composer-panel` — GraphiQL-style query explorer
- `smart-cache-panel` — Smart Cache document management

### Build System
Multi-entry webpack via `@wordpress/scripts`. React, ReactDOM, and GraphQL are WordPress externals. Build outputs: main app → `build/`, each bundled extension → `plugins/<name>/build/`. `.distignore` controls what ships to wp.org.

## Development Notes

- **State persistence**: device-scoped preferences and query history live in localStorage under the keys `wpgraphql-ide:prefs:v1:user-{userId}:ctx-{context}` and `wpgraphql-ide:local-history:v1:user-{userId}:ctx-{context}`; user-scoped preferences live in user meta. Open tabs are device-scoped. See `src/api/preferences.js` and `src/api/history-local.js`.
- **Public API surface**: store names, access functions, PHP hooks, and registered preference keys are public. Breaking changes require a major version bump.
- **i18n**: text domain `wpgraphql-ide`. Use `@wordpress/i18n` in JS, `__()`/`_x()` in PHP.
- **Smart Cache dependency**: saved-document features degrade gracefully when Smart Cache is not installed (`hasSmartCache` flag in bootstrap data).
- **Requirements**: PHP 7.4+, WordPress 7.0+, WPGraphQL active.
- **Docs**: live in `docs/` (mirrors core WPGraphQL's `docs/` + `docs_nav.json` layout). Reference: `docs/access-functions.md` (JS API), `docs/actions-and-filters.md` (PHP/JS hooks), `docs/api-surface.md` (surface inventory). Guides + index: `docs/README.md`. The `docs/` dir is excluded from the shipped zip via `.distignore`.
