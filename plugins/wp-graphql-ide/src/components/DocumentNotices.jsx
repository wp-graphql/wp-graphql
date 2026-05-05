import React from 'react';
import { Button, Notice } from '@wordpress/components';

/**
 * Document-level notices row.
 *
 * Renders inside the query pane, just below the editor toolbar, to surface
 * persistent, state-derived facts about the current document (e.g. "this
 * doc is published and read-only"). Scoped to the document area so the
 * user understands the state applies to the document itself, not the
 * workspace. Not for transient feedback — that's what the snackbar is for.
 *
 * Built on `@wordpress/components` `Notice` so the body styling matches
 * WP admin notices. The action is rendered as an inline `variant="link"`
 * Button inside the message rather than via Notice's `actions` prop —
 * the prop renders a full secondary button on its own line, which felt
 * far too heavy for what should read as a single-sentence call to action.
 *
 * @param {Object}   props
 * @param {boolean}  props.isPublished   Whether the active document is published.
 * @param {Function} [props.onDuplicate] Spawn a draft copy of the current doc.
 * @return {JSX.Element|null}
 */
export function DocumentNotices({ isPublished, onDuplicate }) {
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
				This document is published and read-only. Other apps may rely on
				it.{' '}
				{onDuplicate && (
					<Button
						variant="link"
						onClick={onDuplicate}
						className="wpgraphql-ide-document-notice-link"
					>
						Duplicate to edit
					</Button>
				)}
			</Notice>
		</div>
	);
}
