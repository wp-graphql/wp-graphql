import React from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { deriveDocTitle } from '../utils/derive-doc-title';

/**
 * Compact human-readable elapsed-time for the activity-bar panel ("3s ago",
 * "12m ago", "2d ago"). Anything older than a week falls through to a date.
 *
 * @param {number} latestRunSec Unix seconds.
 * @return {string}
 */
function relativeTime(latestRunSec) {
	if (!latestRunSec) {
		return '';
	}
	const nowSec = Math.floor(Date.now() / 1000);
	const delta = Math.max(0, nowSec - latestRunSec);
	if (delta < 60) {
		return sprintf(
			/* translators: %d: seconds since last execution */
			__('%ds ago', 'wpgraphql-ide'),
			delta
		);
	}
	if (delta < 3600) {
		return sprintf(
			/* translators: %d: minutes since last execution */
			__('%dm ago', 'wpgraphql-ide'),
			Math.floor(delta / 60)
		);
	}
	if (delta < 86400) {
		return sprintf(
			/* translators: %d: hours since last execution */
			__('%dh ago', 'wpgraphql-ide'),
			Math.floor(delta / 3600)
		);
	}
	if (delta < 604800) {
		return sprintf(
			/* translators: %d: days since last execution */
			__('%dd ago', 'wpgraphql-ide'),
			Math.floor(delta / 86400)
		);
	}
	return new Intl.DateTimeFormat(undefined, {
		month: 'short',
		day: 'numeric',
	}).format(new Date(latestRunSec * 1000));
}

function queryPreview(query) {
	if (!query) {
		return '';
	}
	return query.replace(/\s+/g, ' ').trim().slice(0, 100);
}

/**
 * One row in the activity-bar History panel — represents a distinct
 * operation (deduped by content hash) across however many runs. The
 * subtitle surfaces the run count and how long ago the most-recent run
 * happened so the panel reads as "operations I've worked with" instead
 * of "raw event log".
 *
 * Clicking dispatches the parent's `onRestore(group)` — the parent
 * decides whether to switch to an existing published copy or spawn a
 * new draft tab.
 *
 * @param {Object}   props
 * @param {Object}   props.group     Output of `getOperationHistory()`.
 * @param {Function} props.onRestore Called with the group when clicked.
 *
 * @since x-release-please-version
 */
export function OperationHistoryEntry({ group, onRestore }) {
	const derived = deriveDocTitle(group.lastQuery);
	const label =
		derived === 'Untitled'
			? __('Anonymous query', 'wpgraphql-ide')
			: derived;

	const runsLabel = sprintf(
		/* translators: %d: number of times this operation has been executed */
		_n('%d run', '%d runs', group.runCount, 'wpgraphql-ide'),
		group.runCount
	);
	const meta = [runsLabel, relativeTime(group.latestRun)]
		.filter(Boolean)
		.join(' · ');

	return (
		<li className="wpgraphql-ide-history-entry wpgraphql-ide-history-entry--operation">
			<button
				type="button"
				className="wpgraphql-ide-history-entry-button"
				onClick={() => onRestore(group)}
			>
				<div className="wpgraphql-ide-history-entry-detail">
					<span className="wpgraphql-ide-history-entry-label">
						{label}
					</span>
					{group.lastQuery && (
						<span className="wpgraphql-ide-history-entry-preview">
							{queryPreview(group.lastQuery)}
						</span>
					)}
					<span className="wpgraphql-ide-history-entry-meta">
						{meta}
					</span>
				</div>
			</button>
		</li>
	);
}
