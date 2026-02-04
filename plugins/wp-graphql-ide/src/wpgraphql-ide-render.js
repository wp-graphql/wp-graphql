import { createRoot } from '@wordpress/element';
import { dispatch } from '@wordpress/data';
import AppWrapper from './components/AppWrapper';

// Store root reference to prevent multiple root creation
let reactRoot = null;
let isRendered = false;

window.addEventListener('WPGraphQLIDE_Window_Ready', function (event) {
	/**
	 * Get our root element id from the localized script.
	 */
	const { rootElementId } = window.WPGRAPHQL_IDE_DATA;

	/**
	 * Attempts to render the React application to a specified mount point in the DOM.
	 * Logs an error to the console if the mount point is missing.
	 */
	const appMountPoint = document.getElementById(rootElementId);
	if (appMountPoint) {
		// Prevent multiple renders
		if (isRendered) {
			return;
		}

		// Prevent the admin bar link from intercepting clicks
		// The Trigger button needs to receive the click directly for VaulDrawer to work
		const parentLink = appMountPoint.closest('a');
		if (
			parentLink &&
			parentLink.href &&
			(parentLink.href === '#' || parentLink.href.endsWith('#'))
		) {
			// Stop the link from intercepting clicks - let the Trigger button handle them
			parentLink.addEventListener(
				'click',
				function (e) {
					// Always prevent default to stop navigation
					e.preventDefault();
					// Stop propagation only if clicking the link itself (not children)
					// This allows the Trigger button to receive the click
					if (e.target === parentLink) {
						e.stopPropagation();
						// Find and click the Trigger button programmatically
						const triggerButton =
							appMountPoint.querySelector('.AppDrawerButton');
						if (triggerButton) {
							triggerButton.click();
						}
					}
					// If clicking inside (the button), let it bubble naturally
				},
				false
			);

			// Remove aria-hidden from admin bar when drawer opens to fix accessibility warning
			// We'll handle this when the drawer state changes via React
		}

		// Create root only once
		if (!reactRoot) {
			reactRoot = createRoot(appMountPoint);
		}
		reactRoot.render(<AppWrapper />);
		isRendered = true;

		window.dispatchEvent(new Event('WPGraphQLIDEReady'));
	} else {
		console.error(
			`WPGraphQL IDE mount point not found. Please ensure an element with ID "${rootElementId}" exists.`
		);
	}
});
