---
title: "Introduction"
description: "What the WPGraphQL IDE is, what it can do, and how it's put together."
---

WPGraphQL IDE is a WordPress plugin that provides a modern GraphQL query editor built with CodeMirror 6, `@wordpress/components`, and `@wordpress/data`. It runs inside the WordPress admin as a slide-up drawer (reachable from any admin page) or as a dedicated full-page editor, and can optionally expose a public, unauthenticated endpoint for headless developers.

It depends on the WPGraphQL core plugin and integrates with WPGraphQL Smart Cache for persisted documents. With Smart Cache inactive, the IDE still works as a standalone GraphQL client — editor, schema, execute, response panes, history, and preferences.

## Key features

- **Tabbed document editor** with auto-save and per-document state.
- **CodeMirror 6** with GraphQL syntax highlighting, autocomplete, and linting.
- **Schema-aware** autocomplete and a docs explorer.
- **Response viewer** with JSON, Table, and Raw modes.
- **Response extension tabs** for Errors, Debug, Tracing, Query Log, and any plugin's `extensions` data.
- **Execution history** persisted as a custom post type, scoped per user.
- **Query composer** for visual query building.
- **Extensible** through access functions, public `@wordpress/data` stores, and WordPress hooks.

## How it's put together

- **State management** — `@wordpress/data` (Redux) stores. The store names are public API: `wpgraphql-ide/app`, `wpgraphql-ide/document-editor`, `wpgraphql-ide/activity-bar`, and the registry stores under `wpgraphql-ide/*`.
- **Editors** — CodeMirror 6 (`cm6-graphql` for GraphQL, `@codemirror/lang-json` for JSON).
- **UI** — `@wordpress/components`, following the Gutenberg post-editor layout: a global top bar, a left activity bar for global panels, and a document-scoped editor area with tabs.
- **Persistence** — documents and history are WordPress custom post types accessed over the REST API; user preferences are user meta. See [API Surface](./api-surface.md).

## Layout philosophy

Controls are scoped by their relationship to the active document:

- **Global** (activity bar / left sidebar): docs explorer, history, help — things that apply regardless of which tab is active.
- **Document-scoped** (editor area): tabs, auth toggle, query composer, send button, variables, headers — things that change per document.

## Next steps

- [Extending the IDE](./extending-the-ide.md) — the extension model and a map of every surface you can register into.
- [Build Your First IDE Extension](./build-your-first-ide-extension.md) — a hands-on walkthrough.
