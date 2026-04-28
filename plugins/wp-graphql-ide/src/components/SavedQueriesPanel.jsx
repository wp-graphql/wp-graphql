import React, {
	useState,
	useMemo,
	useEffect,
	useCallback,
	useRef,
} from 'react';
import {
	Button,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	SearchControl,
} from '@wordpress/components';
import {
	Icon,
	file,
	moreVertical,
	chevronDown,
	chevronRight,
	plus,
	lock,
} from '@wordpress/icons';
import { useSelect, useDispatch } from '@wordpress/data';
import { isTempId } from '../stores/document-editor/document-editor-store-actions';
import { updateDocument } from '../api/documents';

/**
 * Saved Queries panel icon for the activity bar.
 */
export const SavedQueriesIcon = () => <Icon icon={file} />;

/**
 * Collapsible collection section with kebab menu and drop target.
 *
 * @param {Object}          root0              Props.
 * @param {string}          root0.title        Section title.
 * @param {number}          root0.count        Document count.
 * @param {boolean}         root0.collapsed    Whether collapsed.
 * @param {Function}        root0.onToggle     Toggle callback.
 * @param {Function}        root0.onDelete     Delete callback (optional).
 * @param {Function}        root0.onRename     Rename callback (optional).
 * @param {Function}        root0.onMoveUp     Move up callback (optional).
 * @param {Function}        root0.onMoveDown   Move down callback (optional).
 * @param {Function}        root0.onDrop       Drop handler callback.
 * @param {string}          root0.dropTargetId Drop zone ID.
 * @param {string}          root0.dragOverId   Currently hovered drop zone.
 * @param {Function}        root0.setDragOver  Set drag-over state.
 * @param {React.ReactNode} root0.children     Nested content.
 */
function CollectionSection({
	title,
	count,
	collapsed,
	onToggle,
	onDelete,
	onRename,
	onMoveUp,
	onMoveDown,
	onDrop,
	dropTargetId,
	dragOverId,
	setDragOver,
	children,
}) {
	const isOver = dragOverId === dropTargetId;
	const [editing, setEditing] = useState(false);
	const [editValue, setEditValue] = useState(title);
	const hasMenu = onDelete || onRename || onMoveUp || onMoveDown;

	const commitRename = () => {
		const trimmed = editValue.trim();
		if (trimmed && trimmed !== title && onRename) {
			onRename(trimmed);
		}
		setEditing(false);
	};

	return (
		<div className="wpgraphql-ide-collection-section">
			<div
				className={`wpgraphql-ide-collection-header${isOver ? ' is-drag-over' : ''}`}
				onDragOver={(e) => {
					e.preventDefault();
					e.dataTransfer.dropEffect = 'move';
					setDragOver(dropTargetId);
				}}
				onDragLeave={() => setDragOver(null)}
				onDrop={(e) => {
					e.preventDefault();
					const docId = e.dataTransfer.getData('text/plain');
					if (docId && onDrop) {
						onDrop(docId);
					}
					setDragOver(null);
				}}
			>
				<button
					type="button"
					className="wpgraphql-ide-collection-toggle"
					onClick={onToggle}
				>
					<Icon
						icon={collapsed ? chevronRight : chevronDown}
						size={18}
					/>
				</button>
				{editing ? (
					<input
						className="wpgraphql-ide-collection-rename-input"
						value={editValue}
						onChange={(e) => setEditValue(e.target.value)}
						onBlur={commitRename}
						onKeyDown={(e) => {
							if (e.key === 'Enter') {
								commitRename();
							}
							if (e.key === 'Escape') {
								setEditing(false);
								setEditValue(title);
							}
						}}
						onClick={(e) => e.stopPropagation()}
						// eslint-disable-next-line jsx-a11y/no-autofocus
						autoFocus
					/>
				) : (
					<button
						type="button"
						className="wpgraphql-ide-collection-title"
						onClick={onToggle}
					>
						{title}
					</button>
				)}
				{count > 0 && (
					<span className="wpgraphql-ide-collection-count">
						{count}
					</span>
				)}
				{hasMenu && (
					<DropdownMenu
						icon={moreVertical}
						label="Collection actions"
						toggleProps={{
							size: 'small',
							className: 'wpgraphql-ide-collection-kebab',
						}}
					>
						{({ onClose: closeMenu }) => (
							<>
								{onRename && (
									<MenuGroup>
										<MenuItem
											onClick={() => {
												setEditValue(title);
												setEditing(true);
												closeMenu();
											}}
										>
											Rename
										</MenuItem>
									</MenuGroup>
								)}
								{(onMoveUp || onMoveDown) && (
									<MenuGroup>
										{onMoveUp && (
											<MenuItem
												onClick={() => {
													onMoveUp();
													closeMenu();
												}}
											>
												Move up
											</MenuItem>
										)}
										{onMoveDown && (
											<MenuItem
												onClick={() => {
													onMoveDown();
													closeMenu();
												}}
											>
												Move down
											</MenuItem>
										)}
									</MenuGroup>
								)}
								{onDelete && (
									<MenuGroup>
										<MenuItem
											isDestructive
											onClick={() => {
												onDelete();
												closeMenu();
											}}
										>
											Delete collection
										</MenuItem>
									</MenuGroup>
								)}
							</>
						)}
					</DropdownMenu>
				)}
			</div>
			{!collapsed && (
				<div className="wpgraphql-ide-collection-body">{children}</div>
			)}
		</div>
	);
}

/**
 * Saved Queries panel — documents grouped by collection.
 */
export function SavedQueriesPanel() {
	const [search, setSearch] = useState('');
	const [collapsedSections, setCollapsedSections] = useState({});
	const [creatingCollection, setCreatingCollection] = useState(false);
	const [newCollectionName, setNewCollectionName] = useState('');
	const [dragOverId, setDragOverId] = useState(null);
	const dragDocRef = useRef(null);

	const { documents, openTabs, activeTab } = useSelect((select) => {
		const editor = select('wpgraphql-ide/document-editor');
		return {
			documents: editor.getDocuments(),
			openTabs: editor.getOpenTabs(),
			activeTab: editor.getActiveTab(),
		};
	}, []);

	const { collections } = useSelect((select) => {
		const app = select('wpgraphql-ide/app');
		return { collections: app.getCollections() };
	}, []);

	const { switchTab, removeDocument } = useDispatch(
		'wpgraphql-ide/document-editor'
	);
	const {
		loadCollections,
		addCollection,
		removeCollection,
		renameCollection,
		moveCollection,
	} = useDispatch('wpgraphql-ide/app');

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

	const searchFilter = useCallback(
		(docs) => {
			if (!search.trim()) {
				return docs;
			}
			const q = search.toLowerCase();
			return docs.filter(
				(d) =>
					(d.title || '').toLowerCase().includes(q) ||
					(d.query || '').toLowerCase().includes(q)
			);
		},
		[search]
	);

	const { grouped, uncategorized } = useMemo(() => {
		const filtered = searchFilter(savedDocs);
		const groups = {};
		const ungrouped = [];
		for (const doc of filtered) {
			const docCollections = doc.collections || [];
			if (docCollections.length === 0) {
				ungrouped.push(doc);
			} else {
				for (const cId of docCollections) {
					if (!groups[cId]) {
						groups[cId] = [];
					}
					groups[cId].push(doc);
				}
			}
		}
		return { grouped: groups, uncategorized: ungrouped };
	}, [savedDocs, searchFilter]);

	const toggleSection = (key) => {
		setCollapsedSections((prev) => ({
			...prev,
			[key]: !prev[key],
		}));
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

	const handleDeleteCollection = async (id, name) => {
		if (
			// eslint-disable-next-line no-alert
			!window.confirm(
				`Delete collection "${name}"? Documents will not be deleted.`
			)
		) {
			return;
		}
		await removeCollection(id);
	};

	const reloadDocs = () => {
		const { dispatch: dis } =
			// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
			require('@wordpress/data');
		dis('wpgraphql-ide/document-editor').loadDocuments();
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
		reloadDocs();
	};

	const handleDropToCollection = async (docId, collectionId) => {
		await updateDocument(docId, {
			collections: collectionId ? [collectionId] : [],
		});
		reloadDocs();
	};

	const renderDoc = (doc) => {
		const isActive = String(doc.id) === String(activeTab);
		const isOpen = openTabs.includes(String(doc.id));
		const isUnsaved = isTempId(doc.id);
		const isPublished = !isUnsaved && doc.status === 'publish';

		return (
			<li
				key={doc.id}
				draggable={!isUnsaved}
				onDragStart={(e) => {
					dragDocRef.current = String(doc.id);
					e.dataTransfer.setData('text/plain', String(doc.id));
					e.dataTransfer.effectAllowed = 'move';
				}}
				onDragEnd={() => {
					dragDocRef.current = null;
					setDragOverId(null);
				}}
				className={`wpgraphql-ide-document-item${isActive ? ' is-active' : ''}${isPublished ? ' is-published' : ''}${!isUnsaved ? ' is-draggable' : ''}`}
			>
				<button
					type="button"
					className="wpgraphql-ide-document-label"
					onClick={() => switchTab(String(doc.id))}
				>
					{isOpen && (
						<span className="wpgraphql-ide-document-open-dot" />
					)}
					{isPublished && (
						<Icon
							icon={lock}
							size={14}
							className="wpgraphql-ide-document-status-icon"
						/>
					)}
					<span className="wpgraphql-ide-document-title-text">
						{doc.title || 'Untitled'}
					</span>
				</button>
				{!isUnsaved && (
					<DropdownMenu
						icon={moreVertical}
						label="Document actions"
						toggleProps={{
							size: 'small',
							className: 'wpgraphql-ide-document-kebab',
						}}
					>
						{({ onClose: closeMenu }) => (
							<>
								{collections.length > 0 && (
									<MenuGroup label="Move to">
										{collections.map((c) => {
											const assigned = (
												doc.collections || []
											).includes(c.id);
											return (
												<MenuItem
													key={c.id}
													icon={
														assigned
															? '✓'
															: undefined
													}
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
								<MenuGroup>
									<MenuItem
										isDestructive
										onClick={() => {
											closeMenu();
											if (
												// eslint-disable-next-line no-alert
												window.confirm(
													`Delete "${doc.title || 'Untitled'}"?`
												)
											) {
												removeDocument(doc.id);
											}
										}}
									>
										Delete
									</MenuItem>
								</MenuGroup>
							</>
						)}
					</DropdownMenu>
				)}
			</li>
		);
	};

	const renderDocList = (docs) => {
		if (docs.length === 0) {
			return (
				<p className="wpgraphql-ide-collection-empty">
					No documents in this collection
				</p>
			);
		}
		return (
			<ul className="wpgraphql-ide-documents-list">
				{docs.map(renderDoc)}
			</ul>
		);
	};

	return (
		<div className="wpgraphql-ide-saved-queries-panel">
			<div className="wpgraphql-ide-saved-queries-search">
				<SearchControl
					value={search}
					onChange={setSearch}
					placeholder="Search..."
					__nextHasNoMarginBottom
					size="compact"
				/>
			</div>

			<div className="wpgraphql-ide-collections-list">
				{collections.map((c) => {
					const docs = grouped[c.id] || [];
					return (
						<CollectionSection
							key={c.id}
							title={c.name}
							count={docs.length}
							collapsed={
								c.id in collapsedSections
									? collapsedSections[c.id]
									: docs.length === 0
							}
							onToggle={() => toggleSection(c.id)}
							onDelete={() =>
								handleDeleteCollection(c.id, c.name)
							}
							onRename={(newName) =>
								renameCollection(c.id, newName)
							}
							onMoveUp={
								collections.indexOf(c) > 0
									? () => moveCollection(c.id, 'up')
									: undefined
							}
							onMoveDown={
								collections.indexOf(c) < collections.length - 1
									? () => moveCollection(c.id, 'down')
									: undefined
							}
							onDrop={(docId) =>
								handleDropToCollection(docId, c.id)
							}
							dropTargetId={`collection-${c.id}`}
							dragOverId={dragOverId}
							setDragOver={setDragOverId}
						>
							{renderDocList(docs)}
						</CollectionSection>
					);
				})}

				{/* Uncategorized docs — flat at root, no section wrapper */}
				{uncategorized.length > 0 && (
					<ul className="wpgraphql-ide-documents-list">
						{uncategorized.map(renderDoc)}
					</ul>
				)}

				{savedDocs.length === 0 && unsavedDocs.length === 0 && (
					<div className="wpgraphql-ide-saved-queries-empty">
						<p>No saved documents.</p>
					</div>
				)}
			</div>

			<div className="wpgraphql-ide-collection-footer">
				{creatingCollection ? (
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
				) : (
					<Button
						size="small"
						onClick={() => setCreatingCollection(true)}
						className="wpgraphql-ide-collection-add-btn"
						icon={plus}
					>
						New collection
					</Button>
				)}
			</div>

			{unsavedDocs.length > 0 && (
				<CollectionSection
					title="Unsaved"
					count={unsavedDocs.length}
					collapsed={!!collapsedSections._unsaved}
					onToggle={() => toggleSection('_unsaved')}
					dropTargetId="collection-unsaved"
					dragOverId={dragOverId}
					setDragOver={setDragOverId}
				>
					<ul className="wpgraphql-ide-documents-list">
						{unsavedDocs.map(renderDoc)}
					</ul>
				</CollectionSection>
			)}
		</div>
	);
}
