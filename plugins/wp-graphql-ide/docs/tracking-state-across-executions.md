---
title: "Tracking State Across Executions"
description: "Keep a counter, log, or aggregate that survives panel unmounts — initialize at WPGraphQLIDE_Window_Ready, record from afterExecute, and read it back with useSyncExternalStore."
---

Most extension surfaces mount **conditionally**. An activity-bar panel renders while its panel is selected; response panels, view modes, and status-bar items render while their tab/mode is on screen; editor-bottom tabs render while expanded; workspace tabs render while open. When a surface isn't visible, the IDE unmounts its component and discards its React state and effects.

That's fine for a surface that only ever shows the *current* response — it re-derives from props when it remounts. But it breaks anything that must accumulate **across** executions, or persist independently of what's on screen:

- a session HIT/MISS counter (like the one in the bundled Smart Cache panel),
- a running log of requests,
- rolling latency averages,
- a value computed once and shared between several surfaces.

Put any of that in a component-local `useState` / `useEffect` and it silently stops updating the moment the surface unmounts, then resets when it remounts. The fix is to move the state **out of the component tree** and feed it from something that runs regardless of what's mounted.

## The pattern

1. **Wire it up once at `WPGraphQLIDE_Window_Ready`.** This DOM event fires after `window.WPGraphQLIDE` (stores, registries, the `hooks` bus) is assembled. One-time setup belongs here — it runs exactly once per page and doesn't depend on any surface being mounted.
2. **Update the state from a hook or subscription that fires regardless of UI.** For per-execution data that's the [`wpgraphql-ide.afterExecute`](./actions-and-filters.md#wpgraphql-ideafterexecute) action — it fires once per completed execution (and never for aborted or short-circuited runs, so they don't miscount). Other sources work the same way: a `@wordpress/data` store subscription, an IDE lifecycle action, etc. The point is that the source is **not** a component effect.
3. **Hold the value in a module-scoped store** with a small subscribe API, and read it from any surface with React's [`useSyncExternalStore`](https://react.dev/reference/react/useSyncExternalStore). Those surfaces become display-only — remounting one just re-subscribes to the already-current value, and several surfaces can read the same store at once.

## Worked example

A response panel that shows the average response time for the session — and keeps counting no matter which tab you're looking at.

```js
import { useSyncExternalStore } from 'react';

window.addEventListener('WPGraphQLIDE_Window_Ready', () => {
	const { hooks, registerResponseExtensionTab } = window.WPGraphQLIDE;

	// Module-scoped — survives panel mount/unmount; cleared on page reload.
	let stats = { count: 0, totalMs: 0 };
	const subscribers = new Set();
	const subscribe = (fn) => {
		subscribers.add(fn);
		return () => subscribers.delete(fn);
	};
	const getSnapshot = () => stats;

	// Always fires, regardless of which response tab is mounted.
	hooks.addAction(
		'wpgraphql-ide.afterExecute',
		'my-plugin/latency',
		({ duration }) => {
			stats = {
				count: stats.count + 1,
				totalMs: stats.totalMs + duration,
			};
			subscribers.forEach((fn) => fn());
		}
	);

	// Display-only: it reads the running total, it never records it.
	const AvgLatencyPanel = () => {
		const s = useSyncExternalStore(subscribe, getSnapshot);
		if (!s.count) {
			return null;
		}
		return (
			<p>
				Avg {(s.totalMs / s.count).toFixed(0)} ms over {s.count} runs
				this session
			</p>
		);
	};

	registerResponseExtensionTab(
		'myLatency',
		{ title: 'Latency', content: AvgLatencyPanel, alwaysShow: true },
		60
	);
});
```

Open the panel, run a query, switch to the Debug or Headers tab, run a few more, switch back — the average reflects every run, because the recording lives in the `afterExecute` listener, not in the panel.

## A shipping example

The bundled Smart Cache panel uses this exact pattern for its "this session" HIT/MISS counter, so it's worth reading end to end:

- [`plugins/smart-cache-panel/src/smart-cache-panel.js`](../plugins/smart-cache-panel/src/smart-cache-panel.js) calls `initSmartCacheSessionTracking()` from its `WPGraphQLIDE_Window_Ready` listener.
- [`plugins/smart-cache-panel/src/components/SmartCachePanel.jsx`](../plugins/smart-cache-panel/src/components/SmartCachePanel.jsx) keeps the running totals in a module-scoped `sessionStats` value, records each HIT/MISS from a `wpgraphql-ide.afterExecute` listener, and the panel reads them with `useSyncExternalStore`. Because recording lives in the listener rather than the panel, the counter keeps climbing while you're on the Debug or Headers tab.

It also adds one wrinkle worth copying: it resets the totals when the cache-key inputs (query + variables + auth + operation) change, so the counter tracks the *current* query instead of mixing buckets.

## Why not just lift state to a parent component?

Because there is no always-mounted React parent you can register into from an extension. Every surface the registries expose is mounted conditionally. A module-scoped store sits *above* React entirely, which is exactly what you need: it outlives every mount/unmount and is shared across surfaces.

## Adapting it

The same shape works for any surface — an activity-bar panel that tabulates results, a status-bar badge showing a session aggregate, or a service shared by several panels. Only two things change:

- **The registration call** — `registerActivityBarPanel`, `registerStatusBarItem`, etc. instead of `registerResponseExtensionTab`. See [Access Functions](./access-functions.md).
- **The always-firing source** — `afterExecute` for per-execution data, or a store subscription / lifecycle action for other triggers. See [Actions & Filters](./actions-and-filters.md).

The module-scoped store and the `useSyncExternalStore` read stay the same.
