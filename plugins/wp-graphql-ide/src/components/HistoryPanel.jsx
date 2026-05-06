import React from 'react';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { Icon, backup } from '@wordpress/icons';
import hooks from '../wordpress-hooks';
import { useDialog } from './dialogs/DialogProvider';
import { deriveDocTitle } from '../utils/derive-doc-title';

const HISTORY_TIME_FORMATTER = new Intl.DateTimeFormat(undefined, {
	month: 'short',
	day: 'numeric',
	hour: 'numeric',
	minute: '2-digit',
	hour12: true,
});

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
						No executions yet
					</h3>
					<p className="wpgraphql-ide-history-empty-description">
						Run a query and the request will appear here. Click any
						entry to restore its query, variables, and headers into
						a new tab.
					</p>
				</div>
			</div>
		);
	}

	return (
		<div className="wpgraphql-ide-history-panel">
			<ul className="wpgraphql-ide-history-list">
				{history.map((entry) => {
					const derived = deriveDocTitle(entry.query);
					const label = derived === 'Untitled' ? null : derived;

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
										{HISTORY_TIME_FORMATTER.format(
											new Date(entry.timestamp * 1000)
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
