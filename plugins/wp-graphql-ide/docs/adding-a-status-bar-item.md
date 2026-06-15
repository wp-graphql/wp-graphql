---
title: "Adding a Status Bar Item"
description: "Add a live badge to the response toolbar's status row with registerStatusBarItem."
---

The response toolbar's status row holds the HTTP status code, duration, size, resolver count, and N+1 warning. `registerStatusBarItem` lets you add your own badge there — a cache hit/miss indicator, a schema warning, a custom count, anything you can derive from the current response.

## Render-or-hide

An item is a single `render(ctx)` callback that returns a `ReactNode` — or `null` to hide itself for the current response. Returning `null` is how the built-in resolver-count and N+1 badges disappear when there's no tracing data.

```js
window.addEventListener('WPGraphQLIDE_Window_Ready', () => {
	const { registerStatusBarItem } = window.WPGraphQLIDE;

	registerStatusBarItem('my-cache-hit', {
		render: ({ parsedResponse, focusResponseTab }) => {
			const hit =
				parsedResponse?.extensions?.myCache?.status === 'HIT';
			if (!hit) {
				return null; // nothing to show
			}
			return (
				<button
					type="button"
					title="Cache HIT — open My Cache"
					onClick={() => focusResponseTab('ext:myCache')}
				>
					⚡ cached
				</button>
			);
		},
	}, 60);
});
```

## The context object

`render` receives everything it needs to describe the current response:

```
{ response, parsedResponse, responseStatus, responseDuration, responseSize, isFetching, focusResponseTab(name) }
```

- `parsedResponse` is the already-parsed response object — reach for it instead of `JSON.parse(response)`.
- `focusResponseTab(name)` programmatically opens a response tab. Extension tabs are addressed as `ext:<key>` (e.g. `ext:myCache`), so a badge can deep-link into its own panel.

## Priority

The third argument orders items left-to-right (lower first). Built-ins run status-code (10) → duration (20) → size (30) → resolver-count (40) → N+1 (50).

## A caveat worth knowing

Status-bar items render only when a response is present and no request is in flight, and `render` re-runs from the current response each time — so don't try to accumulate session totals inside the render callback. Keep running aggregates in a module-scoped store fed by `afterExecute`; see [Tracking State Across Executions](./tracking-state-across-executions.md).

## Reference

Full parameter list and the registration hook: [`registerStatusBarItem`](./access-functions.md#registerstatusbaritem).
