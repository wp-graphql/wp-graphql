import {
	SmartCachePanel,
	initSmartCacheSessionTracking,
} from './components/SmartCachePanel';
import { SmartCacheStatusBadge } from './components/SmartCacheStatusBadge';

window.addEventListener('WPGraphQLIDE_Window_Ready', () => {
	if (!window.WPGraphQLIDE) {
		return;
	}

	// Start counting HIT/MISS for every execution, independent of whether
	// the Smart Cache response tab is the one currently mounted.
	initSmartCacheSessionTracking();

	const { registerResponseExtensionTab, registerStatusBarItem } =
		window.WPGraphQLIDE;

	if (typeof registerResponseExtensionTab === 'function') {
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
		registerStatusBarItem(
			'smart-cache',
			{ render: (ctx) => <SmartCacheStatusBadge {...ctx} /> },
			35
		);
	}
});
