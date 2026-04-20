import React from 'react';
import { Button, Tooltip } from '@wordpress/components';
import { Icon, plus, close } from '@wordpress/icons';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Documents panel — list of open query documents.
 * Replaces the tab bar with a sidebar list, like Gutenberg's List View.
 */
export const DocumentsIcon = () => (
	<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">
		<path
			d="M4 4h16v1.5H4V4zm0 4.5h16V10H4V8.5zm0 4.5h16v1.5H4V13zm0 4.5h16V19H4v-1.5z"
			fill="currentColor"
		/>
	</svg>
);

export function DocumentsPanel() {
	const { documents, openTabs, activeTab } = useSelect((select) => {
		const editor = select('wpgraphql-ide/document-editor');
		return {
			documents: editor.getDocuments(),
			openTabs: editor.getOpenTabs(),
			activeTab: editor.getActiveTab(),
		};
	}, []);

	const { switchTab, createTab, closeTab } = useDispatch(
		'wpgraphql-ide/document-editor'
	);

	const openDocuments = openTabs
		.map((tabId) => documents.find((d) => String(d.id) === String(tabId)))
		.filter(Boolean);

	return (
		<div className="wpgraphql-ide-documents-panel">
			<div className="wpgraphql-ide-documents-header">
				<span className="wpgraphql-ide-documents-title">Documents</span>
				<Tooltip text="New document">
					<Button
						size="compact"
						onClick={() => createTab()}
						aria-label="New document"
					>
						<Icon icon={plus} />
					</Button>
				</Tooltip>
			</div>
			<ul className="wpgraphql-ide-documents-list">
				{openDocuments.map((doc) => {
					const isActive = String(doc.id) === String(activeTab);
					return (
						<li
							key={doc.id}
							className={`wpgraphql-ide-document-item ${isActive ? 'is-active' : ''}`}
						>
							<button
								type="button"
								className="wpgraphql-ide-document-label"
								onClick={() => switchTab(String(doc.id))}
							>
								{doc.title || 'Untitled'}
							</button>
							<button
								type="button"
								className="wpgraphql-ide-document-close"
								onClick={(e) => {
									e.stopPropagation();
									closeTab(String(doc.id));
								}}
								aria-label="Close document"
							>
								<Icon icon={close} size={12} />
							</button>
						</li>
					);
				})}
			</ul>
		</div>
	);
}
