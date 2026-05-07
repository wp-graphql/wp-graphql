import React from 'react';
import { Button } from '@wordpress/components';

/**
 * Empty-state surface shown in the editor area when no tabs are open.
 * Clicking the button asks the parent to spawn a new untitled draft.
 *
 * @param {Object}   props
 * @param {Function} props.onCreate - Click handler for the "New Document" button.
 */
export function WorkspaceEmpty({ onCreate }) {
	return (
		<div className="wpgraphql-ide-workspace-empty">
			<svg
				className="wpgraphql-ide-empty-icon"
				viewBox="0 0 24 24"
				fill="none"
				xmlns="http://www.w3.org/2000/svg"
			>
				<path
					d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"
					fill="currentColor"
				/>
			</svg>
			<h3 className="wpgraphql-ide-empty-title">No open documents</h3>
			<p className="wpgraphql-ide-empty-description">
				Create a new document to start writing GraphQL queries, or open
				one from the sidebar.
			</p>
			<Button variant="primary" onClick={onCreate}>
				New Document
			</Button>
		</div>
	);
}
