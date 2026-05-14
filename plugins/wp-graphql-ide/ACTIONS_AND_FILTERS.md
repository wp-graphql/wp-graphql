# Actions & Filters

## PHP Actions

### `wpgraphql_ide_init`

Fires when WPGraphQL is available. Sub-plugins and third-party code should use this to initialize.

### `wpgraphql_ide_enqueue_script`

Fires just before the IDE render script is enqueued. Use this to enqueue extension scripts that depend on `wpgraphql-ide`. Receives the `$app_context` array.

### `wpgraphql_ide_register_document_settings`

Fires on `init` (priority 11), after `graphql_ide_query` and its taxonomies are registered. Use this inside a callback to call `register_graphql_document_setting_field()` and contribute fields to the per-document Settings drawer. Receives no args. See `API_SURFACE.md` for the field registration API.

```php
add_action( 'wpgraphql_ide_register_document_settings', function () {
	register_graphql_document_setting_field( 'tags', [
		'label'   => __( 'Tags', 'my-plugin' ),
		'type'    => 'tag_list',
		'storage' => [ 'kind' => 'taxonomy', 'key' => 'my_query_tag' ],
	] );
} );
```

## PHP Filters

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

### `wpgraphql_ide_external_fragments`

Add external GraphQL fragments (strings) that are auto-merged into every query.

### `wpgraphql_ide_endpoint_api_params`

When the public IDE at the GraphQL endpoint URL is enabled, browser GETs that include any of these query-string params are treated as API calls and pass through to WPGraphQL's JSON handler instead of rendering the IDE shell. Default list:

- `query`, `variables`, `operationName`, `extensions` (GraphQL-over-HTTP spec)
- `queryId` (WPGraphQL Smart Cache persisted-query convention)

Extensions that introduce additional GET params (custom persisted-query layers, etc.) should hook this filter to keep their requests on the JSON path.

```php
add_filter( 'wpgraphql_ide_endpoint_api_params', function ( array $params ) {
	$params[] = 'myCustomParam';
	return $params;
} );
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

Each access function in `ACCESS_FUNCTIONS.md` fires one of these hooks on success and (where present) a paired error hook on failure. Args are listed for the success hook; the error hook adds a trailing `error` argument.

| Registry function | Success hook | Error hook | Args |
| --- | --- | --- | --- |
| `registerDocumentEditorToolbarButton` | `wpgraphql-ide.afterRegisterToolbarButton` | `wpgraphql-ide.registerToolbarButtonError` | `name, config, priority [, error]` |
| `registerActivityBarPanel` | `wpgraphql-ide.afterRegisterActivityBarPanel` | `wpgraphql-ide.registerActivityBarPanelError` | `name, config, priority [, error]` |
| `registerResponseExtensionTab` | `wpgraphql-ide.afterRegisterResponseExtensionTab` | `wpgraphql-ide.registerResponseExtensionTabError` | `name, config, priority [, error]` |
| `registerEditorBottomTab` | `wpgraphql-ide.afterRegisterEditorBottomTab` | `wpgraphql-ide.registerEditorBottomTabError` | `name, config, priority [, error]` |
| `registerStatusBarItem` | `wpgraphql-ide.afterRegisterStatusBarItem` | `wpgraphql-ide.registerStatusBarItemError` | `name, config, priority [, error]` |
| `registerResponseViewMode` | `wpgraphql-ide.afterRegisterResponseViewMode` | `wpgraphql-ide.registerResponseViewModeError` | `value, config, priority [, error]` |
| `registerResponseAction` | `wpgraphql-ide.afterRegisterResponseAction` | `wpgraphql-ide.registerResponseActionError` | `name, config, priority [, error]` |
| `registerEditorAction` | `wpgraphql-ide.afterRegisterEditorAction` | `wpgraphql-ide.registerEditorActionError` | `name, config, priority [, error]` |
| `registerDocumentTabAction` | `wpgraphql-ide.afterRegisterDocumentTabAction` | `wpgraphql-ide.registerDocumentTabActionError` | `name, config, priority [, error]` |
| `registerWorkspaceTabType` | `wpgraphql-ide.afterRegisterWorkspaceTabType` | _(no paired error hook — failures log to `console.error` only)_ | `name, config` |
| `registerTopbarAction` | `wpgraphql-ide.afterRegisterTopbarAction` | _(no paired error hook — failures log to `console.error` only)_ | `name, config, priority` |
| `registerPreference` | `wpgraphql-ide.afterRegisterPreference` | `wpgraphql-ide.registerPreferenceError` | `key, config [, error]` |

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

None currently. The hook instance supports `applyFilters` for future use.
