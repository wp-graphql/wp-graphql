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
 * The `name` must match the top-level key in the GraphQL response
 * `extensions` object that this tab owns. The tab is only shown when the
 * latest response contains that key; the `content` callback receives the
 * value at that key as `data`.
 *
 * @param {string}   name           Extension key (e.g. "debug", "graphqlSmartCache").
 * @param {Object}   config         Tab configuration.
 * @param {string}   config.title   Human-readable tab title.
 * @param {Function} config.content Component receiving `{ data, response }`.
 * @param {number}   [priority=10]  Lower values render first.
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
