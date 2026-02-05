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
		// eslint-disable-next-line no-console
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
		// eslint-disable-next-line no-console
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

// export function registerActivityBarUtility(
// 	name,
// 	config,
// 	priority = 10
// ) {
// 	try {
// 		dispatch( 'wpgraphql-ide/activity-bar' ).registerUtilityButton(
// 			name,
// 			config,
// 			priority
// 		);
// 		hooks.doAction(
// 			'wpgraphql-ide.afterRegisterActivityBarUtilityButton',
// 			name,
// 			config,
// 			priority
// 		);
// 	} catch ( error ) {
// 		console.error( `Failed to register button: ${ name }`, error );
// 		hooks.doAction(
// 			'wpgraphql-ide.registerActivityBarUtilityButtonError',
// 			name,
// 			config,
// 			priority,
// 			error
// 		);
// 	}
// }
