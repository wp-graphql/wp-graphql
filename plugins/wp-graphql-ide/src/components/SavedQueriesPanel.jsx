import React, { useState, useMemo, useEffect } from 'react';
import {
	Button,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	SearchControl,
	TabPanel,
	Tooltip,
} from '@wordpress/components';
import { Icon, file, trash, plus } from '@wordpress/icons';
import { useSelect, useDispatch } from '@wordpress/data';
import { isTempId } from '../stores/document-editor/document-editor-store-actions';
import { updateDocument } from '../api/documents';

/**
 * Saved Queries panel icon for the activity bar.
 */
export const SavedQueriesIcon = () => <Icon icon={file} />;

const STATUS_TABS = [
	{ name: 'all', title: 'All' },
	{ name: 'draft', title: 'Drafts' },
	{ name: 'publish', title: 'Published' },
];

/**
 * Saved Queries panel — browse all saved documents with search,
 * status filtering, and collection grouping.
 */
export function SavedQueriesPanel() {
	const [search, setSearch] = useState('');
	const [creatingCollection, setCreatingCollection] = useState(false);
	const [newCollectionName, setNewCollectionName] = useState('');

	const { documents, openTabs, activeTab } = useSelect((select) => {
		const editor = select('wpgraphql-ide/document-editor');
		return {
			documents: editor.getDocuments(),
			openTabs: editor.getOpenTabs(),
			activeTab: editor.getActiveTab(),
		};
	}, []);

	const { collections, activeCollection } = useSelect((select) => {
		const app = select('wpgraphql-ide/app');
		return {
			collections: app.getCollections(),
			activeCollection: app.getActiveCollection(),
		};
	}, []);

	const { switchTab, removeDocument } = useDispatch(
		'wpgraphql-ide/document-editor'
	);
	const { loadCollections, addCollection, setActiveCollection } =
		useDispatch('wpgraphql-ide/app');

	// Load collections on mount.
	useEffect(() => {
		loadCollections();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

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
		// Filter by active collection.
		if (activeCollection) {
			filtered = filtered.filter(
				(d) => d.collections && d.collections.includes(activeCollection)
			);
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

	const handleCreateCollection = async () => {
		const name = newCollectionName.trim();
		if (!name) {
			return;
		}
		await addCollection(name);
		setNewCollectionName('');
		setCreatingCollection(false);
	};

	const handleAssignCollection = async (docId, collectionId) => {
		const doc = documents.find((d) => String(d.id) === String(docId));
		if (!doc) {
			return;
		}
		const current = doc.collections || [];
		const next = current.includes(collectionId)
			? current.filter((c) => c !== collectionId)
			: [...current, collectionId];
		await updateDocument(docId, { collections: next });
		// Reload to get fresh data.
		const { dispatch: dis } =
			// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
			require('@wordpress/data');
		dis('wpgraphql-ide/document-editor').loadDocuments();
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
				{!isUnsaved && collections.length > 0 && (
					<DropdownMenu
						icon={null}
						label="Assign to collection"
						toggleProps={{
							children: '…',
							className: 'wpgraphql-ide-document-collection-btn',
							size: 'small',
						}}
					>
						{({ onClose: closeMenu }) => (
							<MenuGroup label="Collections">
								{collections.map((c) => {
									const assigned = (
										doc.collections || []
									).includes(c.id);
									return (
										<MenuItem
											key={c.id}
											icon={assigned ? '✓' : undefined}
											onClick={() => {
												handleAssignCollection(
													doc.id,
													c.id
												);
												closeMenu();
											}}
										>
											{c.name}
										</MenuItem>
									);
								})}
							</MenuGroup>
						)}
					</DropdownMenu>
				)}
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
			{/* Search — fixed above tabs */}
			<div className="wpgraphql-ide-saved-queries-search">
				<SearchControl
					value={search}
					onChange={setSearch}
					placeholder="Search..."
					__nextHasNoMarginBottom
				/>
			</div>

			{/* Collections chip bar */}
			{collections.length > 0 && (
				<div className="wpgraphql-ide-collections-bar">
					<button
						type="button"
						className={`wpgraphql-ide-collection-chip${!activeCollection ? ' is-active' : ''}`}
						onClick={() => setActiveCollection(null)}
					>
						All
					</button>
					{collections.map((c) => (
						<button
							key={c.id}
							type="button"
							className={`wpgraphql-ide-collection-chip${activeCollection === c.id ? ' is-active' : ''}`}
							onClick={() =>
								setActiveCollection(
									activeCollection === c.id ? null : c.id
								)
							}
						>
							{c.name}
						</button>
					))}
				</div>
			)}

			{/* Inline collection creation */}
			{creatingCollection ? (
				<div className="wpgraphql-ide-collection-create">
					<input
						className="wpgraphql-ide-collection-input"
						value={newCollectionName}
						onChange={(e) => setNewCollectionName(e.target.value)}
						onKeyDown={(e) => {
							if (e.key === 'Enter') {
								handleCreateCollection();
							}
							if (e.key === 'Escape') {
								setCreatingCollection(false);
								setNewCollectionName('');
							}
						}}
						onBlur={() => {
							if (newCollectionName.trim()) {
								handleCreateCollection();
							} else {
								setCreatingCollection(false);
							}
						}}
						placeholder="Collection name..."
						// eslint-disable-next-line jsx-a11y/no-autofocus
						autoFocus
					/>
				</div>
			) : (
				<div className="wpgraphql-ide-collection-add">
					<Button
						size="small"
						onClick={() => setCreatingCollection(true)}
						className="wpgraphql-ide-collection-add-btn"
					>
						<Icon icon={plus} size={16} />
						New collection
					</Button>
				</div>
			)}

			{/* Tabs + content */}
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

			{/* Unsaved docs */}
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
