import hooks from './wordpress-hooks';
import { dispatch } from '@wordpress/data';

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
