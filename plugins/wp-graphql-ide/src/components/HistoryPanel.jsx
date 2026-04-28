import React from 'react';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { dateI18n } from '@wordpress/date';
import { Icon, backup } from '@wordpress/icons';
import hooks from '../wordpress-hooks';
import { useDialog } from './dialogs/DialogProvider';

/**
 * History panel icon for the activity bar.
 */
export const HistoryIcon = () => <Icon icon={backup} />;

/**
 * Extract a clean one-line preview from a GraphQL query string.
 *
 * @param {string} query Raw query string.
 * @return {string} Collapsed single-line preview.
 */
function queryPreview(query) {
	if (!query) {
		return '';
	}
	return query.replace(/\s+/g, ' ').trim().slice(0, 100);
}

/**
 * Extract operation name from a query string.
 *
 * @param {string} query Raw query string.
 * @return {string|null} Operation name or null.
 */
function extractOperationName(query) {
	if (!query) {
		return null;
	}
	const match = query.match(/(?:query|mutation|subscription)\s+(\w+)/);
	return match ? match[1] : null;
}

/**
 * History panel content.
 *
 * Displays global execution history. Clicking an entry restores its
 * query, variables, and headers into the current active tab.
 */
export function HistoryPanel() {
	const { confirm } = useDialog();
	const history = useSelect(
		(select) => select('wpgraphql-ide/app').getHistory(),
		[]
	);

	const allDocuments = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getDocuments(),
		[]
	);

	const { setQuery, setVariables, setHeaders, clearAllHistory } =
		useDispatch('wpgraphql-ide/app');

	const { createTab } = useDispatch('wpgraphql-ide/document-editor');

	const avatarUrl = window.WPGRAPHQL_IDE_DATA?.context?.avatarUrl || '';

	const restoreEntry = async (entry) => {
		const opName = extractOperationName(entry.query);
		const docName = getDocumentName(entry.document_id);
		const timestamp = dateI18n('M j, g:i A', entry.timestamp * 1000);
		const isGenericName =
			!docName || /^(Untitled|New Tab( \d+)?)$/.test(docName);
		let tabName = timestamp;
		if (opName) {
			tabName = opName;
		} else if (!isGenericName) {
			tabName = docName;
		}

		await createTab(tabName);
		setQuery(entry.query || '');
		setVariables(entry.variables || '');
		setHeaders(entry.headers || '');
	};

	const getDocumentName = (docId) => {
		if (!docId) {
			return null;
		}
		const doc = allDocuments.find((d) => String(d.id) === String(docId));
		return doc?.title || null;
	};

	if (history.length === 0) {
		return (
			<div className="wpgraphql-ide-history-panel wpgraphql-ide-history-panel--empty">
				<div className="wpgraphql-ide-history-empty-state">
					<p>No executions yet</p>
				</div>
			</div>
		);
	}

	return (
		<div className="wpgraphql-ide-history-panel">
			<ul className="wpgraphql-ide-history-list">
				{history.map((entry) => {
					const docName = getDocumentName(entry.document_id);
					const opName = extractOperationName(entry.query);
					const label = opName || docName;

					return (
						<li
							key={entry.id}
							className="wpgraphql-ide-history-entry"
						>
							<button
								type="button"
								className="wpgraphql-ide-history-entry-button"
								onClick={() => restoreEntry(entry)}
							>
								<div className="wpgraphql-ide-history-entry-header">
									{avatarUrl && (
										<img
											src={avatarUrl}
											alt={
												entry.is_authenticated !== false
													? 'Authenticated'
													: 'Public'
											}
											className={`wpgraphql-ide-history-avatar${entry.is_authenticated === false ? ' is-public' : ''}`}
										/>
									)}
									<span className="wpgraphql-ide-history-method">
										{entry.http_method || 'POST'}
									</span>
									<span
										className={`wpgraphql-ide-history-status wpgraphql-ide-history-status--${entry.status}`}
									>
										{entry.status === 'success'
											? 'OK'
											: 'ERR'}
									</span>
									<span className="wpgraphql-ide-history-duration">
										{entry.duration_ms}ms
									</span>
									<span className="wpgraphql-ide-history-entry-time">
										{dateI18n(
											'M j, g:i A',
											entry.timestamp * 1000
										)}
									</span>
									<span className="wpgraphql-ide-history-entry-id">
										#{entry.id}
									</span>
								</div>
								<div className="wpgraphql-ide-history-entry-detail">
									<span className="wpgraphql-ide-history-entry-label">
										{label || 'Anonymous query'}
									</span>
									{entry.query && (
										<span className="wpgraphql-ide-history-entry-preview">
											{queryPreview(entry.query)}
										</span>
									)}
								</div>
							</button>
						</li>
					);
				})}
			</ul>
			<div className="wpgraphql-ide-history-footer">
				<Button
					variant="link"
					isDestructive
					onClick={async () => {
						const ok = await confirm({
							title: 'Clear all history',
							message:
								'This will remove every entry in your execution history. This cannot be undone.',
							confirmLabel: 'Clear all',
							isDestructive: true,
						});
						if (ok) {
							clearAllHistory();
							hooks.doAction(
								'wpgraphql-ide.notice',
								'History cleared'
							);
						}
					}}
					size="small"
				>
					Clear all
				</Button>
			</div>
		</div>
	);
}
