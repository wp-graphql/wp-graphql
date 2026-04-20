import React from 'react';
import { Button, Tooltip } from '@wordpress/components';
import { Icon, plus, close } from '@wordpress/icons';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Tab bar for switching between query documents.
 *
 * Renders a horizontal strip of tabs above the editor area. Each tab
 * represents an open document. Supports creating new tabs and closing
 * existing ones.
 */
export function TabBar() {
	const { openTabs, activeTab, documents } = useSelect((select) => {
		const editor = select('wpgraphql-ide/document-editor');
		return {
			openTabs: editor.getOpenTabs(),
			activeTab: editor.getActiveTab(),
			documents: editor.getDocuments(),
		};
	}, []);

	const { switchTab, createTab, closeTab } = useDispatch(
		'wpgraphql-ide/document-editor'
	);

	const getDocumentTitle = (tabId) => {
		const doc = documents.find((d) => String(d.id) === String(tabId));
		return doc?.title || 'Untitled';
	};

	return (
		<div className="wpgraphql-ide-tab-bar">
			<div className="wpgraphql-ide-tabs">
				{openTabs.map((tabId) => {
					const isActive = String(tabId) === String(activeTab);
					return (
						<div
							key={tabId}
							className={`wpgraphql-ide-tab ${isActive ? 'is-active' : ''}`}
						>
							<button
								type="button"
								className="wpgraphql-ide-tab-label"
								onClick={() => switchTab(tabId)}
								aria-selected={isActive}
								role="tab"
							>
								{getDocumentTitle(tabId)}
							</button>
							<Tooltip text="Close tab">
								<button
									type="button"
									className="wpgraphql-ide-tab-close"
									onClick={(e) => {
										e.stopPropagation();
										closeTab(tabId);
									}}
									aria-label="Close tab"
								>
									<Icon icon={close} size={12} />
								</button>
							</Tooltip>
						</div>
					);
				})}
			</div>
			<Tooltip text="New tab">
				<Button
					className="wpgraphql-ide-tab-add"
					onClick={() => createTab()}
					aria-label="New tab"
				>
					<Icon icon={plus} size={16} />
				</Button>
			</Tooltip>
		</div>
	);
}
