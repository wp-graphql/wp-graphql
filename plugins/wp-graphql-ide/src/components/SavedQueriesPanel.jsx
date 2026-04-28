import React, { useState, useMemo } from 'react';
import {
	DropdownMenu,
	MenuGroup,
	MenuItem,
	SearchControl,
	TabPanel,
	Tooltip,
} from '@wordpress/components';
import { Icon, file, moreVertical, trash } from '@wordpress/icons';
import { useSelect, useDispatch } from '@wordpress/data';
import { isTempId } from '../stores/document-editor/document-editor-store-actions';

/**
 * Saved Queries panel icon for the activity bar.
 */
export const SavedQueriesIcon = () => <Icon icon={file} />;

/**
 * Header action for the Saved Queries panel — "+" button in panel header.
 */
export const SavedQueriesHeaderAction = () => {
	const { createTab } = useDispatch('wpgraphql-ide/document-editor');
	return (
		<DropdownMenu
			icon={moreVertical}
			label="Saved queries actions"
			className="wpgraphql-ide-panel-header-btn"
		>
			{({ onClose: closeMenu }) => (
				<MenuGroup>
					<MenuItem
						onClick={() => {
							createTab();
							closeMenu();
						}}
					>
						New document
					</MenuItem>
				</MenuGroup>
			)}
		</DropdownMenu>
	);
};

const STATUS_TABS = [
	{ name: 'all', title: 'All' },
	{ name: 'draft', title: 'Drafts' },
	{ name: 'publish', title: 'Published' },
];

/**
 * Saved Queries panel — browse all saved documents with search and
 * status filtering. Layout matches the Gutenberg block inserter:
 * search above tabs, both fixed; list scrolls below.
 */
export function SavedQueriesPanel() {
	const [search, setSearch] = useState('');

	const { documents, openTabs, activeTab } = useSelect((select) => {
		const editor = select('wpgraphql-ide/document-editor');
		return {
			documents: editor.getDocuments(),
			openTabs: editor.getOpenTabs(),
			activeTab: editor.getActiveTab(),
		};
	}, []);

	const { switchTab, removeDocument } = useDispatch(
		'wpgraphql-ide/document-editor'
	);

	const savedDocs = useMemo(
		() => documents.filter((d) => !isTempId(d.id)),
		[documents]
	);

	const unsavedDocs = useMemo(
		() => documents.filter((d) => isTempId(d.id)),
		[documents]
	);

	const filterDocs = (docs, status) => {
		let filtered = docs;
		if (status === 'draft') {
			filtered = filtered.filter((d) => d.status === 'draft');
		} else if (status === 'publish') {
			filtered = filtered.filter((d) => d.status === 'publish');
		}
		if (search.trim()) {
			const q = search.toLowerCase();
			filtered = filtered.filter(
				(d) =>
					(d.title || '').toLowerCase().includes(q) ||
					(d.query || '').toLowerCase().includes(q)
			);
		}
		return filtered;
	};

	const renderDoc = (doc) => {
		const isActive = String(doc.id) === String(activeTab);
		const isOpen = openTabs.includes(String(doc.id));
		const isUnsaved = isTempId(doc.id);

		return (
			<li
				key={doc.id}
				className={`wpgraphql-ide-document-item${isActive ? ' is-active' : ''}`}
			>
				<button
					type="button"
					className="wpgraphql-ide-document-label"
					onClick={() => switchTab(String(doc.id))}
				>
					{isOpen && !isUnsaved && (
						<span className="wpgraphql-ide-document-open-dot" />
					)}
					{isUnsaved && doc.dirty && (
						<span className="wpgraphql-ide-document-dirty-dot" />
					)}
					<span className="wpgraphql-ide-document-title-text">
						{doc.title || 'Untitled'}
					</span>
					{!isUnsaved && doc.status === 'publish' && (
						<span className="wpgraphql-ide-document-status">
							Published
						</span>
					)}
				</button>
				<Tooltip text="Delete document">
					<button
						type="button"
						className="wpgraphql-ide-document-delete"
						onClick={(e) => {
							e.stopPropagation();
							if (
								// eslint-disable-next-line no-alert
								window.confirm(
									`Delete "${doc.title || 'Untitled'}"?`
								)
							) {
								removeDocument(doc.id);
							}
						}}
						aria-label="Delete document"
					>
						<Icon icon={trash} size={16} />
					</button>
				</Tooltip>
			</li>
		);
	};

	return (
		<div className="wpgraphql-ide-saved-queries-panel">
			{/* Search — fixed above tabs, part of panel infrastructure */}
			<div className="wpgraphql-ide-saved-queries-search">
				<SearchControl
					value={search}
					onChange={setSearch}
					placeholder="Search..."
					__nextHasNoMarginBottom
				/>
			</div>

			{/* Tabs + content — scrollable below search */}
			<TabPanel
				className="wpgraphql-ide-saved-queries-tabs"
				tabs={STATUS_TABS}
			>
				{(tab) => {
					const filtered = filterDocs(savedDocs, tab.name);
					return (
						<>
							{filtered.length > 0 && (
								<ul className="wpgraphql-ide-documents-list">
									{filtered.map(renderDoc)}
								</ul>
							)}
							{filtered.length === 0 &&
								savedDocs.length === 0 && (
									<div className="wpgraphql-ide-saved-queries-empty">
										<p>No saved documents.</p>
									</div>
								)}
							{filtered.length === 0 && savedDocs.length > 0 && (
								<div className="wpgraphql-ide-saved-queries-empty">
									<p>No matching documents.</p>
								</div>
							)}
						</>
					);
				}}
			</TabPanel>

			{/* Unsaved docs — below everything */}
			{unsavedDocs.length > 0 && (
				<div className="wpgraphql-ide-saved-queries-unsaved">
					<div className="wpgraphql-ide-saved-queries-divider">
						Unsaved
					</div>
					<ul className="wpgraphql-ide-documents-list">
						{unsavedDocs.map(renderDoc)}
					</ul>
				</div>
			)}
		</div>
	);
}
