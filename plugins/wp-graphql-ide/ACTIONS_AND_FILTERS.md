# Actions & Filters

## PHP Actions

### `wpgraphql_ide_init`

Fires when WPGraphQL is available. Sub-plugins and third-party code should use this to initialize.

### `wpgraphql_ide_enqueue_script`

Fires just before the IDE render script is enqueued. Use this to enqueue extension scripts that depend on `wpgraphql-ide`. Receives the `$app_context` array.

## PHP Filters

### `wpgraphql_ide_external_fragments`

Return an array of GraphQL fragment SDL strings to make available to every query in the IDE. The IDE parses each outgoing query, finds unresolved fragment spreads, and prepends only the referenced fragment definitions before sending. Transitive references between external fragments are resolved (a fragment that spreads another fragment will pull both in). Unreferenced fragments are not sent over the wire.

A fragment with the same name as one defined in the user's query is skipped — the user's definition wins.

```php
add_filter( 'wpgraphql_ide_external_fragments', function ( array $fragments ): array {
	$fragments[] = 'fragment PostFields on Post { id databaseId title slug }';
	$fragments[] = 'fragment UserFields on User { id databaseId name avatar { url } }';
	return $fragments;
} );
```

After registering, any query that references `...PostFields` or `...UserFields` will have the matching definitions auto-injected. A query that references neither sends as-is.



### `wpgraphql_ide_capability_required`

Override the capability required to use the IDE. Default: `manage_graphql_ide`.

Consulted by `wpgraphql_ide_get_capability()` and `wpgraphql_ide_user_can()`, which back every IDE permission check — REST permission callbacks, post-type / taxonomy capability maps, post-meta and user-meta auth callbacks, the admin submenu, the public-endpoint trimming flag, and cross-user checks. Filter returns must be a non-empty string; non-string or empty values silently fall back to the default so a misconfigured filter never opens the gate to everyone.

```php
add_filter( 'wpgraphql_ide_capability_required', function () {
	return 'edit_others_posts';
} );
```

Hosts changing this filter should also grant the new capability to the roles that need it — the IDE's activation flow only adds `manage_graphql_ide` to `administrator`. Changing the filter without granting the new cap to anyone results in a fully gated IDE.

### `wpgraphql_ide_context`

Modify the app context object passed to `window.WPGRAPHQL_IDE_DATA.context`. Receives the `$app_context` array; return the modified array.

### `wpgraphql_ide_localized_data`

Inject keys into the IDE bootstrap data exposed at `window.WPGRAPHQL_IDE_DATA`. Receives `( array $data, array $app_context )` and must return `$data`. Used internally by the public-endpoint render to add `endpointMode` / `renderStandalone` / `isUserLoggedIn` / `loginUrl`, by the Document Settings module to add `documentSettings`, and by the Settings tab module to add `settingsRegistry`.

```php
add_filter( 'wpgraphql_ide_localized_data', function ( array $data, array $app_context ): array {
	$data['myPluginFlag'] = true;
	return $data;
}, 10, 2 );
```

## JavaScript Actions

All JavaScript hooks use a private `@wordpress/hooks` instance exposed on `window.WPGraphQLIDE.hooks`. Use `wp.hooks` functions against it:

```js
const { hooks } = window.WPGraphQLIDE;
hooks.addAction( 'wpgraphql-ide.init', 'my-plugin/boot', () => { /* … */ } );
```

### Lifecycle

- `wpgraphql-ide.init` — Fires after stores are registered and the registry is initialized. No args.
- `wpgraphql-ide.rendered` — Fires after the React root has mounted. No args.
- `wpgraphql-ide.destroyed` — Fires when the React root unmounts. No args.

### Registration

Each access function in `ACCESS_FUNCTIONS.md` fires one of these actions after a successful registration. Registration failures log to `console.error` and do not fire a paired action — see the [Migration from 4.x](#migration-from-4x) section if you previously relied on `register*Error` hooks.

| Registry function | Success action | Args |
| --- | --- | --- |
| `registerDocumentEditorToolbarButton` | `wpgraphql-ide.afterRegisterToolbarButton` | `name, config, priority` |
| `registerActivityBarPanel` | `wpgraphql-ide.afterRegisterActivityBarPanel` | `name, config, priority` |
| `registerResponseExtensionTab` | `wpgraphql-ide.afterRegisterResponseExtensionTab` | `name, config, priority` |
| `registerEditorBottomTab` | `wpgraphql-ide.afterRegisterEditorBottomTab` | `name, config, priority` |
| `registerStatusBarItem` | `wpgraphql-ide.afterRegisterStatusBarItem` | `name, config, priority` |
| `registerResponseViewMode` | `wpgraphql-ide.afterRegisterResponseViewMode` | `value, config, priority` |
| `registerResponseAction` | `wpgraphql-ide.afterRegisterResponseAction` | `name, config, priority` |
| `registerEditorAction` | `wpgraphql-ide.afterRegisterEditorAction` | `name, config, priority` |
| `registerDocumentTabAction` | `wpgraphql-ide.afterRegisterDocumentTabAction` | `name, config, priority` |
| `registerWorkspaceTabType` | `wpgraphql-ide.afterRegisterWorkspaceTabType` | `name, config` |
| `registerTopbarAction` | `wpgraphql-ide.afterRegisterTopbarAction` | `name, config, priority` |
| `registerPreference` | `wpgraphql-ide.afterRegisterPreference` | `key, config` |

### Notices

The IDE exposes its notice surface so extensions can publish or dismiss toasts without coupling to the notice store directly.

- `wpgraphql-ide.notice` — Publish a notice. Args: `( payload, type = 'default' )`. `payload` is either a plain string (the message) or a descriptor object:
  - `content` (string|Element): Notice body.
  - `id` (string, optional): Reusing an id replaces the previous notice in place instead of stacking (used by the Refresh Schema "milestones" pattern). Implicit ids are unique per call so the back-compat string form keeps working.
  - `actions` (Array, optional): `[{ label, onClick }]` — link-style buttons rendered inside the snackbar (Gutenberg `SnackbarList` shape).
  - `explicitDismiss` (boolean, optional): Disable the auto-timeout. Pair with `actions` when the user needs time to act on an offer.
  - `icon` (Element, optional): Custom icon rendered in the snackbar.

  Common `type` values: `'default'`, `'error'`, `'warning'`. The renderer applies a styling hook per type; unknown values pass through as default.

- `wpgraphql-ide.notice.dismiss` — Dismiss a notice. Args: `( id )`.

```js
const { hooks } = window.WPGraphQLIDE;

hooks.doAction( 'wpgraphql-ide.notice', 'Saved!' );

// Replace the same notice in place by re-using its id:
hooks.doAction( 'wpgraphql-ide.notice', { id: 'my-plugin/status', content: 'Working…' } );
hooks.doAction( 'wpgraphql-ide.notice', { id: 'my-plugin/status', content: 'Done.' } );

// Snackbar with action buttons that requires explicit dismissal:
hooks.doAction( 'wpgraphql-ide.notice', {
	id: 'my-plugin/offer',
	content: 'Found a related snippet.',
	actions: [ { label: 'Insert', onClick: () => insertSnippet() } ],
	explicitDismiss: true,
} );

// Error-styled notice:
hooks.doAction( 'wpgraphql-ide.notice', 'Failed to refresh', 'error' );

// Imperative dismiss:
hooks.doAction( 'wpgraphql-ide.notice.dismiss', 'my-plugin/offer' );
```

## JavaScript Filters

### `wpgraphql-ide.executeRequest`

Filter the outbound GraphQL request payload just before the fetcher runs. Plugins use this to inject auth tokens, transform variables, rewrite headers, switch the HTTP method per query, and similar request-side concerns.

**Args:**

- `request` (Object): `{ query, variables, operationName, headers, httpMethod }`. `headers` and `variables` are the parsed object forms (already JSON.parse'd from the editor strings). Mutate-and-return is fine; you can also return a brand new object as long as the shape matches.

Consumers that only want to observe should return the input untouched. Returning `undefined` or `null` falls back to the input object so a broken filter never wedges execution.

```js
const { hooks } = window.WPGraphQLIDE;

hooks.addFilter(
	'wpgraphql-ide.executeRequest',
	'my-plugin/inject-trace-header',
	(request) => ({
		...request,
		headers: {
			...request.headers,
			'X-Trace-Id': crypto.randomUUID(),
		},
	})
);
```

### `wpgraphql-ide.executeResponse`

Filter the parsed response object before it lands in the store / status bar / extension tabs. Plugins use this to normalize error shapes, inject synthetic `extensions` for downstream extension tabs to render, redact sensitive fields, or pass-through observe with no mutation.

**Args:**

- `response` (Object): Parsed GraphQL response (`{ data, errors, extensions }`).
- `request` (Object): The (filtered) request that produced this response — same shape `executeRequest` sees. Use it to branch on operation name, query content, etc.

Fires on both success and transport failure (the failure path passes `{ errors: [{ message }] }` through the filter so observers see errors through the same channel as successes). Returning `undefined` or `null` falls back to the input.

```js
hooks.addFilter(
	'wpgraphql-ide.executeResponse',
	'my-plugin/mark-cache-hit',
	(response, request) => {
		if (response?.extensions?.graphqlSmartCache?.cacheStatus === 'HIT') {
			return {
				...response,
				extensions: {
					...response.extensions,
					myPluginCacheBadge: { hit: true, op: request.operationName },
				},
			};
		}
		return response;
	}
);
```

## Execute lifecycle

In addition to the two filters above, the execute flow fires one action for observability:

### `wpgraphql-ide.afterExecute`

Fire-and-forget action with the full request + response envelope. Fires on both success and transport failure. Used by analytics, query-log, and telemetry plugins.

**Args (single payload object):**

| Field | Type | Notes |
| --- | --- | --- |
| `request` | Object | The (filtered) outbound payload — `{ query, variables, operationName, headers, httpMethod }`. |
| `result` | Object | The (filtered) parsed response. On failure: `{ errors: [{ message }] }`. |
| `responseHeaders` | Object\|null | Response headers map (success only). |
| `httpStatus` | number\|null | HTTP status code (success only). |
| `responseSize` | number\|null | Response body size in bytes (success only). |
| `duration` | number | Wall-clock time in milliseconds. |
| `status` | `'success' \| 'error'` | Operation outcome — `'error'` if the response carries `errors` *or* the transport failed. |
| `ok` | boolean | `true` on transport success; `false` on transport failure. |
| `error` | Error | Transport-level error object (failure path only). |

```js
hooks.addAction(
	'wpgraphql-ide.afterExecute',
	'my-plugin/analytics',
	({ request, result, duration, status }) => {
		// Don't await — observer must not block the response render.
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

### Pattern: state that outlives a component

Most extension surfaces mount **conditionally** — an activity-bar panel renders while its panel is selected; response panels, view modes, and status-bar items while their tab/mode is on screen; editor-bottom tabs while expanded; workspace tabs while open. When a surface isn't visible the IDE unmounts its component and discards its React state and effects.

Response panels are where this bites most often, but the rule is general: any state that must accumulate *across* executions, or otherwise persist independently of what's on screen — a session HIT/MISS counter, a running request log, rolling latency averages, a computation cached and shared between surfaces — cannot live in a component-local `useState` / `useEffect`. It silently stops updating the moment the surface unmounts, then resets when it remounts.

Keep that state outside the component tree and drive it from something that runs regardless of what's mounted:

1. **Wire it up once at `WPGraphQLIDE_Window_Ready`.** That DOM event fires after `window.WPGraphQLIDE` (stores, registries, the `hooks` bus) is assembled. One-time setup belongs here — it runs exactly once per page and doesn't depend on any surface being mounted.
2. **Update it from a hook or subscription that fires regardless of UI.** For per-execution data that's `wpgraphql-ide.afterExecute` (fires once per completed execution; never for aborted or short-circuited runs, so they don't miscount). Other sources work the same way — a `@wordpress/data` store subscription, an IDE lifecycle action, etc. The point is that the source isn't a component effect.
3. **Hold the value in a module-scoped store** with a small subscribe API, and read it from any surface with React's `useSyncExternalStore`. Those surfaces become display-only — remounting one just re-subscribes to the already-current value, and several surfaces can read the same store at once.

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

The IDE's built-in Smart Cache "this session" HIT/MISS counter works exactly this way: the panel renders the totals, but recording happens in an `afterExecute` listener registered at window-ready — so the count keeps climbing while you're looking at the Debug or Headers tab. The same shape applies to any surface — an activity-bar panel that tabulates results, a status-bar badge showing a session aggregate, or a service shared by several surfaces. Only the registration call and the always-firing source change; the module-scoped store and `useSyncExternalStore` read stay the same.

## Migration from 4.x

A quick lookup for extension authors upgrading from 4.x. Hooks not listed below are unchanged.

### PHP

| 4.x hook | Status in 5.0 | What to do |
| --- | --- | --- |
| `wpgraphql_ide_external_fragments` (filter) | **Behavior change** | Still supported. 5.0 smart-merges instead of always-prepending: only fragments referenced by an outgoing query (transitively) are injected, and a fragment defined in the user's query wins over an external definition with the same name. See the [filter docs above](#wpgraphql_ide_external_fragments) for the current contract. |
| `wpgraphql_ide_endpoint_api_params` (filter) | **Removed** | The public-endpoint GET-param allow-list is now a literal in `public-endpoint.php`. If you need to extend it for a custom persisted-query layer, open an issue — this hook had no external consumers, so the contract is open to re-introduction if there's a real use case. |
| `wpgraphql_ide_register_document_settings` (action) | **Removed** | The built-in Document Settings fields are registered via `add_action('init', ..., 11)` directly. The Document Settings surface is migrating into WPGraphQL Smart Cache, so extending fields from outside the IDE is now a Smart-Cache-side concern. |
| `graphiql_external_fragments` (legacy alias) | **Removed** | Was an alias for the removed `wpgraphql_ide_external_fragments`. See above. |
| `enqueue_graphiql_extension` (legacy alias) | **Removed** | Hook `wpgraphql_ide_enqueue_script` directly. |
| `graphiql_rendered` (legacy alias) | **Removed** | Use the JS action `wpgraphql-ide.rendered` via `wp.hooks.addAction`. |
| `graphiql_toolbar_before_buttons` / `graphiql_toolbar_after_buttons` | **Removed** | Register toolbar items via the JS API: `registerDocumentEditorToolbarButton()`. See `ACCESS_FUNCTIONS.md`. |
| `wpgraphql_ide_capability_required` (filter) | **Behavior change** | The filter is now honored at every IDE permission check (REST, post-type/taxonomy caps, post-meta, user-meta, admin menu, public-endpoint trim). In 4.x it was consulted only at the admin-menu gate. Hosts already relying on it will find their override actually works end-to-end. |

### JavaScript

The ten `register*Error` actions are all removed. Registration failures still log to `console.error`, which is the actionable signal — there was never a productive consumer pattern for the error actions.

| 4.x action | Status in 5.0 | What to do |
| --- | --- | --- |
| `wpgraphql-ide.registerPreferenceError` | **Removed** | Failures log to `console.error`. |
| `wpgraphql-ide.registerToolbarButtonError` | **Removed** | Failures log to `console.error`. |
| `wpgraphql-ide.registerActivityBarPanelError` | **Removed** | Failures log to `console.error`. |
| `wpgraphql-ide.registerResponseExtensionTabError` | **Removed** | Failures log to `console.error`. |
| `wpgraphql-ide.registerEditorBottomTabError` | **Removed** | Failures log to `console.error`. |
| `wpgraphql-ide.registerStatusBarItemError` | **Removed** | Failures log to `console.error`. |
| `wpgraphql-ide.registerResponseViewModeError` | **Removed** | Failures log to `console.error`. |
| `wpgraphql-ide.registerResponseActionError` | **Removed** | Failures log to `console.error`. |
| `wpgraphql-ide.registerEditorActionError` | **Removed** | Failures log to `console.error`. |
| `wpgraphql-ide.registerDocumentTabActionError` | **Removed** | Failures log to `console.error`. |

The matching `wpgraphql-ide.afterRegister*` success actions are unchanged.

### New in 5.0

If you were extending the 4.x IDE, these new surfaces are worth knowing about:

- **Notices** — `wpgraphql-ide.notice` / `wpgraphql-ide.notice.dismiss` JS actions for publishing toasts without coupling to the notice store.
- **Execute lifecycle** — `wpgraphql-ide.executeRequest` (filter) and `wpgraphql-ide.executeResponse` (filter) hook the request and response on every execution, plus `wpgraphql-ide.afterExecute` (action) for analytics/observability.
- **Localized data** — `wpgraphql_ide_localized_data` (PHP filter) is the canonical way to inject keys into `window.WPGRAPHQL_IDE_DATA`. Both the public-endpoint mode and the Document Settings module use it.
- **Registry APIs** — twelve `register*` functions for panels, toolbar buttons, status-bar items, response-view modes, response/editor/document-tab actions, workspace tab types, topbar actions, and preferences. See `ACCESS_FUNCTIONS.md`.

