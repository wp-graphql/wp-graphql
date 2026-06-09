import { useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

import hooks from '../wordpress-hooks';
import { AppDrawer } from './AppDrawer';
import { App } from './App';
import { DialogProvider } from './dialogs/DialogProvider';
import { ResizeOverlayProvider } from './ResizeOverlay';

import {
	endpointMode,
	isUserLoggedIn,
	isDedicatedIdePage,
	renderStandalone,
} from '../bootstrap';

// eslint-disable-next-line no-undef
const {
	context: { drawerButtonLabel },
} = window.WPGRAPHQL_IDE_DATA;

const setInitialState = (dispatch) => {
	const {
		setShouldRenderStandalone,
		setInitialStateLoaded,
		toggleAuthentication,
	} = dispatch;

	// Standalone mode: full-page render with no slide-up drawer.
	// Dedicated admin page sets `isDedicatedIdePage`; the public
	// `?graphql` endpoint sets `renderStandalone` (for both anonymous
	// and signed-in admins, since both land at the same URL and
	// should see the same shell shape — only the feature set
	// differs, via `endpointMode`).
	if (isDedicatedIdePage || renderStandalone) {
		setShouldRenderStandalone(true);
	}

	// Public-endpoint render for an anonymous visitor: the app store's
	// `isAuthenticated` initial state is `true` because most surfaces
	// only mount for logged-in admins. On the public endpoint that
	// default would have us send a useless / invalid nonce. Flip it
	// off once on hydration when we know the visitor is anonymous.
	// `wp_localize_script` serializes PHP `false` as the empty string
	// `""`, so a truthy check is right here.
	if (endpointMode && !isUserLoggedIn) {
		toggleAuthentication();
	}

	// Deep-link share restore (`?wpgraphql_ide=`) is handled in the
	// document-editor store's `loadDocuments` thunk, which opens the
	// shared payload as a real tab. Doing it here against the app store
	// doesn't work: the tab-switch sync in IDELayout re-seeds the live
	// query from the active document right after this runs.

	setInitialStateLoaded();
};

/**
 * The main application component.
 *
 * @return {JSX.Element} The application component.
 */
export function AppWrapper() {
	const dispatch = useDispatch('wpgraphql-ide/app');

	useEffect(() => {
		setInitialState(dispatch);
	}, [dispatch]);

	useEffect(() => {
		/**
		 * Perform actions on component mount.
		 *
		 * Triggers a custom action 'wpgraphql-ide.rendered' when the App component mounts,
		 * allowing plugins or themes to hook into this event. The action passes
		 * the current state of `drawerOpen` to any listeners, providing context
		 * about the application's UI state.
		 */
		hooks.doAction('wpgraphql-ide.rendered');

		/**
		 * Cleanup action on component unmount.
		 *
		 * Returns a cleanup function that triggers the 'wpgraphql-ide.destroyed' action,
		 * signaling that the App component is about to unmount. This allows for
		 * any necessary cleanup or teardown operations in response to the App
		 * component's lifecycle.
		 */
		return () => hooks.doAction('wpgraphql-ide.destroyed');
	}, []);

	return <RenderAppWrapper />;
}

function RenderAppWrapper() {
	const isInitialStateLoaded = useSelect((select) => {
		return select('wpgraphql-ide/app').isInitialStateLoaded();
	});

	const shouldRenderStandalone = useSelect((select) => {
		return select('wpgraphql-ide/app').shouldRenderStandalone();
	});

	if (!isInitialStateLoaded) {
		return null;
	}

	if (shouldRenderStandalone) {
		return (
			<div className="wpgraphql-ide-shell">
				<DialogProvider>
					<ResizeOverlayProvider>
						<App />
					</ResizeOverlayProvider>
				</DialogProvider>
			</div>
		);
	}

	return (
		<div className="wpgraphql-ide-shell">
			<DialogProvider>
				<ResizeOverlayProvider>
					<AppDrawer buttonLabel={drawerButtonLabel}>
						<App />
					</AppDrawer>
				</ResizeOverlayProvider>
			</DialogProvider>
		</div>
	);
}

export default AppWrapper;
