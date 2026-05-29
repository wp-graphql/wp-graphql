---
title: "Extending the IDE"
description: "The IDE extension model — enqueue a script, wait for WPGraphQLIDE_Window_Ready, and register against window.WPGraphQLIDE. A map of every surface you can extend."
---

The IDE is built to be extended the same way its own built-in features are. Everything you can register — panels, tabs, status-bar items, view modes, menu actions, preferences — goes through the public API on `window.WPGraphQLIDE`, the same registry the IDE itself uses internally (`src/registry/index.js`).

There are three moving parts:

1. **A PHP shell** that enqueues your JavaScript with `wpgraphql-ide` as a dependency.
2. **A JS entry** that waits for the IDE to be ready, then registers your contributions.
3. **The registries and hooks** on `window.WPGraphQLIDE`.

## 1. Enqueue your script (PHP)

Hook `wpgraphql_ide_enqueue_script` — it fires right before the IDE's own render script is enqueued, so depending on `wpgraphql-ide` guarantees load order.

```php
add_action( 'wpgraphql_ide_enqueue_script', function ( $app_context ) {
	wp_enqueue_script(
		'my-ide-extension',
		plugins_url( 'build/extension.js', __FILE__ ),
		[ 'wpgraphql-ide' ], // ensures window.WPGraphQLIDE exists first
		'1.0.0',
		true
	);
} );
```

See [Actions & Filters](./actions-and-filters.md) for the PHP hooks (`wpgraphql_ide_enqueue_script`, `wpgraphql_ide_localized_data`, the capability filter, etc.).

## 2. Wait for the IDE, then register (JS)

The IDE assembles `window.WPGraphQLIDE` and then dispatches the `WPGraphQLIDE_Window_Ready` DOM event. Do all your wiring inside that listener — it's the guarantee that the stores, registries, and hook bus are in place.

```js
window.addEventListener('WPGraphQLIDE_Window_Ready', () => {
	const { registerResponseExtensionTab } = window.WPGraphQLIDE;

	registerResponseExtensionTab('myExtension', {
		title: 'My Extension',
		content: ({ data, response }) => <pre>{JSON.stringify(data, null, 2)}</pre>,
	}, 50);
});
```

`window.WPGraphQLIDE` exposes every `register*` function plus the shared `hooks` bus (a `@wordpress/hooks` instance — the same one `wp.hooks` delegates to). Anything fired by the IDE's PHP-side or JS-side hooks travels on that bus.

## 3. What you can extend

| You want to… | Register with | Guide / reference |
| --- | --- | --- |
| Surface your `extensions` payload in the response pane | `registerResponseExtensionTab` | [Adding a Response Panel](./adding-a-response-panel.md) |
| Add a live badge by the HTTP status / duration / size | `registerStatusBarItem` | [Adding a Status Bar Item](./adding-a-status-bar-item.md) |
| Add a global left-sidebar panel | `registerActivityBarPanel` | [Adding an Activity Bar Panel](./adding-an-activity-bar-panel.md) |
| Add a response viewer mode (next to JSON / Table) | `registerResponseViewMode` | [Access Functions](./access-functions.md#registerresponseviewmode) |
| Add a tab beneath the editor (next to Variables / Headers) | `registerEditorBottomTab` | [Access Functions](./access-functions.md#registereditorbottomtab) |
| Add a query-editor toolbar button | `registerDocumentEditorToolbarButton` | [Access Functions](./access-functions.md#registerdocumenteditortoolbarbutton) |
| Add kebab-menu actions (response / editor / document tab) | `registerResponseAction` / `registerEditorAction` / `registerDocumentTabAction` | [Access Functions](./access-functions.md) |
| Add a top-bar button + workspace tab | `registerTopbarAction` / `registerWorkspaceTabType` | [Access Functions](./access-functions.md#registertopbaraction) |
| Persist a setting (device or user scope) | `registerPreference` | [Access Functions](./access-functions.md#registerpreference) |
| Observe or rewrite every request / response | `wpgraphql-ide.executeRequest` / `executeResponse` / `afterExecute` | [Hooking the Execute Lifecycle](./hooking-the-execute-lifecycle.md) |
| Publish a toast | `wpgraphql-ide.notice` | [Actions & Filters](./actions-and-filters.md#notices) |

## Things to know before you build

- **React & JSX** — React, ReactDOM, and GraphQL are WordPress script externals. Build your extension with `@wordpress/scripts` (or any bundler that externalizes `react` / `wp.*`) so it shares the IDE's React instance. The bundled extensions under `plugins/` are working examples.
- **Surfaces mount conditionally.** A panel/tab/item is unmounted when it isn't on screen. If you need state that accumulates across executions or persists while hidden, don't keep it in component state — see [Tracking State Across Executions](./tracking-state-across-executions.md).
- **Public API & SemVer.** The `register*` functions, the `@wordpress/data` store names, the PHP hooks, and registered preference keys are public. Breaking changes require a major version bump.
