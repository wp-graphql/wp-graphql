import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { Icon, backup } from '@wordpress/icons';
import hooks from '../wordpress-hooks';
import { useDialog } from './dialogs/DialogProvider';
import { OperationHistoryEntry } from './OperationHistoryEntry';

/**
 * History panel icon for the activity bar.
 */
export const HistoryIcon = () => <Icon icon={backup} />;

/**
 * Activity-bar History panel.
 *
 * Shows distinct **operations** rather than raw executions: running the
 * same query ten times in a row collapses into one row labeled "10 runs"
 * (GraphiQL / Apollo Studio / Altair style). Backed by the in-memory
 * `getOperationHistory` selector, which dedupes the underlying flat
 * localStorage log by content-addressed `operationHash`.
 *
 * Click behavior is "switch-or-spawn": if the operation's hash matches
 * an existing published `graphql_document`'s slug (Smart Cache's
 * content-addressed identity), we switch to that tab so the user keeps
 * working with the saved doc. Otherwise we spawn a fresh draft tab
 * pre-filled with the most-recent variables and headers used.
 *
 * Per-execution detail (timestamps, durations, statuses) lives in the
 * Request-history tab in the response pane — only enabled for published
 * documents, where per-run observability ties to a stable identity.
 *
 * Storage is browser-local, scoped per (WordPress user × IDE context),
 * so admins sharing a browser don't see each other's history and the
 * admin-IDE bucket stays distinct from the public-endpoint bucket.
 */
export function HistoryPanel() {
	const { confirm } = useDialog();
	const operations = useSelect(
		(select) => select('wpgraphql-ide/app').getOperationHistory(),
		[]
	);
	const allDocuments = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getDocuments(),
		[]
	);

	const { setQuery, setVariables, setHeaders, clearAllHistory } =
		useDispatch('wpgraphql-ide/app');

	const { createTab, switchTab } = useDispatch(
		'wpgraphql-ide/document-editor'
	);

	const restoreOperation = async (group) => {
		// Match against a published doc by Smart Cache slug. If we find
		// one, switching is more useful than spawning a fresh draft —
		// the user gets back to the saved doc they already had.
		if (group.hash) {
			const existing = allDocuments.find(
				(d) => d.slug === group.hash && d.status === 'publish'
			);
			if (existing) {
				switchTab(String(existing.id));
				return;
			}
		}
		// Fallback: open a new draft tab pre-filled with the most-recent
		// captured context for this operation.
		await createTab('');
		setQuery(group.lastQuery || '');
		setVariables(group.lastVariables || '');
		setHeaders(group.lastHeaders || '');
	};

	if (operations.length === 0) {
		return (
			<div className="wpgraphql-ide-history-panel wpgraphql-ide-history-panel--empty">
				<div className="wpgraphql-ide-history-empty-state">
					<Icon
						icon={backup}
						size={32}
						className="wpgraphql-ide-history-empty-icon"
					/>
					<h3 className="wpgraphql-ide-history-empty-title">
						{__('No operations yet', 'wpgraphql-ide')}
					</h3>
					<p className="wpgraphql-ide-history-empty-description">
						{__(
							'Run a query and the operation will appear here. Click any row to open it; published copies open in place, drafts open in a new tab.',
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
				{operations.map((group) => (
					<OperationHistoryEntry
						key={group.hash || `legacy-${group.latestRun}`}
						group={group}
						onRestore={restoreOperation}
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
