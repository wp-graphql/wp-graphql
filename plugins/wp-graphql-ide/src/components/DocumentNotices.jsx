import React, { useState, useCallback } from 'react';
import { Button, Notice } from '@wordpress/components';
import { Icon, chevronDown, chevronUp } from '@wordpress/icons';
import { useDispatch } from '@wordpress/data';
import { useDebouncedCallback } from '../hooks/useDebouncedCallback';

/**
 * IDs of notices that support an expand/collapse with extra detail.
 * Each notice is identified by a stable string key so the persisted
 * collapsed state survives schema changes (don't rename without a
 * migration of the user-meta blob).
 */
const NOTICE_PUBLISHED_READONLY = 'doc-published-readonly';

function readCollapsedNotices() {
	const data = window.WPGRAPHQL_IDE_DATA || {};
	const list = Array.isArray(data.collapsedNotices)
		? data.collapsedNotices
		: [];
	return new Set(list);
}

/**
 * Document-level notices row.
 *
 * Renders inside the query pane, just below the editor toolbar, to
 * surface persistent, state-derived facts about the current document
 * (e.g. "this doc is published and read-only"). Scoped to the document
 * area so the user understands the state applies to the document
 * itself, not the workspace. Not for transient feedback — the snackbar
 * handles that.
 *
 * Each notice supports an expand/collapse with extra detail; the user's
 * choice persists per WordPress user via the
 * `wpgraphql_ide_collapsed_notices` user meta. A persisted collapsed
 * state means the user has already read the long form and only wants
 * the one-line summary going forward.
 *
 * @param {Object}   props
 * @param {boolean}  props.isPublished   Whether the active document is published.
 * @param {Function} [props.onDuplicate] Spawn a draft copy of the current doc.
 * @return {JSX.Element|null}
 */
export function DocumentNotices({ isPublished, onDuplicate }) {
	const [collapsed, setCollapsed] = useState(() =>
		readCollapsedNotices().has(NOTICE_PUBLISHED_READONLY)
	);

	const { saveUserPreference } = useDispatch('wpgraphql-ide/app');

	// Debounced write so a fast toggle (or a dev double-click) only hits
	// the network once.
	const [persistCollapsed] = useDebouncedCallback((set) => {
		saveUserPreference('collapsed_notices', Array.from(set));
	}, 300);

	const toggle = useCallback(() => {
		setCollapsed((prev) => {
			const next = !prev;
			const set = readCollapsedNotices();
			if (next) {
				set.add(NOTICE_PUBLISHED_READONLY);
			} else {
				set.delete(NOTICE_PUBLISHED_READONLY);
			}
			persistCollapsed(set);
			// Mirror the change onto the bootstrap blob so a remount
			// (e.g. switching tabs and back) hydrates from the latest
			// state without waiting for the REST write to round-trip.
			if (window.WPGRAPHQL_IDE_DATA) {
				window.WPGRAPHQL_IDE_DATA.collapsedNotices = Array.from(set);
			}
			return next;
		});
	}, [persistCollapsed]);

	if (!isPublished) {
		return null;
	}

	return (
		<div className="wpgraphql-ide-document-notices">
			<Notice
				status="info"
				isDismissible={false}
				className="wpgraphql-ide-document-notice"
			>
				{/* The whole header row is the disclosure target so the
				    user has a generous click area; "Duplicate as draft"
				    is a nested link that stops propagation so it can
				    fire without flipping the disclosure. The chevron
				    remains as the visual cue, no longer its own button. */}
				<div
					className="wpgraphql-ide-document-notice-header"
					role="button"
					tabIndex={0}
					aria-expanded={!collapsed}
					aria-controls="wpgraphql-ide-document-notice-detail"
					aria-label={
						collapsed
							? 'Show details about read-only queries'
							: 'Hide details about read-only queries'
					}
					onClick={toggle}
					onKeyDown={(e) => {
						if (e.key === 'Enter' || e.key === ' ') {
							e.preventDefault();
							toggle();
						}
					}}
				>
					<span className="wpgraphql-ide-document-notice-summary">
						This query is published and read-only.
						{onDuplicate && (
							<>
								{' '}
								<Button
									variant="link"
									onClick={(e) => {
										e.stopPropagation();
										onDuplicate();
									}}
									className="wpgraphql-ide-document-notice-link"
								>
									Duplicate as draft
								</Button>{' '}
								to keep iterating.
							</>
						)}
					</span>
					<Icon
						icon={collapsed ? chevronDown : chevronUp}
						size={18}
						className="wpgraphql-ide-document-notice-chevron"
						aria-hidden="true"
					/>
				</div>
				{!collapsed && (
					<div
						id="wpgraphql-ide-document-notice-detail"
						className="wpgraphql-ide-document-notice-detail"
					>
						<p>
							Other apps — mobile clients, persisted-query caches,
							automation — reference this query by its stable ID.
							Editing it would change the ID and silently break
							them.
						</p>
						<p>
							<strong>Duplicate as draft</strong> creates an
							editable copy. Publishing the copy produces a new,
							separate ID, so consumers can adopt it on their own
							schedule.
						</p>
					</div>
				)}
			</Notice>
		</div>
	);
}
