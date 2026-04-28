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
	upload,
	download,
} from '@wordpress/icons';
import { useSelect, useDispatch } from '@wordpress/data';
import { doAction } from '@wordpress/hooks';
import { useDialog } from './dialogs/DialogProvider';
import { isTempId } from '../stores/document-editor/document-editor-store-actions';
import {
	updateDocument,
	deleteCollectionWithContents,
	exportDocuments,
	importDocuments,
} from '../api/documents';

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
	onDeleteWithContents,
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
	const hasMenu =
		onDelete || onDeleteWithContents || onRename || onMoveUp || onMoveDown;

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
					aria-expanded={!collapsed}
					aria-label={`${title}: ${collapsed ? 'expand' : 'collapse'}`}
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
						aria-label="Rename collection"
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
						({count})
					</span>
				)}
				{hasMenu ? (
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
								{(onDelete || onDeleteWithContents) && (
									<MenuGroup>
										{onDelete && (
											<MenuItem
												isDestructive
												onClick={() => {
													onDelete();
													closeMenu();
												}}
											>
												Delete collection
											</MenuItem>
										)}
										{onDeleteWithContents && (
											<MenuItem
												isDestructive
												onClick={() => {
													onDeleteWithContents();
													closeMenu();
												}}
											>
												Delete collection and contents
											</MenuItem>
										)}
									</MenuGroup>
								)}
							</>
						)}
					</DropdownMenu>
				) : (
					<span className="wpgraphql-ide-collection-kebab-spacer" />
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
	const { confirm, prompt } = useDialog();
	const notify = (content, type = 'default') =>
		doAction('wpgraphql-ide.notice', content, type);

	const [search, setSearch] = useState('');
	const [statusFilter, setStatusFilter] = useState('all');
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

	const filterDocs = useCallback(
		(docs) => {
			let filtered = docs;
			if (statusFilter === 'publish') {
				filtered = filtered.filter((d) => d.status === 'publish');
			} else if (statusFilter === 'draft') {
				filtered = filtered.filter((d) => d.status !== 'publish');
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
		},
		[search, statusFilter]
	);

	const { grouped, uncategorized } = useMemo(() => {
		const filtered = filterDocs(savedDocs);
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
	}, [savedDocs, filterDocs]);

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
		const ok = await confirm({
			title: 'Delete collection',
			message: `Delete "${name}"? Documents will not be deleted.`,
			confirmLabel: 'Delete collection',
			isDestructive: true,
		});
		if (!ok) {
			return;
		}
		await removeCollection(id);
	};

	const handleDeleteCollectionWithContents = async (id, name) => {
		const ok = await confirm({
			title: 'Delete collection and contents',
			message: `Delete "${name}" AND every document in it? This cannot be undone.`,
			confirmLabel: 'Delete everything',
			isDestructive: true,
		});
		if (!ok) {
			return;
		}
		await deleteCollectionWithContents(id);
		await loadCollections();
		reloadDocs();
	};

	const handleExport = async () => {
		try {
			const data = await exportDocuments();
			const blob = new Blob([JSON.stringify(data, null, 2)], {
				type: 'application/json',
			});
			const url = URL.createObjectURL(blob);
			const a = document.createElement('a');
			a.href = url;
			a.download = `wpgraphql-ide-documents-${new Date().toISOString().slice(0, 10)}.json`;
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
			URL.revokeObjectURL(url);
		} catch (err) {
			// eslint-disable-next-line no-console
			console.error('Export failed:', err);
			notify('Export failed. Check the console for details.', 'error');
		}
	};

	const importInputRef = useRef(null);

	const handleImport = () => {
		importInputRef.current?.click();
	};

	const handleImportFile = async (e) => {
		// eslint-disable-next-line no-shadow
		const importedFile = e.target.files?.[0];
		e.target.value = '';
		if (!importedFile) {
			return;
		}
		try {
			const text = await importedFile.text();
			const payload = JSON.parse(text);
			const result = await importDocuments(payload);
			if (result?.error) {
				notify(`Import failed: ${result.error}`, 'error');
				return;
			}
			await loadCollections();
			reloadDocs();
			notify(
				`Imported ${result.created || 0} documents${
					result.skipped
						? ` (${result.skipped} skipped as duplicates)`
						: ''
				}.`
			);
		} catch (err) {
			// eslint-disable-next-line no-console
			console.error('Import failed:', err);
			notify('Import failed. Make sure the file is valid JSON.', 'error');
		}
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
		const isDraft = !isUnsaved && !isPublished;

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
					<span
						className={`wpgraphql-ide-document-title-text${isUnsaved ? ' is-unsaved' : ''}`}
					>
						{doc.title || 'Untitled'}
						{isDraft && (
							<span className="wpgraphql-ide-document-badge">
								{' '}
								— Draft
							</span>
						)}
					</span>
				</button>
				{!isUnsaved && (
					<DropdownMenu
						icon={moreVertical}
						label="Document actions"
						popoverProps={{ placement: 'bottom-start' }}
						toggleProps={{
							size: 'small',
							className: 'wpgraphql-ide-document-kebab',
						}}
					>
						{({ onClose: closeMenu }) => (
							<>
								<MenuGroup>
									<MenuItem
										onClick={async () => {
											closeMenu();
											const newName = await prompt({
												title: 'Rename document',
												inputLabel: 'Title',
												defaultValue:
													doc.title || 'Untitled',
												confirmLabel: 'Rename',
											});
											if (newName) {
												await updateDocument(doc.id, {
													title: newName,
												});
												reloadDocs();
											}
										}}
									>
										Rename
									</MenuItem>
								</MenuGroup>
								{(() => {
									const docCols = doc.collections || [];
									const available = collections.filter(
										(c) => !docCols.includes(c.id)
									);
									const inCollection = docCols.length > 0;
									return (
										<>
											{available.length > 0 && (
												<MenuGroup label="Move to">
													{available.map((c) => (
														<MenuItem
															key={c.id}
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
													))}
												</MenuGroup>
											)}
											{inCollection && (
												<MenuGroup>
													{docCols.map((cId) => {
														const col =
															collections.find(
																(cx) =>
																	cx.id ===
																	cId
															);
														if (!col) {
															return null;
														}
														return (
															<MenuItem
																key={`remove-${cId}`}
																onClick={() => {
																	handleAssignCollection(
																		doc.id,
																		cId
																	);
																	closeMenu();
																}}
															>
																Remove from{' '}
																{col.name}
															</MenuItem>
														);
													})}
												</MenuGroup>
											)}
										</>
									);
								})()}
								<MenuGroup>
									<MenuItem
										isDestructive
										onClick={async () => {
											closeMenu();
											const ok = await confirm({
												title: 'Delete document',
												message: `Delete "${
													doc.title || 'Untitled'
												}"? This cannot be undone.`,
												confirmLabel: 'Delete',
												isDestructive: true,
											});
											if (ok) {
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

			<div className="wpgraphql-ide-status-filters">
				{[
					{ key: 'all', label: 'All' },
					{ key: 'draft', label: 'Drafts' },
					{ key: 'publish', label: 'Published' },
				].map((s) => (
					<button
						key={s.key}
						type="button"
						className={`wpgraphql-ide-status-chip${statusFilter === s.key ? ' is-active' : ''}`}
						onClick={() => setStatusFilter(s.key)}
					>
						{s.label}
					</button>
				))}
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
							onDeleteWithContents={() =>
								handleDeleteCollectionWithContents(c.id, c.name)
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

				{/* "Documents" — permanent section for uncollected docs */}
				<CollectionSection
					title="Documents"
					count={uncategorized.length}
					collapsed={!!collapsedSections._documents}
					onToggle={() => toggleSection('_documents')}
					onDrop={(docId) => handleDropToCollection(docId, null)}
					dropTargetId="collection-documents"
					dragOverId={dragOverId}
					setDragOver={setDragOverId}
				>
					{uncategorized.length > 0 ? (
						<ul className="wpgraphql-ide-documents-list">
							{uncategorized.map(renderDoc)}
						</ul>
					) : (
						<p className="wpgraphql-ide-collection-empty">
							No documents
						</p>
					)}
				</CollectionSection>
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
					<div className="wpgraphql-ide-collection-footer-actions">
						<Button
							size="small"
							onClick={() => setCreatingCollection(true)}
							className="wpgraphql-ide-collection-add-btn"
							icon={plus}
						>
							New collection
						</Button>
						<Button
							size="small"
							onClick={handleImport}
							icon={upload}
							label="Import documents"
							showTooltip
						/>
						<Button
							size="small"
							onClick={handleExport}
							icon={download}
							label="Export documents"
							showTooltip
						/>
						<input
							ref={importInputRef}
							type="file"
							accept="application/json,.json"
							onChange={handleImportFile}
							style={{ display: 'none' }}
						/>
					</div>
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
