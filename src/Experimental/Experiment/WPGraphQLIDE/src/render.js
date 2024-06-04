import { createRoot } from '@wordpress/element';
import AppWrapper from './components/AppWrapper';

window.addEventListener( 'WPGraphQLIDE_Window_Ready', function ( event ) {
	/**
	 * Get our root element id from the localized script.
	 */
	const { rootElementId } = window.WPGRAPHQL_IDE_DATA;

	/**
	 * Attempts to render the React application to a specified mount point in the DOM.
	 * Logs an error to the console if the mount point is missing.
	 */
	const appMountPoint = document.getElementById( rootElementId );
	if ( appMountPoint ) {
		console.log( `RENDER....` );
		createRoot( appMountPoint ).render( <AppWrapper /> );
		window.dispatchEvent( new Event( 'WPGraphQLIDEReady' ) );
	} else {
		console.error(
			`WPGraphQL IDE mount point not found. Please ensure an element with ID "${ rootElementId }" exists.`
		);
	}
} );
