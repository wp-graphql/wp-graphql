import { Dialog } from '@graphiql/react';
import React from 'react';
import { Fragment } from '@wordpress/element';

const modifier =
	typeof window !== 'undefined' &&
	window.navigator.platform.toLowerCase().indexOf('mac') === 0
		? 'Cmd'
		: 'Ctrl';

const SHORT_KEYS = Object.entries({
	'Search in editor': [modifier, 'F'],
	'Search in documentation': [modifier, 'K'],
	'Execute query': [modifier, 'Enter'],
	'Prettify editors': ['Ctrl', 'Shift', 'P'],
	'Merge fragments definitions into operation definition': [
		'Ctrl',
		'Shift',
		'M',
	],
	'Copy query': ['Ctrl', 'Shift', 'C'],
	'Re-fetch schema using introspection': ['Ctrl', 'Shift', 'R'],
});

const ShortKeys = ({ keyMap }) => {
	return (
		<div>
			<table className="graphiql-table">
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
										<code className="graphiql-key">
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
					href="https://codemirror.net/5/doc/manual.html#keymaps"
					target="_blank"
					rel="noopener noreferrer"
				>
					CodeMirror Key Maps
				</a>{' '}
				that add more short keys. This instance of Graph<em>i</em>QL
				uses <code>{keyMap}</code>.
			</p>
		</div>
	);
};

export const ShortKeysDialog = ({
	showDialog,
	handleOpenShortKeysDialog,
	keyMap,
}) => {
	return (
		<Dialog
			open={showDialog === 'short-keys'}
			onOpenChange={handleOpenShortKeysDialog}
		>
			<div className="graphiql-dialog-header">
				<Dialog.Title className="graphiql-dialog-title">
					Short Keys
				</Dialog.Title>
				<Dialog.Close />
			</div>
			<div className="graphiql-dialog-section">
				<ShortKeys keyMap={keyMap || 'sublime'} />
			</div>
		</Dialog>
	);
};
