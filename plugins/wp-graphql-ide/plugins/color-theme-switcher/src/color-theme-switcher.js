import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';
import { ColorThemeSwitcher } from './components/ColorThemeSwitcher';
import { paintBrushIcon } from './components/PaintBrushIcon';

const TAB_ID = 'color-theme-switcher';

window.addEventListener('WPGraphQLIDE_Window_Ready', () => {
	if (!window.WPGraphQLIDE) {
		return;
	}

	const { registerTopbarAction, registerWorkspaceTabType } =
		window.WPGraphQLIDE;

	// No schemes localized → don't surface the picker at all (e.g. user
	// without enqueue context, or no admin color colors registered).
	if (
		!window.WPGraphQLIDEColorThemeSwitcher ||
		!window.WPGraphQLIDEColorThemeSwitcher.schemes ||
		Object.keys(window.WPGraphQLIDEColorThemeSwitcher.schemes).length === 0
	) {
		return;
	}

	if (typeof registerWorkspaceTabType === 'function') {
		registerWorkspaceTabType(TAB_ID, {
			title: __('Color Theme', 'wpgraphql-ide'),
			content: ColorThemeSwitcher,
		});
	}

	if (typeof registerTopbarAction === 'function') {
		// Priority 8 sits between "refresh schema" (5) and "WPGraphQL
		// Settings" (10) so the paintbrush lands immediately to the
		// left of the settings cog.
		registerTopbarAction(
			TAB_ID,
			{
				title: __('Color Theme', 'wpgraphql-ide'),
				icon: () => <Icon icon={paintBrushIcon} />,
				tabType: TAB_ID,
				tabId: TAB_ID,
			},
			8
		);
	}
});
