import hooks from './wordpress-hooks';
import { dispatch, select } from '@wordpress/data';

/**
 * Public function to register a new editor toolbar button.
 *
 * @param {string} name          The name of the button to register.
 * @param {Object} config        The configuration object for the button.
 * @param {number} [priority=10] The priority for the button, lower numbers mean higher priority.
 *
 * @return {void}
 */
export function registerDocumentEditorToolbarButton(
	name,
	config,
	priority = 10
) {
	try {
		dispatch('wpgraphql-ide/document-editor').registerButton(
			name,
			config,
			priority
		);
		hooks.doAction(
			'wpgraphql-ide.afterRegisterToolbarButton',
			name,
			config,
			priority
		);
	} catch (error) {
		console.error(`Failed to register button: ${name}`, error);
		hooks.doAction(
			'wpgraphql-ide.registerToolbarButtonError',
			name,
			config,
			priority,
			error
		);
	}
}

export function registerActivityBarPanel(name, config, priority = 10) {
	try {
		dispatch('wpgraphql-ide/activity-bar').registerPanel(
			name,
			config,
			priority
		);
		hooks.doAction(
			'wpgraphql-ide.afterRegisterActivityBarPanel',
			name,
			config,
			priority
		);
	} catch (error) {
		console.error(`Failed to register activity bar panel: ${name}`, error);
		hooks.doAction(
			'wpgraphql-ide.registerActivityBarPanelError',
			name,
			config,
			priority,
			error
		);
	}
}

/**
 * Register a tab in the response extensions panel.
 *
 * Most tabs map 1:1 onto a key in the GraphQL response `extensions` object
 * — Tracing reads `extensions.tracing`, Debug reads `extensions.debug`, etc.
 * The tab is only shown when the latest response contains that key; the
 * `content` component receives the value at that key as its `data` prop.
 *
 * The built-in `errors` and `headers` tabs use the same registry but flag
 * themselves with `alwaysShow: true` (they describe the response itself,
 * not response.extensions, so their data is sourced from synthetic slots
 * — see slotData in ResponseContent.jsx).
 *
 * @param {string}          name                      Extension key (e.g. "debug", "graphqlSmartCache").
 * @param {Object}          config                    Tab configuration.
 * @param {string|Function} config.title              Human-readable tab title. Pass a
 *                                                    function `({data, response}) =>
 *                                                    string` to surface a count or
 *                                                    other state (e.g. "Errors (3)").
 * @param {Function}        config.content            Component receiving `{ data, response }`.
 * @param {boolean}         [config.alwaysShow=false] When true, the tab is shown
 *                                                    even if there's no matching key in
 *                                                    response.extensions. Used by the
 *                                                    built-in errors / headers tabs.
 * @param {number}          [priority=10]             Lower values render first.
 *
 * @return {void}
 */
export function registerResponseExtensionTab(name, config, priority = 10) {
	try {
		dispatch('wpgraphql-ide/response-extensions').registerExtensionTab(
			name,
			config,
			priority
		);
		hooks.doAction(
			'wpgraphql-ide.afterRegisterResponseExtensionTab',
			name,
			config,
			priority
		);
	} catch (error) {
		console.error(
			`Failed to register response extension tab: ${name}`,
			error
		);
		hooks.doAction(
			'wpgraphql-ide.registerResponseExtensionTabError',
			name,
			config,
			priority,
			error
		);
	}
}

/**
 * Register a tab in the editor's bottom tools area (where Variables and
 * Headers live). The tab is rendered by EditorPane via OverflowTabs and
 * sits beneath the query editor.
 *
 * The `content` component receives the editor context as props:
 *   { query, variables, onVariablesChange, variableToType,
 *     headers, onHeadersChange, response, activeDocument }
 * Built-in tabs use what they need; plugins can pull from props or read
 * the stores directly via useSelect.
 *
 * @param {string}          name           Unique tab identifier.
 * @param {Object}          config         Tab configuration.
 * @param {string|Function} config.title   Human-readable tab title (or
 *                                         function for dynamic titles).
 * @param {Function}        config.content Component receiving editor-context props.
 * @param {number}          [priority=10]  Lower values render first.
 *
 * @return {void}
 */
export function registerEditorBottomTab(name, config, priority = 10) {
	try {
		dispatch('wpgraphql-ide/editor-bottom-tabs').registerEditorBottomTab(
			name,
			config,
			priority
		);
		hooks.doAction(
			'wpgraphql-ide.afterRegisterEditorBottomTab',
			name,
			config,
			priority
		);
	} catch (error) {
		console.error(`Failed to register editor bottom tab: ${name}`, error);
		hooks.doAction(
			'wpgraphql-ide.registerEditorBottomTabError',
			name,
			config,
			priority,
			error
		);
	}
}

/**
 * Register an item in the response toolbar's status row (where the HTTP
 * status code, duration, size, and resolver-count badges live). Useful
 * for surfacing live response signals next to the existing meta — cache
 * hit/miss, schema warnings, custom badges, etc.
 *
 * The `render` callback receives:
 *   { response, parsedResponse, responseStatus, responseDuration,
 *     responseSize, isFetching, focusResponseTab(name) }
 * It should return a ReactNode (rendered inline in the meta row) or
 * null to hide the item for the current response.
 *
 * @param {string}   name          Unique item identifier.
 * @param {Object}   config        Item configuration.
 * @param {Function} config.render Render callback returning a ReactNode or null.
 * @param {number}   [priority=10] Lower values render first (left-to-right).
 *
 * @return {void}
 */
export function registerStatusBarItem(name, config, priority = 10) {
	try {
		dispatch('wpgraphql-ide/status-bar-items').registerStatusBarItem(
			name,
			config,
			priority
		);
		hooks.doAction(
			'wpgraphql-ide.afterRegisterStatusBarItem',
			name,
			config,
			priority
		);
	} catch (error) {
		console.error(`Failed to register status bar item: ${name}`, error);
		hooks.doAction(
			'wpgraphql-ide.registerStatusBarItemError',
			name,
			config,
			priority,
			error
		);
	}
}

/**
 * Register a response viewer mode (the JSON / Table / etc. toggle in the
 * response toolbar). Each mode owns both the toggle button label and the
 * top-pane content when it's active.
 *
 * The `render` callback receives:
 *   { response, parsed, dataScope, viewerContent }
 * where `viewerContent` is the pre-formatted string the JSON viewer
 * uses (already filtered by data-scope).
 *
 * @param {string}   value         Unique mode value (e.g. "formatted", "table").
 * @param {Object}   config        Mode configuration.
 * @param {string}   config.label  Toggle-button label.
 * @param {Function} config.render Render callback returning a ReactNode.
 * @param {number}   [priority=10] Lower values render first (left-to-right).
 *
 * @return {void}
 */
export function registerResponseViewMode(value, config, priority = 10) {
	try {
		dispatch('wpgraphql-ide/response-view-modes').registerResponseViewMode(
			value,
			config,
			priority
		);
		hooks.doAction(
			'wpgraphql-ide.afterRegisterResponseViewMode',
			value,
			config,
			priority
		);
	} catch (error) {
		console.error(`Failed to register response view mode: ${value}`, error);
		hooks.doAction(
			'wpgraphql-ide.registerResponseViewModeError',
			value,
			config,
			priority,
			error
		);
	}
}

/**
 * Register an item in the response toolbar's kebab dropdown ("Response
 * options" — currently houses the data-scope toggle). Useful for
 * "Copy as cURL", "Export to Postman", etc.
 *
 * Items with the same `group` string render under a `<MenuGroup
 * label={group}>` (Gutenberg post-editor pattern). Omit `group` to
 * land in the unlabelled top group.
 *
 * The action callbacks receive a ctx object:
 *   { dataScope, setDataScope, response, parsedResponse, closeMenu }
 *
 * @param {string}          name                   Unique action identifier.
 * @param {Object}          config                 Action configuration.
 * @param {string|Function} config.label           Item label (or fn(ctx) => string).
 * @param {Function}        config.onClick         Click handler `(ctx) => void`.
 * @param {Function}        [config.isSelected]    Returns true to render a checkmark.
 * @param {Function}        [config.isDisabled]    Returns true to disable the item.
 * @param {boolean}         [config.isDestructive] Renders the item with the destructive style.
 * @param {string}          [config.group]         Group label (drives MenuGroup).
 * @param {Function}        [config.predicate]     Hide when this returns false.
 * @param {number}          [priority=10]          Sort order within group.
 *
 * @return {void}
 */
export function registerResponseAction(name, config, priority = 10) {
	try {
		dispatch('wpgraphql-ide/response-actions').registerResponseAction(
			name,
			config,
			priority
		);
		hooks.doAction(
			'wpgraphql-ide.afterRegisterResponseAction',
			name,
			config,
			priority
		);
	} catch (error) {
		console.error(`Failed to register response action: ${name}`, error);
		hooks.doAction(
			'wpgraphql-ide.registerResponseActionError',
			name,
			config,
			priority,
			error
		);
	}
}

/**
 * Register a workspace tab type with a content renderer.
 *
 * Once registered, the tab type can be opened with `openWorkspaceTab`.
 *
 * @param {string}   name           Unique tab type identifier.
 * @param {Object}   config         Tab type configuration.
 * @param {string}   config.title   Human-readable display name.
 * @param {Function} config.content React component to render as workspace content.
 * @param {Function} [config.icon]  Optional icon component.
 *
 * @return {void}
 */
export function registerWorkspaceTabType(name, config) {
	try {
		dispatch('wpgraphql-ide/document-editor').registerTabType(name, config);
		hooks.doAction(
			'wpgraphql-ide.afterRegisterWorkspaceTabType',
			name,
			config
		);
	} catch (error) {
		console.error(`Failed to register workspace tab type: ${name}`, error);
	}
}

/**
 * Open a workspace tab of a registered type.
 *
 * If a tab with the given ID is already open, it is switched to instead
 * of creating a duplicate.
 *
 * @param {string} typeName      Tab type name (must be registered via registerWorkspaceTabType).
 * @param {Object} options       Options.
 * @param {string} options.id    Unique tab ID. Reusing an ID switches to the existing tab.
 * @param {string} options.title Display title for the tab.
 *
 * @return {void}
 */
export function openWorkspaceTab(typeName, { id, title } = {}) {
	const registered = select('wpgraphql-ide/document-editor').getTabType(
		typeName
	);
	if (!registered) {
		console.error(`Workspace tab type "${typeName}" is not registered.`);
		return;
	}
	const tabId = id || `${typeName}-${Date.now()}`;
	const tabTitle = title || registered.title || typeName;
	dispatch('wpgraphql-ide/document-editor').openWorkspaceTab(
		typeName,
		tabId,
		tabTitle
	);
}

/**
 * Register a topbar action button that opens a workspace tab.
 *
 * Topbar actions appear in the global top bar (right side, after schema
 * refresh). Clicking one opens or switches to a singleton workspace tab.
 *
 * @param {string}   name           Unique action identifier.
 * @param {Object}   config         Action configuration.
 * @param {string}   config.title   Tooltip / aria-label text.
 * @param {Function} config.icon    React component rendering the icon.
 * @param {string}   config.tabType Workspace tab type to open (must be registered).
 * @param {string}   [config.tabId] Singleton tab ID (defaults to tabType).
 * @param {number}   [priority=10]  Sort order (lower = first).
 *
 * @return {void}
 */
export function registerTopbarAction(name, config, priority = 10) {
	try {
		dispatch('wpgraphql-ide/document-editor').registerTopbarAction(
			name,
			config,
			priority
		);
		hooks.doAction(
			'wpgraphql-ide.afterRegisterTopbarAction',
			name,
			config,
			priority
		);
	} catch (error) {
		console.error(`Failed to register topbar action: ${name}`, error);
	}
}
