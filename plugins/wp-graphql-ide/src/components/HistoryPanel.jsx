import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { Icon, backup } from '@wordpress/icons';
import hooks from '../wordpress-hooks';
import { useDialog } from './dialogs/DialogProvider';
import { HistoryEntry } from './HistoryEntry';

/**
 * History panel icon for the activity bar.
 */
export const HistoryIcon = () => <Icon icon={backup} />;

/**
 * History panel content.
 *
 * Displays execution history for the current visitor. Clicking an
 * entry restores its query, variables, and headers into a new tab.
 *
 * Backed entirely by localStorage via `src/api/history.js` — buckets
 * are scoped per (WordPress user, IDE context) so admins sharing a
 * browser don't see each other's history and the admin-IDE bucket
 * stays distinct from the public-endpoint bucket. Works identically
 * for anonymous public-endpoint visitors and logged-in admins.
 */
export function HistoryPanel() {
	const { confirm } = useDialog();
	const history = useSelect(
		(select) => select('wpgraphql-ide/app').getHistory(),
		[]
	);

	const { setQuery, setVariables, setHeaders, clearAllHistory } =
		useDispatch('wpgraphql-ide/app');

	const { createTab } = useDispatch('wpgraphql-ide/document-editor');

	const avatarUrl = window.WPGRAPHQL_IDE_DATA?.context?.avatarUrl || '';

	const restoreEntry = async (entry) => {
		// Tab title derives from the restored query; no need to compute a name.
		await createTab('');
		setQuery(entry.query || '');
		setVariables(entry.variables || '');
		setHeaders(entry.headers || '');
	};

	if (history.length === 0) {
		return (
			<div className="wpgraphql-ide-history-panel wpgraphql-ide-history-panel--empty">
				<div className="wpgraphql-ide-history-empty-state">
					<Icon
						icon={backup}
						size={32}
						className="wpgraphql-ide-history-empty-icon"
					/>
					<h3 className="wpgraphql-ide-history-empty-title">
						{__('No executions yet', 'wpgraphql-ide')}
					</h3>
					<p className="wpgraphql-ide-history-empty-description">
						{__(
							'Run a query and the request will appear here. Click any entry to restore its query, variables, and headers into a new tab.',
							'wpgraphql-ide'
						)}
					</p>
				</div>
			</div>
		);
	}

	return (
		<div className="wpgraphql-ide-history-panel">
			<ul className="wpgraphql-ide-history-list">
				{history.map((entry) => (
					<HistoryEntry
						key={entry.id}
						entry={entry}
						onRestore={restoreEntry}
						avatarUrl={avatarUrl}
					/>
				))}
			</ul>
			<div className="wpgraphql-ide-history-footer">
				<Button
					variant="link"
					isDestructive
					onClick={async () => {
						const ok = await confirm({
							title: __('Clear all history', 'wpgraphql-ide'),
							message: __(
								'This will remove every entry in your execution history. This cannot be undone.',
								'wpgraphql-ide'
							),
							confirmLabel: __('Clear all', 'wpgraphql-ide'),
							isDestructive: true,
						});
						if (ok) {
							clearAllHistory();
							hooks.doAction(
								'wpgraphql-ide.notice',
								__('History cleared', 'wpgraphql-ide')
							);
						}
					}}
					size="small"
				>
					{__('Clear all', 'wpgraphql-ide')}
				</Button>
			</div>
		</div>
	);
}
