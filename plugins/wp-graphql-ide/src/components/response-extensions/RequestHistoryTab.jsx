import React, { useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { Icon, backup } from '@wordpress/icons';
import { RequestHistoryEntry } from '../RequestHistoryEntry';

/**
 * Per-document execution log for a published `graphql_document`.
 *
 * Smart Cache makes published docs **content-addressed** — the slug is
 * the sha256 of the normalized query. Every history entry written by
 * `addHistoryEntry` carries the same hash on `operationHash`, so this
 * tab can show every run of *this* document's content (including runs
 * made while it was still a draft) by filtering the flat history log
 * by `entry.operationHash === activeDocument.slug`.
 *
 * The tab is gated by the `predicate` registered at the registry: it
 * only appears when `activeDocument.status === 'publish'`. Ephemeral /
 * draft documents don't have a stable identity to observe against.
 *
 * Clicking a row restores the variables and headers from that
 * particular run onto the current editor — the query itself is the
 * published one and stays immutable.
 *
 * @since x-release-please-version
 */
export function RequestHistoryTab() {
	const history = useSelect(
		(select) => select('wpgraphql-ide/app').getHistory(),
		[]
	);
	const activeDocument = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getActiveDocument(),
		[]
	);
	const { setVariables, setHeaders } = useDispatch('wpgraphql-ide/app');
	const avatarUrl = window.WPGRAPHQL_IDE_DATA?.context?.avatarUrl || '';

	const runs = useMemo(() => {
		if (!activeDocument?.slug) {
			return [];
		}
		return (history || []).filter(
			(e) => e.operationHash && e.operationHash === activeDocument.slug
		);
	}, [history, activeDocument?.slug]);

	const restore = (entry) => {
		setVariables(entry.variables || '');
		setHeaders(entry.headers || '');
	};

	if (runs.length === 0) {
		return (
			<div className="wpgraphql-ide-history-panel wpgraphql-ide-history-panel--empty">
				<div className="wpgraphql-ide-history-empty-state">
					<Icon
						icon={backup}
						size={32}
						className="wpgraphql-ide-history-empty-icon"
					/>
					<h3 className="wpgraphql-ide-history-empty-title">
						{__('No runs recorded yet', 'wpgraphql-ide')}
					</h3>
					<p className="wpgraphql-ide-history-empty-description">
						{__(
							'Execute this query and the request will appear here. Click a row to re-apply that run’s variables and headers.',
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
				{runs.map((entry) => (
					<RequestHistoryEntry
						key={entry.id}
						entry={entry}
						onRestore={restore}
						avatarUrl={avatarUrl}
					/>
				))}
			</ul>
		</div>
	);
}
