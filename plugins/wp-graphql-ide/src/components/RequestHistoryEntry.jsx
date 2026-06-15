import React from 'react';
import { __ } from '@wordpress/i18n';
import { deriveDocTitle } from '../utils/derive-doc-title';

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
 * One row representing a single execution — method / status / duration /
 * time pill header above a query label and one-line preview. Lives in
 * the Request-history response-pane tab for published documents (every
 * row is one run of that document). Clicking re-applies the entry's
 * variables and headers to the active document; the parent owns the
 * actual restore action.
 *
 * @param {Object}   props
 * @param {Object}   props.entry       - Adapted history entry. Shape matches `createLocalHistoryEntry`'s return value.
 * @param {Function} props.onRestore   - Called with the entry when the row is clicked.
 * @param {string}   [props.avatarUrl] - Optional avatar to render in the row header.
 *
 * @since x-release-please-version
 */
export function RequestHistoryEntry({ entry, onRestore, avatarUrl }) {
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
