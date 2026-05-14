# Access Functions

Access Functions abstract several layers of complexity and provide simpler ways to accomplish common tasks. They are modeled around core WordPress functions like `register_post_type`, `register_taxonomy`, etc.

All access functions are available on `window.WPGraphQLIDE` after the `WPGraphQLIDE_Window_Ready` event fires. Each `register*` function dispatches to one of the IDE's public Redux stores (see `src/stores/`); the same registration path is used by the IDE's own built-in features (`src/registry/index.js`) and by third-party extensions.

> **Backward compatibility.** Each access function below is part of the IDE's public API. Signatures and config shapes will only change with a major version bump (and a corresponding entry in `CHANGELOG.md`).

## registerPreference

Declare a preference key so the IDE knows where to persist it. Plugins use this to add their own settings to the same scope-aware persistence the IDE's built-in prefs use — no separate REST endpoint or localStorage scheme required.

Two scopes:

- `'device'` — localStorage on the current browser / render context. Cheap, anonymous-friendly, lost on cache clear. Right for UI chrome (panel widths, last-open tab, view modes, etc.).
- `'user'` — server user-meta via `/wp/v2/users/me`. Cross-device for the logged-in user; doesn't work for anonymous endpoint visitors. Right for identity-bound data.

Once registered, the same `setPreference(key, value)` / `getPreference(key)` / `readDevicePreference(key)` calls the IDE uses for its own prefs route to the right backing store automatically.

**Parameters:**

- `key` (string): Preference key. Plugins should prefix with a short plugin identifier (`'my_plugin_sidebar_width'`, not `'sidebar_width'`).
- `config` (Object):
  - `scope` (`'device' | 'user'`): Where to persist.

**Key format:**

- `device`-scope keys can be any non-empty string.
- `user`-scope keys are serialized into WP user-meta and must match `[A-Za-z_][A-Za-z0-9_]*`. Recommended convention: stick to `[a-z0-9_]` for both scopes so a pref can move between them without renaming.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterPreference` on success. Args: `key, config`.
- `wpgraphql-ide.registerPreferenceError` on failure. Args: `key, config, error`.

**Example:**

```js
const { registerPreference, setPreference, getPreference } = window.WPGraphQLIDE;

// Device-scope: instant, anonymous-friendly.
registerPreference('my_plugin_sidebar_width', { scope: 'device' });
await setPreference('my_plugin_sidebar_width', 240);

// User-scope: server-persisted, cross-device.
registerPreference('my_plugin_default_view', { scope: 'user' });
await setPreference('my_plugin_default_view', 'compact');
const view = await getPreference('my_plugin_default_view');
```

### Constants: `PREFERENCE_KEYS`

The built-in preference keys are also exported as a frozen constants object on `window.WPGraphQLIDE.PREFERENCE_KEYS` — use these instead of bare string literals at callsites so typos become obvious and a future rename is a single edit.

```js
const { PREFERENCE_KEYS, setPreference } = window.WPGraphQLIDE;
await setPreference(PREFERENCE_KEYS.RESPONSE_VIEW_MODE, 'formatted');
```

Available constants: `RESPONSE_VIEW_MODE`, `RESPONSE_TAB_ORDER`, `PANEL_ORDER`, `LEFT_PANEL`, `OPEN_TABS`, `ACTIVE_TAB`, `VISIBLE_PANEL`, `EDITOR_BOTTOM_COLLAPSED`, `EDITOR_BOTTOM_ACTIVE_TAB`, `RESPONSE_BOTTOM_COLLAPSED`, `RESPONSE_BOTTOM_ACTIVE_TAB`, `PERSONAL_COLLECTIONS`, `COLLECTION_SORT_MODES`, `COLLECTION_ORDER`, `SEEN_SHARED_COLLECTIONS`, `COLLAPSED_NOTICES`, `SECTION_STATES`.

## setPreference / getPreference / readDevicePreference

Read and write preference values. Route by registered scope automatically.

- `setPreference(key, value): Promise<*>` — Write. Device writes resolve immediately; user writes round-trip through REST.
- `getPreference(key): Promise<*>` — Read. Async because user-scope reads round-trip through REST.
- `readDevicePreference(key): *` — **Sync** read for `device`-scope only. Returns `undefined` for `user`-scope keys. Use in `useState(() => readDevicePreference(...))` lazy initializers and other paths that can't `await`.

All three accept a key registered via `registerPreference` (or one of the built-ins). Unregistered keys default to `user` scope.

## registerDocumentEditorToolbarButton

Registers a new toolbar button in the query editor toolbar.

**Parameters:**

- `name` (string): Unique identifier for the button.
- `config` (Object): Button configuration object.
  - `label` (string): Tooltip / aria-label.
  - `children` (string|Element): Visible label inside the menu item.
  - `onClick` (Function): Handler fired on click.
  - `mutates` (boolean, optional): Set `true` if the action edits the query buffer (e.g. format, merge fragments). Such buttons are auto-hidden when the active document is published/read-only. Default: `false`.
- `priority` (number, optional): Lower numbers render first. Default: `10`.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterToolbarButton` on success
- `wpgraphql-ide.registerToolbarButtonError` on failure

**Example:**

```js
const { registerDocumentEditorToolbarButton } = window.WPGraphQLIDE;

registerDocumentEditorToolbarButton('my-button', {
  label: 'My Button',
  children: 'My Button',
  onClick: () => console.log('clicked'),
  // Hide on read-only docs because this action would change the query.
  mutates: true,
}, 20);
```

## registerActivityBarPanel

Registers a panel in the left activity bar sidebar.

Activity bar panels are global to the IDE (not tied to a specific document). Built-in panels include Saved Queries (priority 1), Docs Explorer (priority 5), and History (priority 30).

**Parameters:**

- `name` (string): Unique identifier for the panel.
- `config` (Object): Panel configuration.
  - `title` (string): Human-readable panel title shown in the header.
  - `icon` (Function): React component rendering the panel's icon.
  - `content` (Function): React component rendering the panel's content.
  - `headerAction` (Function, optional): React component rendered in the panel header (right side). Used by the built-in Saved Queries panel for the "New collection" button.
- `priority` (number, optional): Lower numbers render first. Default: `10`.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterActivityBarPanel` on success
- `wpgraphql-ide.registerActivityBarPanelError` on failure

**Example:**

```js
const { registerActivityBarPanel } = window.WPGraphQLIDE;
import { Icon, plugins } from '@wordpress/icons';

registerActivityBarPanel('my-panel', {
  title: 'My Panel',
  icon: () => <Icon icon={plugins} />,
  content: () => <div>Panel content here</div>,
}, 20);
```

## registerResponseExtensionTab

Registers a tab in the response pane's Extensions section.

Most tabs map 1:1 onto a key in the GraphQL response `extensions` object — Tracing reads `extensions.tracing`, Debug reads `extensions.debug`, etc. The tab is only shown when the latest response contains that key, and the `content` component receives the value at that key as its `data` prop. This is how plugins like WPGraphQL Smart Cache, ACF, or WooCommerce surface their extension data.

The built-in `errors` and `headers` tabs use the same registry but flag themselves with `alwaysShow: true` (they describe the response itself, not response.extensions, so their data is sourced from synthetic slots — see `slotData` in `ResponseContent.jsx`).

**Parameters:**

- `name` (string): Extension key in the response (e.g. `"debug"`, `"graphqlSmartCache"`).
- `config` (Object): Tab configuration.
  - `title` (string|Function): Human-readable tab title. Pass a function `({ data, response }) => string` to surface a count or other state (e.g. `"Errors (3)"`).
  - `content` (Function): React component receiving `{ data, response }`.
  - `alwaysShow` (boolean, optional): When `true`, the tab renders even if there's no matching key in `response.extensions`. Used by the built-in `errors` / `headers` tabs. Default: `false`.
- `priority` (number, optional): Lower numbers render first. Default: `10`.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterResponseExtensionTab` on success
- `wpgraphql-ide.registerResponseExtensionTabError` on failure

**Example:**

```js
const { registerResponseExtensionTab } = window.WPGraphQLIDE;

registerResponseExtensionTab('graphqlSmartCache', {
  title: 'Smart Cache',
  content: ({ data, response }) => (
    <div>
      <p>Cache status: {data?.cacheStatus}</p>
    </div>
  ),
}, 50);
```

## registerEditorBottomTab

Registers a tab in the editor's bottom tools area — the row that hosts the built-in **Variables** and **Headers** tabs beneath the query editor. The tab is rendered by `EditorPane` via `OverflowTabs`.

**Parameters:**

- `name` (string): Unique tab identifier.
- `config` (Object): Tab configuration.
  - `title` (string|Function): Human-readable tab title. Pass a function for dynamic titles (e.g. count badges).
  - `content` (Function): React component receiving editor-context props: `{ query, variables, onVariablesChange, variableToType, headers, onHeadersChange, response, activeDocument }`. Built-in tabs use what they need; plugins can pull from props or read the stores directly via `useSelect`.
- `priority` (number, optional): Lower numbers render first. Default: `10`.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterEditorBottomTab` on success
- `wpgraphql-ide.registerEditorBottomTabError` on failure

**Example:**

```js
const { registerEditorBottomTab } = window.WPGraphQLIDE;

registerEditorBottomTab('inspector', {
  title: 'Inspector',
  content: ({ query, activeDocument }) => (
    <div>Inspecting: {activeDocument?.title}</div>
  ),
}, 30);
```

## registerStatusBarItem

Registers an item in the response toolbar's status row — alongside the built-in HTTP status code, duration, size, resolver count, and N+1 warning badges. Useful for surfacing live response signals: cache hit/miss, schema warnings, custom counts, etc.

**Parameters:**

- `name` (string): Unique item identifier.
- `config` (Object): Item configuration.
  - `render` (Function): Callback returning a `ReactNode` or `null`. Return `null` to hide the item for the current response. Receives:
    `{ response, parsedResponse, responseStatus, responseDuration, responseSize, isFetching, focusResponseTab(name) }`.
- `priority` (number, optional): Lower numbers render first (left to right). Default: `10`.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterStatusBarItem` on success
- `wpgraphql-ide.registerStatusBarItemError` on failure

**Example:**

```js
const { registerStatusBarItem } = window.WPGraphQLIDE;

registerStatusBarItem('cache-hit', {
  render: ({ response }) => {
    const hit = response?.extensions?.graphqlSmartCache?.cacheStatus === 'HIT';
    return hit ? <span title="Cache HIT">⚡</span> : null;
  },
}, 60);
```

## registerResponseViewMode

Registers a response viewer mode — the JSON / Table toggle in the response toolbar. Each mode owns both the toggle-button label and the top-pane content while it's active. Built-in modes: `formatted` (JSON, priority 10) and `table` (priority 20).

**Parameters:**

- `value` (string): Unique mode value (e.g. `"formatted"`, `"table"`).
- `config` (Object): Mode configuration.
  - `label` (string): Toggle-button label.
  - `render` (Function): Callback returning a `ReactNode`. Receives:
    `{ response, parsed, dataScope, viewerContent }`, where `viewerContent` is the pre-formatted string the JSON viewer uses (already filtered by data-scope).
- `priority` (number, optional): Lower numbers render first (left to right). Default: `10`.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterResponseViewMode` on success
- `wpgraphql-ide.registerResponseViewModeError` on failure

**Example:**

```js
const { registerResponseViewMode } = window.WPGraphQLIDE;

registerResponseViewMode('raw', {
  label: 'Raw',
  render: ({ response }) => <pre>{JSON.stringify(response, null, 0)}</pre>,
}, 30);
```

## registerResponseAction

Registers an item in the response toolbar's kebab dropdown ("Response options" — currently houses the data-scope toggle). Useful for "Copy as cURL", "Export to Postman", etc.

Items with the same `group` string render under a `<MenuGroup label={group}>` (Gutenberg post-editor pattern). Omit `group` to land in the unlabelled top group.

**Parameters:**

- `name` (string): Unique action identifier.
- `config` (Object): Action configuration.
  - `label` (string|Function): Item label, or `(ctx) => string` for dynamic labels.
  - `onClick` (Function): Click handler `(ctx) => void`. Receives:
    `{ dataScope, setDataScope, response, parsedResponse, closeMenu }`.
  - `isSelected` (Function, optional): `(ctx) => boolean` — render a checkmark when true.
  - `isDisabled` (Function, optional): `(ctx) => boolean` — disable the item.
  - `isDestructive` (boolean, optional): Render the item with the destructive style.
  - `group` (string, optional): Group label — items with the same label render under one `MenuGroup`.
  - `predicate` (Function, optional): `(ctx) => boolean` — hide when this returns false.
- `priority` (number, optional): Sort order within group. Lower renders first. Default: `10`.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterResponseAction` on success
- `wpgraphql-ide.registerResponseActionError` on failure

**Example:**

```js
const { registerResponseAction } = window.WPGraphQLIDE;

registerResponseAction('copy-as-curl', {
  label: 'Copy as cURL',
  group: 'Export',
  onClick: ({ response, closeMenu }) => {
    navigator.clipboard.writeText(buildCurl(response));
    closeMenu();
  },
}, 30);
```

## registerEditorAction

Registers an item in the editor toolbar's kebab dropdown — sits below the registered editor toolbar buttons (Prettify, etc.) and above the Save / Publish buttons. The IDE's built-in **Share link…**, **Rename query**, and **Duplicate as draft** items are registered through this API. Ideal for plugins that want to add "Open in Studio", "Copy operation", "Run on staging", etc.

Same shape as `registerResponseAction`. Click handlers receive an editor-scoped ctx:
`{ query, activeDocument, isPublished, isTempId, endpointMode, openShareDialog, openRenameDialog, duplicateAsDraft, addNotice, closeMenu }`.

**Parameters:**

- `name` (string): Unique action identifier.
- `config` (Object): Action configuration (see `registerResponseAction` for the shared shape).
  - `label` (string|Function)
  - `onClick` (Function): `(ctx) => void`.
  - `isSelected` (Function, optional)
  - `isDisabled` (Function, optional)
  - `isDestructive` (boolean, optional)
  - `group` (string, optional)
  - `predicate` (Function, optional)
- `priority` (number, optional): Default: `10`.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterEditorAction` on success
- `wpgraphql-ide.registerEditorActionError` on failure

**Example:**

```js
const { registerEditorAction } = window.WPGraphQLIDE;

registerEditorAction('run-on-staging', {
  label: 'Run on staging',
  onClick: ({ query, addNotice, closeMenu }) => {
    closeMenu();
    fetch('https://staging.example.com/graphql', {
      method: 'POST',
      body: JSON.stringify({ query }),
    }).then(() => addNotice('Sent to staging'));
  },
  // Only enable when there's a query to send.
  isDisabled: ({ query }) => !query?.trim(),
}, 40);
```

## registerDocumentTabAction

Registers an item in the document-tabs kebab dropdown (Close active / Close inactive / Close all). Plugins can extend with "Pin tab", "Lock tab", "Move all to collection", etc.

Same shape as `registerEditorAction`. Click handlers receive a tab-scoped ctx:
`{ activeId, tabs, onClose, onCloseOthers, onCloseAll, closeMenu }`.

**Parameters:**

- `name` (string): Unique action identifier.
- `config` (Object): Action configuration (same shape as `registerEditorAction`).
- `priority` (number, optional): Default: `10`.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterDocumentTabAction` on success
- `wpgraphql-ide.registerDocumentTabActionError` on failure

**Example:**

```js
const { registerDocumentTabAction } = window.WPGraphQLIDE;

registerDocumentTabAction('pin-tab', {
  label: 'Pin tab',
  onClick: ({ activeId, closeMenu }) => {
    pinTab(activeId);
    closeMenu();
  },
  isDisabled: ({ activeId }) => !activeId,
}, 5);
```

## registerWorkspaceTabType

Registers a workspace tab type with a content renderer. Once registered, the tab type can be opened with [`openWorkspaceTab`](#openworkspacetab). The built-in Settings workspace tab is registered through this API.

**Parameters:**

- `name` (string): Unique tab-type identifier.
- `config` (Object): Tab-type configuration.
  - `title` (string): Human-readable display name (used as the tab label when no explicit title is passed to `openWorkspaceTab`).
  - `content` (Function): React component rendered as the workspace content.
  - `icon` (Function, optional): React component rendering an icon next to the title.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterWorkspaceTabType` on success

**Example:**

```js
const { registerWorkspaceTabType } = window.WPGraphQLIDE;

registerWorkspaceTabType('my-extension-panel', {
  title: 'My Extension',
  content: () => <MyExtensionScreen />,
});
```

## openWorkspaceTab

Opens a workspace tab of a registered type. If a tab with the given `id` is already open, it's switched to instead of being duplicated.

**Parameters:**

- `typeName` (string): Tab-type name (must have been registered via [`registerWorkspaceTabType`](#registerworkspacetabtype)).
- `options` (Object, optional): Options.
  - `id` (string): Unique tab ID. Reusing an ID switches to the existing tab. Defaults to `${typeName}-${Date.now()}` (one new tab per call).
  - `title` (string): Display title for the tab. Falls back to the registered type's `title`.

**Example:**

```js
const { openWorkspaceTab } = window.WPGraphQLIDE;

// Singleton tab — every call switches to the same tab.
openWorkspaceTab('my-extension-panel', {
  id: 'my-extension-singleton',
  title: 'My Extension',
});
```

## registerTopbarAction

Registers a topbar action button in the global top bar (right side, after schema refresh). Clicking one opens or switches to a singleton workspace tab. The built-in **Refresh Schema** and **WPGraphQL Settings** buttons are registered through this API.

**Parameters:**

- `name` (string): Unique action identifier.
- `config` (Object): Action configuration.
  - `title` (string): Tooltip / aria-label text.
  - `icon` (Function): React component rendering the icon.
  - `tabType` (string, optional): Workspace tab type to open on click. Must already be registered via `registerWorkspaceTabType`.
  - `tabId` (string, optional): Singleton tab ID (defaults to `tabType`).
  - `onClick` (Function, optional): Custom click handler. Receives a context object with helpers like `refetchSchema` (used by the built-in Refresh Schema action). Use this instead of `tabType` for actions that don't open a workspace tab.
  - `isDisabled` (Function, optional): `(ctx) => boolean` — disable the button.
  - `className` (Function, optional): `(ctx) => string` — apply a class to the button (used by Refresh Schema to add `is-loading`).
- `priority` (number, optional): Sort order — lower renders first. Default: `10`.

**Hooks fired:**

- `wpgraphql-ide.afterRegisterTopbarAction` on success

**Example:**

```js
const { registerTopbarAction, registerWorkspaceTabType } = window.WPGraphQLIDE;
import { Icon, help } from '@wordpress/icons';

registerWorkspaceTabType('help-center', {
  title: 'Help',
  content: () => <HelpScreen />,
});

registerTopbarAction('help-center', {
  title: 'Open Help Center',
  icon: () => <Icon icon={help} />,
  tabType: 'help-center',
  tabId: 'help-center',
}, 20);
```
