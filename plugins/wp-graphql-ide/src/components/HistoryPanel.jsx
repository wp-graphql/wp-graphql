import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { Icon, backup } from '@wordpress/icons';
import hooks from '../wordpress-hooks';
import { useDialog } from './dialogs/DialogProvider';
import { OperationHistoryEntry } from './OperationHistoryEntry';
import { computeOperationHash } from '../utils/operation-hash';

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
 * Click resolution (in order):
 * 1. **Any already-open tab whose current content matches** — preferred
 *    so the user keeps their in-progress draft state intact. Published
 *    tabs short-circuit on their Smart Cache slug; drafts compute the
 *    hash on demand from the live query.
 * 2. **A published `graphql_document` with a matching slug** that isn't
 *    currently open — `switchTab` will open and activate it.
 * 3. **Spawn a fresh draft** pre-filled with the most-recent captured
 *    variables and headers when no existing tab or doc maps cleanly.
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
	const openTabs = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getOpenTabs(),
		[]
	);

	const { setQuery, setVariables, setHeaders, clearAllHistory } =
		useDispatch('wpgraphql-ide/app');

	const { createTab, switchTab } = useDispatch(
		'wpgraphql-ide/document-editor'
	);

	const restoreOperation = async (group) => {
		if (group.hash) {
			// First pass: any already-open tab whose current content
			// matches? Published tabs short-circuit on their slug; draft
			// tabs need a hash computed on demand (memoized in the util,
			// so re-clicks are free). Switching to an existing tab keeps
			// the user's in-progress state intact.
			for (const tabId of openTabs) {
				const doc = allDocuments.find(
					(d) => String(d.id) === String(tabId)
				);
				if (!doc) {
					continue;
				}
				const candidateHash =
					doc.status === 'publish' && doc.slug
						? doc.slug
						: // eslint-disable-next-line no-await-in-loop
							await computeOperationHash(doc.query || '');
				if (candidateHash && candidateHash === group.hash) {
					switchTab(String(doc.id));
					return;
				}
			}
			// Second pass: a published copy that isn't currently open.
			// `switchTab` will open it before activating.
			const existing = allDocuments.find(
				(d) => d.slug === group.hash && d.status === 'publish'
			);
			if (existing) {
				switchTab(String(existing.id));
				return;
			}
		}
		// Fallback: spawn a fresh draft tab pre-filled with the most-
		// recent captured context for this operation.
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
