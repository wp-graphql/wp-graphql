import React, { useState } from 'react';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { dateI18n } from '@wordpress/date';
import { Icon, backup } from '@wordpress/icons';

/**
 * History panel icon for the activity bar.
 */
export const HistoryIcon = () => <Icon icon={backup} />;

/**
 * History panel content.
 *
 * Displays global execution history. Can be filtered to show only
 * entries from the active document.
 */
export function HistoryPanel() {
	const [filterByDoc, setFilterByDoc] = useState(false);

	const history = useSelect(
		(select) => select('wpgraphql-ide/app').getHistory(),
		[]
	);

	const activeDocument = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getActiveDocument(),
		[]
	);

	const { setQuery, setVariables, setHeaders, clearAllHistory } =
		useDispatch('wpgraphql-ide/app');

	const restoreEntry = (entry) => {
		setQuery(entry.query || '');
		setVariables(entry.variables || '');
		setHeaders(entry.headers || '');
	};

	const displayedHistory =
		filterByDoc && activeDocument
			? history.filter((e) => e.document_id === activeDocument.id)
			: history;

	if (history.length === 0) {
		return (
			<div className="wpgraphql-ide-history-panel wpgraphql-ide-history-panel--empty">
				<div className="wpgraphql-ide-history-empty-state">
					<p>No executions yet</p>
					<p className="wpgraphql-ide-history-empty-hint">
						Press <kbd>Cmd</kbd>+<kbd>Enter</kbd> to execute a query
					</p>
				</div>
			</div>
		);
	}

	return (
		<div className="wpgraphql-ide-history-panel">
			<div className="wpgraphql-ide-history-actions">
				{activeDocument && (
					<Button
						variant={filterByDoc ? 'primary' : 'secondary'}
						onClick={() => setFilterByDoc(!filterByDoc)}
						size="small"
					>
						This document
					</Button>
				)}
				<Button
					variant="link"
					isDestructive
					onClick={clearAllHistory}
					size="small"
				>
					Clear all
				</Button>
			</div>
			{displayedHistory.length === 0 ? (
				<p className="wpgraphql-ide-history-empty">
					No history for this document.
				</p>
			) : (
				<ul className="wpgraphql-ide-history-list">
					{displayedHistory.map((entry) => (
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
								</div>
								{entry.query && (
									<div className="wpgraphql-ide-history-entry-preview">
										{entry.query.slice(0, 120)}
										{entry.query.length > 120 ? '...' : ''}
									</div>
								)}
							</button>
						</li>
					))}
				</ul>
			)}
		</div>
	);
}
