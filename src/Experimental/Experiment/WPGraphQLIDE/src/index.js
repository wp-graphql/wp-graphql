/**
 * @file
 * Initializes the WPGraphQL IDE by setting up the necessary WordPress hooks,
 * registering the global store, and exposing the GraphQL functionality through a global IDE object.
 */

// External dependencies
import { createRoot } from '@wordpress/element';
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
//
// // Dynamically import the GraphQL library and assign it to `window.WPGraphQLIDE.GraphQL`
// import('graphql/index.js').then(GraphQL => {
// 	window.WPGraphQLIDE.GraphQL = GraphQL;
// 	window.WPGraphQLIDE.GraphQLLoaded = true;
// 	console.log( `WPGraphQLIDE is ready` );
// 	window.dispatchEvent( new Event( 'WPGraphQLIDE_Window_Ready' ) );
// }).catch(error => {
// 	console.error('Failed to load GraphQL library:', error);
// });
