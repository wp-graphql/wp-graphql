import { SmartCachePanel } from './components/SmartCachePanel';
import { SmartCacheStatusBadge } from './components/SmartCacheStatusBadge';

window.addEventListener('WPGraphQLIDE_Window_Ready', () => {
	if (!window.WPGraphQLIDE) {
		return;
	}

	const { registerResponseExtensionTab, registerStatusBarItem } =
		window.WPGraphQLIDE;

	if (typeof registerResponseExtensionTab === 'function') {
		// Live alongside the other extension renderers (Tracing/Debug, etc).
		// Title surfaces a HIT/MISS hint so users can see cache state at a
		// glance without opening the tab — matches the "Errors (3)" pattern
		// the built-in Errors tab uses.
		registerResponseExtensionTab(
			'graphqlSmartCache',
			{
				title: ({ data }) => {
					const isHit = !!data?.graphqlObjectCache?.cacheKey;
					return isHit ? 'Smart Cache (HIT)' : 'Smart Cache';
				},
				content: SmartCachePanel,
			},
			25
		);
	}

	if (typeof registerStatusBarItem === 'function') {
		// Status-bar HIT/MISS badge, between size (30) and resolver-count
		// (40). Only renders when the response carries the smart-cache
		// extension — silently absent on servers without smart-cache.
		registerStatusBarItem(
			'smart-cache',
			{ render: (ctx) => <SmartCacheStatusBadge {...ctx} /> },
			35
		);
	}
});
