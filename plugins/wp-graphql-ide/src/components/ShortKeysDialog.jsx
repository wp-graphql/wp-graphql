import React from 'react';
import { Modal } from '@wordpress/components';
import { Fragment } from '@wordpress/element';

const modifier =
	typeof window !== 'undefined' &&
	window.navigator.platform.toLowerCase().indexOf('mac') === 0
		? 'Cmd'
		: 'Ctrl';

const SHORT_KEYS = Object.entries({
	'Search in editor': [modifier, 'F'],
	'Execute query': [modifier, 'Enter'],
	'Prettify query': ['Ctrl', 'Shift', 'P'],
	'Merge fragments into query': ['Ctrl', 'Shift', 'M'],
	'Copy query': ['Ctrl', 'Shift', 'C'],
});

const ShortKeys = () => {
	return (
		<div>
			<table className="wpgraphql-ide-shortkeys-table">
				<thead>
					<tr>
						<th>Short Key</th>
						<th>Function</th>
					</tr>
				</thead>
				<tbody>
					{SHORT_KEYS.map(([title, keys]) => (
						<tr key={title}>
							<td>
								{keys.map((key, index, array) => (
									<Fragment key={key}>
										<code className="wpgraphql-ide-shortkeys-key">
											{key}
										</code>
										{index !== array.length - 1 && ' + '}
									</Fragment>
								))}
							</td>
							<td>{title}</td>
						</tr>
					))}
				</tbody>
			</table>
			<p>
				The editors use{' '}
				<a
					href="https://codemirror.net/docs/ref/#commands.defaultKeymap"
					target="_blank"
					rel="noopener noreferrer"
				>
					CodeMirror key bindings
				</a>{' '}
				that add more short keys.
			</p>
		</div>
	);
};

export const ShortKeysDialog = ({ showDialog, handleOpenShortKeysDialog }) => {
	if (showDialog !== 'short-keys') {
		return null;
	}

	return (
		<Modal
			title="Keyboard Shortcuts"
			onRequestClose={() => handleOpenShortKeysDialog(false)}
			className="wpgraphql-ide-short-keys-modal"
		>
			<ShortKeys />
		</Modal>
	);
};
