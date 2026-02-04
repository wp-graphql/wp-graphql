/**
 * @file
 * Initializes the WPGraphQL IDE by setting up the necessary WordPress hooks,
 * registering the global store, and exposing the GraphQL functionality through a global IDE object.
 */

// External dependencies
import * as accessFunctions from './access-functions';

// Local imports including the hook configuration and the main App component.
import hooks from './wordpress-hooks';

import { registerStores } from './stores';
import { initializeRegistry } from './registry';

/**
 * Initializes the application's regions by registering stores.
 */
const init = () => {
	registerStores();
	initializeRegistry();
	hooks.doAction( 'wpgraphql-ide.init' );
};

init();

/**
 * Exposes a global `WPGraphQLIDE` variable that includes hooks, store, and GraphQL references,
 * making them accessible for extensions and external scripts.
 */
window.WPGraphQLIDE = {
	hooks,
	...accessFunctions,
};

window.dispatchEvent( new Event( 'WPGraphQLIDE_Window_Ready' ) );
