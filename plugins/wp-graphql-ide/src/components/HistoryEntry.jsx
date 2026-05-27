import React from 'react';
import { __ } from '@wordpress/i18n';
import { deriveDocTitle } from '../utils/derive-doc-title';

/**
 * GraphQL fragment for the IdeHistoryEntry fields this component (and its
 * parent's restore action) consumes. Exported so the data layer can compose
 * it into the outgoing operation document without re-declaring the field
 * selection in `src/api/history.js` — when this component starts rendering
 * a new field, the only place to update is this file.
 *
 * Wire shape (GraphQL field names). The adapter in `src/api/history.js`
 * maps these to the snake_case shape the component actually reads from
 * `props.entry` (`durationMs` → `duration_ms`, etc.). If you add a field
 * here you also need a line in `adaptHistoryEntry` until codegen lands.
 *
 * @since x-release-please-version
 */
export const HISTORY_ENTRY_FRAGMENT = `
	fragment IdeHistoryEntryFields on IdeHistoryEntry {
		id
		databaseId
		date
		queryString
		variables
		headers
		durationMs
		executionStatus
		documentId
		isAuthenticated
		httpMethod
	}
`;

const HISTORY_TIME_FORMATTER = new Intl.DateTimeFormat(undefined, {
	month: 'short',
	day: 'numeric',
	hour: 'numeric',
	minute: '2-digit',
	hour12: true,
});

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
 * One row in the history list — method / status / duration / time pill
 * header above a query label and one-line preview. Clicking restores
 * the entry into a new tab; the parent owns the actual restore action.
 *
 * Pure presentational leaf so the data dependency (the exported
 * fragment above) and the rendering live in the same file.
 *
 * @param {Object}   props
 * @param {Object}   props.entry       - Adapted history entry. Shape matches `adaptHistoryEntry` in `src/api/history.js`.
 * @param {Function} props.onRestore   - Called with the entry when the row is clicked.
 * @param {string}   [props.avatarUrl] - Optional avatar to render in the row header.
 *
 * @since x-release-please-version
 */
export function HistoryEntry({ entry, onRestore, avatarUrl }) {
	const derived = deriveDocTitle(entry.query);
	// `deriveDocTitle` returns the literal 'Untitled' fallback; matching
	// the literal here is the contract — translation happens at display
	// time below.
	const label = derived === 'Untitled' ? null : derived;

	return (
		<li className="wpgraphql-ide-history-entry">
			<button
				type="button"
				className="wpgraphql-ide-history-entry-button"
				onClick={() => onRestore(entry)}
			>
				<div className="wpgraphql-ide-history-entry-header">
					{avatarUrl && (
						<img
							src={avatarUrl}
							alt={
								entry.is_authenticated !== false
									? __('Authenticated', 'wpgraphql-ide')
									: __('Public', 'wpgraphql-ide')
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
							? __('OK', 'wpgraphql-ide')
							: __('ERR', 'wpgraphql-ide')}
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
						{label || __('Anonymous query', 'wpgraphql-ide')}
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
}
