import { Icon, stack } from '@wordpress/icons';
import { CacheInspector } from './components/CacheInspector';

const TAB_ID = 'cache-inspector';

window.addEventListener('WPGraphQLIDE_Window_Ready', () => {
	if (!window.WPGraphQLIDE) {
		return;
	}

	const { registerWorkspaceTabType, registerTopbarAction } =
		window.WPGraphQLIDE;

	if (typeof registerWorkspaceTabType !== 'function') {
		return;
	}

	registerWorkspaceTabType(TAB_ID, {
		title: 'Cache Inspector',
		content: CacheInspector,
	});

	if (typeof registerTopbarAction === 'function') {
		registerTopbarAction(
			'cache-inspector',
			{
				title: 'Cache Inspector',
				icon: () => <Icon icon={stack} />,
				tabType: TAB_ID,
				tabId: TAB_ID,
			},
			20
		);
	}
});
