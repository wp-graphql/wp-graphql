---
title: "Adding a Response Panel"
description: "Surface your plugin's extensions payload in the IDE's response pane with registerResponseExtensionTab."
---

The response pane's extension tabs (Errors, Debug, Tracing, Query Log, Smart Cache, …) are all registered through one function: `registerResponseExtensionTab`. This is the most common extension point — if your plugin returns data in the GraphQL response's `extensions`, a panel is how you show it.

## The data contract

Each tab is keyed to a property of `response.extensions`. Register the tab under that key, and the IDE will:

- show the tab **only when** the latest response contains `extensions.<key>`, and
- pass the value at that key to your `content` component as its `data` prop.

```js
window.addEventListener('WPGraphQLIDE_Window_Ready', () => {
	const { registerResponseExtensionTab } = window.WPGraphQLIDE;

	// Reads response.extensions.myCache on every response.
	registerResponseExtensionTab('myCache', {
		title: 'My Cache',
		content: ({ data, response }) => (
			<p>Status: {data?.status ?? 'unknown'}</p>
		),
	}, 50);
});
```

`content` also receives the full parsed `response` if you need siblings like `data` or `errors`.

## Dynamic titles

Pass a function for `title` to surface a count or state. It receives the same `{ data, response }`:

```js
title: ({ data }) => `My Cache (${data?.entries?.length ?? 0})`,
```

This is how the built-in **Errors (3)** and **Headers (15)** tabs render their counts.

## `alwaysShow`

By default a tab only appears when its key is present in `extensions`. Set `alwaysShow: true` to render it regardless — useful for a panel that describes the response envelope itself (the built-in `errors` and `headers` tabs do this) or one that should explain its own empty state:

```js
registerResponseExtensionTab('myCache', {
	title: 'My Cache',
	alwaysShow: true,
	content: ({ data }) =>
		data ? <CacheReport data={data} /> : <p>No cache data for this response.</p>,
}, 50);
```

## Priority

The third argument orders tabs left-to-right; lower renders first. Built-ins use Errors (5), Tracing (7), Debug (10), Query Log (40), Headers (80) — pick a number that slots your tab where it belongs.

## A caveat worth knowing

Your `content` component is **unmounted** whenever another response tab is active. That's fine for a panel that renders the current response — it re-derives from `data` when it remounts. But if your panel needs to *accumulate* something across executions (a session counter, a running log), that state can't live in the component or it stops updating while the tab is hidden. See [Tracking State Across Executions](./tracking-state-across-executions.md).

## Reference

Full parameter list and the registration hook: [`registerResponseExtensionTab`](./access-functions.md#registerresponseextensiontab).
