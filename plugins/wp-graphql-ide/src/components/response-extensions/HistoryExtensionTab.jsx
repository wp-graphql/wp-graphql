import React, { useMemo } from 'react';
import { Button } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { Icon, backup } from '@wordpress/icons';
import { isUserLoggedIn } from '../../bootstrap';
import { isTempId } from '../../utils/document-id';
import { useDialog } from '../dialogs/DialogProvider';
import { InlineSignInPrompt } from '../InlineSignInPrompt';
import hooks from '../../wordpress-hooks';

const HISTORY_TIME_FORMATTER = new Intl.DateTimeFormat(undefined, {
	month: 'short',
	day: 'numeric',
	hour: 'numeric',
	minute: '2-digit',
	hour12: true,
});

function queryPreview(query) {
	if (!query) {
		return '';
	}
	return query.replace(/\s+/g, ' ').trim().slice(0, 100);
}

/**
 * Document-scoped execution history surfaced in the response panel.
 *
 * The global History panel (left activity bar) shows every execution
 * regardless of which document it ran against, and restores into a new
 * tab. This view is the inverse: just *this* document's executions,
 * restoring in place — useful for "rerun what I did 10 minutes ago
 * with those exact variables and headers."
 *
 * Filtering hinges on `entry.document_id`. The API coerces temp-id
 * documents to `0` at write time, so unsaved drafts have no
 * meaningful per-doc history; the empty state explains the gate.
 *
 * Click action restores `query`, `variables`, and `headers` from the
 * entry's snapshot into the active document — the query is included
 * because edits between executions are common and "rerun exactly what
 * I ran" is the load-bearing use case here.
 */
export function HistoryExtensionTab() {
	if (!isUserLoggedIn) {
		return (
			<div className="wpgraphql-ide-history-panel wpgraphql-ide-history-panel--empty wpgraphql-ide-history-panel--in-response">
				<InlineSignInPrompt
					title="Sign in to see this document's history"
					description="Executions are saved per user — sign in to keep a per-document record you can replay with a click."
				/>
			</div>
		);
	}
	return <HistoryExtensionTabContent />;
}

function HistoryExtensionTabContent() {
	const { confirm } = useDialog();
	const history = useSelect(
		(select) => select('wpgraphql-ide/app').getHistory(),
		[]
	);
	const activeDocument = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getActiveDocument(),
		[]
	);

	const { setQuery, setVariables, setHeaders } =
		useDispatch('wpgraphql-ide/app');

	const avatarUrl = window.WPGRAPHQL_IDE_DATA?.context?.avatarUrl || '';

	const activeDocId = activeDocument?.id ?? null;
	const isTempDoc = activeDocId !== null && isTempId(activeDocId);
	const numericDocId =
		activeDocId !== null && !isTempDoc ? Number(activeDocId) : null;

	const scopedHistory = useMemo(() => {
		if (numericDocId === null || !Number.isFinite(numericDocId)) {
			return [];
		}
		return history.filter(
			(entry) => Number(entry.document_id) === numericDocId
		);
	}, [history, numericDocId]);

	const restoreEntry = async (entry) => {
		// Restore in place — the user is already on this document's
		// history. Treat the editor state as ours to overwrite, but
		// confirm first when the doc has unsaved edits we'd clobber.
		if (activeDocument?.dirty) {
			const ok = await confirm({
				title: 'Replace current edits?',
				message:
					'This document has unsaved changes. Restoring this execution will overwrite the current query, variables, and headers. The history entry stays intact either way.',
				confirmLabel: 'Restore',
				isDestructive: true,
			});
			if (!ok) {
				return;
			}
		}
		setQuery(entry.query || '');
		setVariables(entry.variables || '');
		setHeaders(entry.headers || '');
		hooks.doAction(
			'wpgraphql-ide.notice',
			`Restored execution from ${HISTORY_TIME_FORMATTER.format(
				new Date(entry.timestamp * 1000)
			)}`
		);
	};

	if (!activeDocument) {
		return (
			<div className="wpgraphql-ide-history-panel wpgraphql-ide-history-panel--empty wpgraphql-ide-history-panel--in-response">
				<div className="wpgraphql-ide-history-empty-state">
					<p className="wpgraphql-ide-history-empty-description">
						No document is active.
					</p>
				</div>
			</div>
		);
	}

	if (isTempDoc) {
		return (
			<div className="wpgraphql-ide-history-panel wpgraphql-ide-history-panel--empty wpgraphql-ide-history-panel--in-response">
				<div className="wpgraphql-ide-history-empty-state">
					<Icon
						icon={backup}
						size={28}
						className="wpgraphql-ide-history-empty-icon"
					/>
					<h3 className="wpgraphql-ide-history-empty-title">
						Save the document to track its history
					</h3>
					<p className="wpgraphql-ide-history-empty-description">
						Executions on unsaved drafts are kept in the global
						History panel, but per-document tracking only kicks in
						once the document has a permanent ID.
					</p>
				</div>
			</div>
		);
	}

	if (scopedHistory.length === 0) {
		return (
			<div className="wpgraphql-ide-history-panel wpgraphql-ide-history-panel--empty wpgraphql-ide-history-panel--in-response">
				<div className="wpgraphql-ide-history-empty-state">
					<Icon
						icon={backup}
						size={28}
						className="wpgraphql-ide-history-empty-icon"
					/>
					<h3 className="wpgraphql-ide-history-empty-title">
						No executions on this document yet
					</h3>
					<p className="wpgraphql-ide-history-empty-description">
						Run a query and the request will appear here. Click any
						entry to restore its query, variables, and headers into
						this document.
					</p>
				</div>
			</div>
		);
	}

	return (
		<div className="wpgraphql-ide-history-panel wpgraphql-ide-history-panel--in-response">
			<ul className="wpgraphql-ide-history-list">
				{scopedHistory.map((entry) => (
					<li key={entry.id} className="wpgraphql-ide-history-entry">
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
									{entry.status === 'success' ? 'OK' : 'ERR'}
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
							{entry.query && (
								<div className="wpgraphql-ide-history-entry-detail">
									<span className="wpgraphql-ide-history-entry-preview">
										{queryPreview(entry.query)}
									</span>
								</div>
							)}
						</button>
					</li>
				))}
			</ul>
			<div className="wpgraphql-ide-history-footer wpgraphql-ide-history-footer--in-response">
				<span className="wpgraphql-ide-history-footer-count">
					{scopedHistory.length}{' '}
					{scopedHistory.length === 1 ? 'execution' : 'executions'}{' '}
					for this document
				</span>
				<Button
					variant="link"
					size="small"
					onClick={() => {
						hooks.doAction('wpgraphql-ide.open-history-panel');
					}}
				>
					View global history
				</Button>
			</div>
		</div>
	);
}
