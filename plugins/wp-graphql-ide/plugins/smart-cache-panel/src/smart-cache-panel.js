import { SmartCachePanel } from './components/SmartCachePanel';

window.addEventListener('WPGraphQLIDE_Window_Ready', () => {
	if (!window.WPGraphQLIDE) {
		return;
	}

	const { registerResponseExtensionTab } = window.WPGraphQLIDE;

	if (typeof registerResponseExtensionTab !== 'function') {
		return;
	}

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
});
