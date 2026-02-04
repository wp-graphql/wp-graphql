/* global WPGRAPHQL_IDE_DATA */
import { useEffect } from '@wordpress/element';
import { doAction } from '@wordpress/hooks';
import { useDispatch, useSelect } from '@wordpress/data';
import { parse, print } from 'graphql';
import LZString from 'lz-string';

import { AppDrawer } from './AppDrawer';
import { App } from './App';

const {
	isDedicatedIdePage,
	context: { drawerButtonLabel },
} = window.WPGRAPHQL_IDE_DATA;

const url = new URL( window.location.href );
const params = url.searchParams;

const setInitialState = () => {
	const {
		setDrawerOpen,
		setQuery,
		setShouldRenderStandalone,
		setInitialStateLoaded,
	} = useDispatch( 'wpgraphql-ide/app' );

	if ( isDedicatedIdePage ) {
		setShouldRenderStandalone( true );
	}

	if ( params.has( 'wpgraphql_ide' ) ) {
		const queryParam = params.get( 'wpgraphql_ide' );
		const queryParamShareObjectString =
			LZString.decompressFromEncodedURIComponent( queryParam );
		const queryParamShareObject = JSON.parse( queryParamShareObjectString );

		const { query } = queryParamShareObject;

		let parsedQuery;
		let printedQuery = null;

		// convert the query from a string to an AST
		// console errors if there are any
		try {
			parsedQuery = parse( query );
		} catch ( error ) {
			console.error(
				`Error parsing the query "${ query }"`,
				error.message
			);
			parsedQuery = null;
		}

		// Convert the AST back to a formatted printed document
		// console errors if there are any
		if ( null !== parsedQuery ) {
			try {
				printedQuery = print( parsedQuery );
			} catch ( error ) {
				console.error(
					`Error printing the query "${ query }"`,
					error.message
				);
				printedQuery = null;
			}
		}

		if ( null !== printedQuery ) {
			setDrawerOpen( true );
			setQuery( printedQuery );
			params.delete( 'wpgraphql_ide' );
			history.pushState( {}, '', url.toString() );
		}
	}

	setInitialStateLoaded();
};

/**
 * The main application component.
 *
 * @return {JSX.Element} The application component.
 */
export function AppWrapper() {
	setInitialState();

	useEffect( () => {
		/**
		 * Perform actions on component mount.
		 *
		 * Triggers a custom action 'wpgraphql-ide.rendered' when the App component mounts,
		 * allowing plugins or themes to hook into this event. The action passes
		 * the current state of `drawerOpen` to any listeners, providing context
		 * about the application's UI state.
		 */
		doAction( 'wpgraphql-ide.rendered' );

		/**
		 * Cleanup action on component unmount.
		 *
		 * Returns a cleanup function that triggers the 'wpgraphql-ide.destroyed' action,
		 * signaling that the App component is about to unmount. This allows for
		 * any necessary cleanup or teardown operations in response to the App
		 * component's lifecycle.
		 */
		return () => doAction( 'wpgraphql-ide.destroyed' );
	}, [] );

	return <RenderAppWrapper />;
}

export function RenderAppWrapper() {
	const isInitialStateLoaded = useSelect( ( select ) => {
		return select( 'wpgraphql-ide/app' ).isInitialStateLoaded();
	} );

	const shouldRenderStandalone = useSelect( ( select ) => {
		return select( 'wpgraphql-ide/app' ).shouldRenderStandalone();
	} );

	if ( ! isInitialStateLoaded ) {
		return null;
	}

	if ( shouldRenderStandalone ) {
		return (
			<div className="AppRoot">
				<App />
			</div>
		);
	}

	return (
		<div className="AppRoot">
			<AppDrawer buttonLabel={ drawerButtonLabel }>
				<App />
			</AppDrawer>
		</div>
	);
}

export default AppWrapper;
