/**
 * External dependencies
 */
import { QueryComposer } from './components/QueryComposer';
import { pencil as editIcon, Icon } from '@wordpress/icons';

window.addEventListener('WPGraphQLIDE_Window_Ready', function (event) {
	if (!window.WPGraphQLIDE) {
		return;
	}

	const { registerActivityBarPanel } = window.WPGraphQLIDE || {};

	if (typeof registerActivityBarPanel === 'function') {
		registerActivityBarPanel(
			'query-composer',
			{
				title: 'Query Composer',
				icon: () => (
					<Icon
						icon={editIcon}
						style={{
							fill: 'hsla(var(--color-neutral), var(--alpha-tertiary))',
						}}
					/>
				),
				content: () => <QueryComposer />,
			},
			3 // Priority
		);
	}
});
