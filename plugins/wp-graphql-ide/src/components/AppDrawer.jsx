import React, { useState, useEffect } from 'react';
import { Drawer as VaulDrawer } from 'vaul';
import { useDispatch, useSelect } from '@wordpress/data';

export function AppDrawer({ children, buttonLabel }) {
	const isDrawerOpen = useSelect((select) => {
		return select('wpgraphql-ide/app').isDrawerOpen();
	});

	const { setDrawerOpen } = useDispatch('wpgraphql-ide/app');

	// Use local state for VaulDrawer, sync with WordPress store
	const [localOpen, setLocalOpen] = useState(false);

	// Sync local state with WordPress store
	useEffect(() => {
		setLocalOpen(isDrawerOpen);
	}, [isDrawerOpen]);

	// Prevent WordPress from setting aria-hidden on admin bar when drawer is open
	useEffect(() => {
		if (!localOpen) {
			return;
		}

		const adminBar = document.getElementById('wpadminbar');
		if (!adminBar) {
			return;
		}

		// Store original aria-hidden value
		const originalAriaHidden = adminBar.getAttribute('aria-hidden');

		// Remove aria-hidden immediately when drawer opens
		adminBar.removeAttribute('aria-hidden');

		// Watch for WordPress trying to set aria-hidden and prevent it
		// eslint-disable-next-line no-undef
		const observer = new MutationObserver((mutations) => {
			mutations.forEach((mutation) => {
				if (
					mutation.type === 'attributes' &&
					mutation.attributeName === 'aria-hidden'
				) {
					// If WordPress tries to set aria-hidden while drawer is open, remove it
					if (adminBar.getAttribute('aria-hidden') === 'true') {
						adminBar.removeAttribute('aria-hidden');
					}
				}
			});
		});

		observer.observe(adminBar, {
			attributes: true,
			attributeFilter: ['aria-hidden'],
		});

		return () => {
			observer.disconnect();
			// Restore original aria-hidden when drawer closes
			if (originalAriaHidden) {
				adminBar.setAttribute('aria-hidden', originalAriaHidden);
			} else {
				adminBar.removeAttribute('aria-hidden');
			}
		};
	}, [localOpen]);

	// Manage focus when drawer opens/closes
	useEffect(() => {
		if (localOpen) {
			// Immediately blur the trigger button to prevent aria-hidden conflict
			const triggerButton = document.querySelector('.AppDrawerButton');
			// eslint-disable-next-line @wordpress/no-global-active-element
			if (triggerButton && document.activeElement === triggerButton) {
				triggerButton.blur();
			}

			// When drawer opens, move focus to the drawer content
			// Use a small delay to ensure the drawer is fully rendered
			const focusTimeout = setTimeout(() => {
				// Try to find a focusable element in the drawer content
				const drawer = document.querySelector('[data-vaul-drawer]');
				if (drawer) {
					// Look for the GraphiQL query editor or any focusable element
					const focusableElements = drawer.querySelectorAll(
						'textarea, input, button, [tabindex]:not([tabindex="-1"])'
					);

					// Prefer the GraphiQL query editor textarea
					const queryEditor = drawer.querySelector(
						'.graphiql-query-editor textarea, .CodeMirror textarea'
					);
					if (queryEditor) {
						queryEditor.focus();
					} else if (focusableElements.length > 0) {
						// Fallback to first focusable element
						focusableElements[0].focus();
					} else {
						// Last resort: focus the drawer content itself
						drawer.setAttribute('tabindex', '-1');
						drawer.focus();
					}
				}
			}, 100);

			return () => clearTimeout(focusTimeout);
		}
		// When drawer closes, return focus to the trigger button
		// Use a small delay to ensure the drawer is fully closed
		const focusTimeout = setTimeout(() => {
			const triggerButton = document.querySelector('.AppDrawerButton');
			if (triggerButton) {
				triggerButton.focus();
			}
		}, 100);

		return () => clearTimeout(focusTimeout);
	}, [localOpen]);

	return (
		<div className="AppDrawerRoot">
			<VaulDrawer.Root
				dismissible={false}
				closeThreshold={1}
				shouldScaleBackground={false}
				open={localOpen}
				onOpenChange={(open) => {
					// Immediately blur the trigger button when opening to prevent aria-hidden conflict
					if (open) {
						const triggerButton =
							document.querySelector('.AppDrawerButton');
					if (
						triggerButton &&
						// eslint-disable-next-line @wordpress/no-global-active-element
						document.activeElement === triggerButton
					) {
						triggerButton.blur();
						}
					}
					setLocalOpen(open);
					setDrawerOpen(open);
				}}
			>
				<VaulDrawer.Trigger className="AppDrawerButton">
					<span className="ab-icon"></span>
					{buttonLabel}
				</VaulDrawer.Trigger>
				<VaulDrawer.Portal>
					<VaulDrawer.Content>
						<VaulDrawer.Title className="screen-reader-text">
							GraphQL IDE
						</VaulDrawer.Title>
						<VaulDrawer.Description className="screen-reader-text">
							Interactive GraphQL query editor for WPGraphQL
						</VaulDrawer.Description>
						{children}
					</VaulDrawer.Content>
					<VaulDrawer.Overlay />
				</VaulDrawer.Portal>
			</VaulDrawer.Root>
		</div>
	);
}
