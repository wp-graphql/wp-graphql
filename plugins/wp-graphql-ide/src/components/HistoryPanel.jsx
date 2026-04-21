import React from 'react';
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
 * Displays the execution history for the active document. Clicking
 * an entry restores its variables, headers, and response.
 */
export function HistoryPanel() {
	const activeDocument = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getActiveDocument(),
		[]
	);

	const { setVariables, setHeaders, setResponse } =
		useDispatch('wpgraphql-ide/app');

	const { saveDocument } = useDispatch('wpgraphql-ide/document-editor');

	const clearHistory = () => {
		if (activeDocument) {
			saveDocument(activeDocument.id, { history: [] });
		}
	};

	const history = activeDocument?.history || [];

	const restoreEntry = (entry) => {
		if (entry.variables) {
			setVariables(entry.variables);
		}
		if (entry.headers) {
			setHeaders(entry.headers);
		}
		if (entry.response_summary) {
			setResponse(entry.response_summary);
		}
	};

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
				<p className="wpgraphql-ide-history-empty">
					No executions yet. Run a query to see history.
				</p>
			</div>
		);
	}

	return (
		<div className="wpgraphql-ide-history-panel">
			<div className="wpgraphql-ide-history-actions">
				<Button
					variant="link"
					isDestructive
					onClick={clearHistory}
					size="small"
				>
					Clear history
				</Button>
			</div>
			<ul className="wpgraphql-ide-history-list">
				{[...history].reverse().map((entry, index) => (
					<li
						key={`${entry.timestamp}-${index}`}
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
									{entry.status === 'success' ? 'OK' : 'ERR'}
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
							{entry.response_summary && (
								<div className="wpgraphql-ide-history-entry-preview">
									{entry.response_summary.slice(0, 120)}
									{entry.response_summary.length > 120
										? '...'
										: ''}
								</div>
							)}
						</button>
					</li>
				))}
			</ul>
		</div>
	);
}
