import React from 'react';
import { useSelect } from '@wordpress/data';
import { Icon, backup } from '@wordpress/icons';

/**
 * History panel icon for the activity bar.
 */
export const HistoryIcon = () => <Icon icon={backup} />;

/**
 * History panel content.
 *
 * Displays the execution history for the active document, showing
 * timestamp, duration, and status for each past execution.
 */
export function HistoryPanel() {
	const activeDocument = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getActiveDocument(),
		[]
	);

	const history = activeDocument?.history || [];

	if (!activeDocument) {
		return (
			<div className="wpgraphql-ide-history-panel">
				<p className="wpgraphql-ide-history-empty">
					No document selected.
				</p>
			</div>
		);
	}

	if (history.length === 0) {
		return (
			<div className="wpgraphql-ide-history-panel">
				<div className="wpgraphql-ide-history-header">History</div>
				<p className="wpgraphql-ide-history-empty">
					No executions yet. Run a query to see history.
				</p>
			</div>
		);
	}

	return (
		<div className="wpgraphql-ide-history-panel">
			<div className="wpgraphql-ide-history-header">History</div>
			<ul className="wpgraphql-ide-history-list">
				{[...history].reverse().map((entry, index) => {
					const date = new Date(entry.timestamp * 1000);
					const timeStr = date.toLocaleTimeString();
					const dateStr = date.toLocaleDateString();

					return (
						<li
							key={`${entry.timestamp}-${index}`}
							className={`wpgraphql-ide-history-entry wpgraphql-ide-history-${entry.status}`}
						>
							<div className="wpgraphql-ide-history-entry-header">
								<span
									className={`wpgraphql-ide-history-status wpgraphql-ide-history-status--${entry.status}`}
								>
									{entry.status === 'success' ? 'OK' : 'ERR'}
								</span>
								<span className="wpgraphql-ide-history-duration">
									{entry.duration_ms}ms
								</span>
							</div>
							<div className="wpgraphql-ide-history-entry-time">
								{dateStr} {timeStr}
							</div>
							{entry.response_summary && (
								<div className="wpgraphql-ide-history-entry-preview">
									{entry.response_summary.slice(0, 100)}
									{entry.response_summary.length > 100
										? '...'
										: ''}
								</div>
							)}
						</li>
					);
				})}
			</ul>
		</div>
	);
}
