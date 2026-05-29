---
title: "WPGraphQL IDE Documentation"
description: "Index of the WPGraphQL IDE docs — getting started, extension guides, and the API reference."
---

# WPGraphQL IDE Documentation

Docs for building on and extending the WPGraphQL IDE. The split mirrors core WPGraphQL: short **reference** docs for "what exists", and **guides** for "here's how to build X".

> Navigation for these docs is also described in [`docs_nav.json`](./docs_nav.json), kept in the shape WPGraphQL core uses so the site can ingest it later.

## Getting Started

- [Introduction](./introduction.md) — what the IDE is and how it's put together.
- [Extending the IDE](./extending-the-ide.md) — the extension model: enqueue a script, wait for `WPGraphQLIDE_Window_Ready`, and register against `window.WPGraphQLIDE`.

## Guides

- [Build Your First IDE Extension](./build-your-first-ide-extension.md) — end-to-end: a PHP shell that enqueues a script, plus a response panel.
- [Adding a Response Panel](./adding-a-response-panel.md) — surface your plugin's `extensions` payload in the response pane.
- [Adding a Status Bar Item](./adding-a-status-bar-item.md) — a live badge next to the HTTP status / duration / size.
- [Adding an Activity Bar Panel](./adding-an-activity-bar-panel.md) — a global left-sidebar panel.
- [Hooking the Execute Lifecycle](./hooking-the-execute-lifecycle.md) — observe or rewrite every request and response.
- [Tracking State Across Executions](./tracking-state-across-executions.md) — keep a counter/log/aggregate that survives panel unmounts.

## Reference

- [Access Functions](./access-functions.md) — the `window.WPGraphQLIDE` registration API.
- [Actions & Filters](./actions-and-filters.md) — PHP and JavaScript hooks, plus the execute pipeline.
- [API Surface](./api-surface.md) — maintainer inventory of server-side surfaces (bootstrap data, GraphQL, REST, meta, caps).

## Related

- [Upgrade to 5.0](../UPGRADE-5.0.md) — breaking changes and the 4.x → 5.0 migration guide (ships with the plugin).
- [Contributing](../CONTRIBUTING.md)
