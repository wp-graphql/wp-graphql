---
title: "Hooking the Execute Lifecycle"
description: "Observe or rewrite every IDE request and response with the executeRequest / executeResponse filters and the afterExecute action."
---

Every time the IDE runs a query it passes through three JavaScript hooks on the shared `window.WPGraphQLIDE.hooks` bus. Two are filters (rewrite the request, rewrite the response); one is an action (observe the completed execution). Use them to inject auth, transform payloads, synthesize data for your panels, or send analytics.

```
editor → [executeRequest filter] → fetch → [executeResponse filter] → store/UI
                                                      └─→ [afterExecute action]
```

## `executeRequest` — rewrite the outbound request

Fires just before the fetcher runs, with `{ query, variables, operationName, headers, httpMethod }` (variables and headers already parsed to objects). Mutate and return it — or return it untouched to just observe.

```js
window.addEventListener('WPGraphQLIDE_Window_Ready', () => {
	const { hooks } = window.WPGraphQLIDE;

	hooks.addFilter(
		'wpgraphql-ide.executeRequest',
		'my-plugin/inject-trace-header',
		(request) => ({
			...request,
			headers: { ...request.headers, 'X-Trace-Id': crypto.randomUUID() },
		})
	);
});
```

A broken filter that returns `undefined`/`null` falls back to the input, so it can't wedge execution.

## `executeResponse` — rewrite the parsed response

Fires after the response is parsed (on both success and transport failure), with `(response, request)`. Use it to normalize errors, redact fields, or **inject synthetic `extensions`** that one of your [response panels](./adding-a-response-panel.md) then renders:

```js
hooks.addFilter(
	'wpgraphql-ide.executeResponse',
	'my-plugin/annotate',
	(response, request) => ({
		...response,
		extensions: {
			...response.extensions,
			myExtension: { op: request.operationName, at: Date.now() },
		},
	})
);
```

This is the seam that lets a response panel exist even when the server doesn't (yet) emit the payload.

## `afterExecute` — observe a completed execution

A fire-and-forget action carrying the full envelope: `{ request, result, responseHeaders, httpStatus, responseSize, duration, status, ok, error }`. It fires once per completed execution (success or transport failure), and **not** for aborted or short-circuited runs. Ideal for analytics, query logs, and session aggregates.

```js
hooks.addAction(
	'wpgraphql-ide.afterExecute',
	'my-plugin/analytics',
	({ request, duration, status }) => {
		// Don't await — an observer must not block the response render.
		void fetch('/my-analytics', {
			method: 'POST',
			body: JSON.stringify({
				op: request.operationName,
				ms: duration,
				ok: status === 'success',
			}),
		});
	}
);
```

Because `afterExecute` fires regardless of which UI surface is mounted, it's also the right place to record anything that must survive panel unmounts — see [Tracking State Across Executions](./tracking-state-across-executions.md).

## Reference

Full argument tables and failure-path semantics: [`executeRequest`](./actions-and-filters.md#wpgraphql-ideexecuterequest), [`executeResponse`](./actions-and-filters.md#wpgraphql-ideexecuteresponse), and [`afterExecute`](./actions-and-filters.md#wpgraphql-ideafterexecute).
