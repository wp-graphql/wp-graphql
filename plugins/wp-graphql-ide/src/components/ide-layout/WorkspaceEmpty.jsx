import React from 'react';

/**
 * Empty-state surface shown in the editor area when no tabs are open.
 *
 * The persistent tab strip above this surface keeps the `+` button in
 * its muscle-memory position, so this state is ambient guidance only —
 * no redundant CTA. Users either click the `+` (top-left) or pick a
 * saved doc from the activity-bar Documents panel.
 */
export function WorkspaceEmpty() {
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
			<h3 className="wpgraphql-ide-empty-title">No queries open</h3>
			<p className="wpgraphql-ide-empty-description">
				Click the + button to start a new query, or open one from Saved
				Queries.
			</p>
		</div>
	);
}
