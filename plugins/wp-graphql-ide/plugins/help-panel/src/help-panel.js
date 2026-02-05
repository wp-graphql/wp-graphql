import { HelpIcon } from './components/HelpIcon';
import { HelpPanel } from './components/HelpPanel';

window.addEventListener('WPGraphQLIDE_Window_Ready', (event) => {
	if (!window.WPGraphQLIDE) {
		return;
	}

	const { registerActivityBarPanel } = window.WPGraphQLIDE;

	if (typeof registerActivityBarPanel === 'function') {
		registerActivityBarPanel(
			'help',
			{
				title: 'Help',
				icon: () => <HelpIcon />,
				content: () => <HelpPanel />,
			},
			3
		);
	}
});
