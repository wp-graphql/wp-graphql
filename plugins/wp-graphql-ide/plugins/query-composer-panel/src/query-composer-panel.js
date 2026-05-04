/**
 * External dependencies
 */
import { QueryComposer } from './components/QueryComposer';
import { pencil as editIcon, Icon } from '@wordpress/icons';

window.addEventListener('WPGraphQLIDE_Window_Ready', function (_event) {
	if (!window.WPGraphQLIDE) {
		return;
	}

	const { registerActivityBarPanel } = window.WPGraphQLIDE || {};

	if (typeof registerActivityBarPanel === 'function') {
		registerActivityBarPanel(
			'query-composer',
			{
				title: 'Query Composer',
				icon: () => <Icon icon={editIcon} />,
				content: () => <QueryComposer />,
			},
			3 // Priority
		);
	}
});
