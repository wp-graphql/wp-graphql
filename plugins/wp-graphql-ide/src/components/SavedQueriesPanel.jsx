import React, {
	useState,
	useMemo,
	useEffect,
	useCallback,
	useRef,
} from 'react';
import {
	DropdownMenu,
	MenuGroup,
	MenuItem,
	SearchControl,
	TabPanel,
} from '@wordpress/components';
import {
	Icon,
	file,
	moreVertical,
	chevronDown,
	chevronRight,
	check,
} from '@wordpress/icons';
import { useSelect, useDispatch } from '@wordpress/data';
import { doAction, addAction, removeAction } from '@wordpress/hooks';
import { useDialog } from './dialogs/DialogProvider';
import { DeleteCollectionDialog } from './dialogs/DeleteCollectionDialog';
import { ExportDialog } from './dialogs/ExportDialog';
import { SaveDialog } from './dialogs/SaveDialog';
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

const PANEL_ACTION_HOOK = 'wpgraphql-ide.saved-queries.action';

/**
 * Kebab rendered in the panel header (right of the title). Bridges
 * its menu items to the panel body via the `wpgraphql-ide.saved-queries.action`
 * hook so the panel owns the modal/state and the header stays stateless.
 */
export function SavedQueriesPanelHeaderAction() {
	const collectionCount = useSelect(
		(select) => select('wpgraphql-ide/app').getCollections().length,
		[]
	);

	return (
		<DropdownMenu
			icon={moreVertical}
			label="Saved queries actions"
			toggleProps={{
				size: 'small',
				className: 'wpgraphql-ide-panel-kebab',
			}}
			popoverProps={{ placement: 'bottom-end' }}
		>
			{({ onClose: closeMenu }) => (
				<>
					<MenuGroup>
						<MenuItem
							onClick={() => {
								closeMenu();
								doAction(PANEL_ACTION_HOOK, 'new-collection');
							}}
						>
							New collection
						</MenuItem>
					</MenuGroup>
					<MenuGroup>
						<MenuItem
							onClick={() => {
								closeMenu();
								doAction(PANEL_ACTION_HOOK, 'import');
							}}
						>
							Import documents…
						</MenuItem>
						<MenuItem
							disabled={collectionCount === 0}
							onClick={() => {
								closeMenu();
								doAction(PANEL_ACTION_HOOK, 'export');
							}}
						>
							Export documents…
						</MenuItem>
					</MenuGroup>
				</>
			)}
		</DropdownMenu>
	);
}

const SORT_OPTIONS = [
	{ value: 'manual', label: 'Manual' },
	{ value: 'title_asc', label: 'Alphabetical' },
	{ value: 'modified_desc', label: 'Recently modified' },
	{ value: 'status', label: 'Status' },
];

/**
 * Sort a list of documents according to the active sort mode.
 *
 * @param {Array<Object>} docs Documents to sort.
 * @param {string}        mode Sort mode.
 * @return {Array<Object>} A new sorted array.
 */
function sortDocuments(docs, mode) {
	const out = [...docs];
	const titleCmp = (a, b) =>
		(a.title || 'Untitled').localeCompare(
			b.title || 'Untitled',
			undefined,
			{
				sensitivity: 'base',
			}
		);

	switch (mode) {
		case 'title_asc':
			out.sort(titleCmp);
			break;
		case 'modified_desc':
			out.sort((a, b) => {
				const ta = a.modified ? Date.parse(a.modified) : 0;
				const tb = b.modified ? Date.parse(b.modified) : 0;
				if (tb !== ta) {
					return tb - ta;
				}
				return titleCmp(a, b);
			});
			break;
		case 'status':
			out.sort((a, b) => {
				const sa = a.status === 'publish' ? 1 : 0;
				const sb = b.status === 'publish' ? 1 : 0;
				if (sa !== sb) {
					return sa - sb;
				}
				return titleCmp(a, b);
			});
			break;
		case 'manual':
		default:
			break;
	}

	return out;
}

/**
 * Collapsible collection section with kebab menu and drop target.
 *
 * @param {Object}          root0                         Props.
 * @param {string}          root0.title                   Section title.
 * @param {number}          root0.count                   Document count.
 * @param {boolean}         root0.collapsed               Whether collapsed.
 * @param {Function}        root0.onToggle                Toggle callback.
 * @param {Function}        [root0.onDelete]              Delete callback.
 * @param {Function}        [root0.onRename]              Rename callback.
 * @param {Function}        [root0.onDrop]                Drop handler callback.
 * @param {string}          root0.dropTargetId            Drop zone ID.
 * @param {string}          root0.dragOverId              Currently hovered drop zone.
 * @param {Function}        root0.setDragOver             Set drag-over state.
 * @param {React.ReactNode} root0.children                Nested content.
 * @param {number}          [root0.collectionId]          Collection ID (number for taxonomy collections).
 * @param {Function}        [root0.onCollectionDragStart] Collection drag-start handler.
 * @param {Function}        [root0.onCollectionDragOver]  Collection drag-over handler.
 * @param {Function}        [root0.onCollectionDrop]      Collection drop handler.
 * @param {Function}        [root0.onCollectionDragEnd]   Collection drag-end handler.
 * @param {string}          [root0.collectionDropPos]     Drop indicator position ('before'|'after'|null).
 * @param {string}          [root0.sortMode]              Active sort mode for this section.
 * @param {Function}        [root0.onSortModeChange]      Sort mode change handler.
 * @param {Function}        [root0.onDeleteAll]           Bulk-delete-all-docs handler.
 * @param {string}          [root0.deleteAllLabel]        Label for the bulk-delete menu item.
 */
function CollectionSection({
	title,
	count,
	collapsed,
	onToggle,
	onDelete,
	onRename,
	onDrop,
	dropTargetId,
	dragOverId,
	setDragOver,
	collectionId,
	onCollectionDragStart,
	onCollectionDragOver,
	onCollectionDrop,
	onCollectionDragEnd,
	collectionDropPos,
	sortMode,
	onSortModeChange,
	onDeleteAll,
	deleteAllLabel = 'Delete all documents',
	children,
}) {
	const isOver = dragOverId === dropTargetId;
	const [editing, setEditing] = useState(false);
	const [editValue, setEditValue] = useState(title);
	const hasMenu =
		onDelete ||
		onRename ||
		typeof onSortModeChange === 'function' ||
		typeof onDeleteAll === 'function';
	const isReorderable = typeof collectionId === 'number';

	const commitRename = () => {
		const trimmed = editValue.trim();
		if (trimmed && trimmed !== title && onRename) {
			onRename(trimmed);
		}
		setEditing(false);
	};

	return (
		<div
			className={`wpgraphql-ide-collection-section${collectionDropPos === 'before' ? ' is-drop-above' : ''}${collectionDropPos === 'after' ? ' is-drop-below' : ''}`}
		>
			<div
				className={`wpgraphql-ide-collection-header${isOver ? ' is-drag-over' : ''}${isReorderable ? ' is-reorderable' : ''}`}
				draggable={isReorderable}
				onDragStart={(e) => {
					if (!isReorderable || !onCollectionDragStart) {
						return;
					}
					onCollectionDragStart(collectionId);
					e.dataTransfer.setData(
						'application/x-wpgraphql-ide-collection',
						String(collectionId)
					);
					e.dataTransfer.effectAllowed = 'move';
				}}
				onDragEnd={() => {
					if (onCollectionDragEnd) {
						onCollectionDragEnd();
					}
				}}
				onDragOver={(e) => {
					// Two cases: a doc drag (assign-to-collection) or a
					// collection drag (sibling reorder). The presence of
					// the collection mime decides which one.
					const types = Array.from(e.dataTransfer.types || []);
					const isCollectionDrag = types.includes(
						'application/x-wpgraphql-ide-collection'
					);
					if (isCollectionDrag && isReorderable) {
						e.preventDefault();
						e.dataTransfer.dropEffect = 'move';
						const rect = e.currentTarget.getBoundingClientRect();
						const position =
							e.clientY < rect.top + rect.height / 2
								? 'before'
								: 'after';
						if (onCollectionDragOver) {
							onCollectionDragOver(collectionId, position);
						}
						return;
					}
					e.preventDefault();
					e.dataTransfer.dropEffect = 'move';
					setDragOver(dropTargetId);
				}}
				onDragLeave={() => setDragOver(null)}
				onDrop={(e) => {
					const types = Array.from(e.dataTransfer.types || []);
					const isCollectionDrag = types.includes(
						'application/x-wpgraphql-ide-collection'
					);
					if (isCollectionDrag && isReorderable && onCollectionDrop) {
						e.preventDefault();
						e.stopPropagation();
						onCollectionDrop(collectionId);
						return;
					}
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
				<span className="wpgraphql-ide-collection-count">
					({count})
				</span>
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
								{typeof onSortModeChange === 'function' && (
									<MenuGroup label="Sort by">
										{SORT_OPTIONS.map((opt) => (
											<MenuItem
												key={opt.value}
												icon={
													(sortMode || 'manual') ===
													opt.value
														? check
														: null
												}
												onClick={() => {
													onSortModeChange(opt.value);
													closeMenu();
												}}
											>
												{opt.label}
											</MenuItem>
										))}
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
								{typeof onDeleteAll === 'function' && (
									<MenuGroup>
										<MenuItem
											isDestructive
											onClick={() => {
												closeMenu();
												onDeleteAll();
											}}
										>
											{deleteAllLabel}
										</MenuItem>
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
	const { confirm } = useDialog();
	const notify = (content, type = 'default') =>
		doAction('wpgraphql-ide.notice', content, type);

	const [search, setSearch] = useState('');
	const [statusFilter, setStatusFilter] = useState('all');
	const [collapsedSections, setCollapsedSections] = useState({});
	const [creatingCollection, setCreatingCollection] = useState(false);
	const [newCollectionName, setNewCollectionName] = useState('');
	const [dragOverId, setDragOverId] = useState(null);
	const [deleteTarget, setDeleteTarget] = useState(null);
	const [renameTarget, setRenameTarget] = useState(null);
	// Drop indicator for sibling reorder. `kind` distinguishes between
	// dragging a document vs a collection so we render the right line.
	const [dropIndicator, setDropIndicator] = useState(null);
	const dragDocRef = useRef(null);
	const dragCollectionRef = useRef(null);

	const { documents, activeTab } = useSelect((select) => {
		const editor = select('wpgraphql-ide/document-editor');
		return {
			documents: editor.getDocuments(),
			activeTab: editor.getActiveTab(),
		};
	}, []);

	const { collections, sortModes } = useSelect((select) => {
		const app = select('wpgraphql-ide/app');
		return {
			collections: app.getCollections(),
			sortModes: app.getCollectionSortModes(),
		};
	}, []);

	const sortModeFor = useCallback(
		(key) => sortModes[String(key)] || 'manual',
		[sortModes]
	);

	const { switchTab, removeDocument, reorderDocuments } = useDispatch(
		'wpgraphql-ide/document-editor'
	);
	const {
		loadCollections,
		addCollection,
		removeCollection,
		renameCollection,
		reorderCollections,
		setCollectionSortMode,
	} = useDispatch('wpgraphql-ide/app');

	const [exportOpen, setExportOpen] = useState(false);

	useEffect(() => {
		loadCollections();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	useEffect(() => {
		const namespace = 'wpgraphql-ide/saved-queries-panel';
		addAction(PANEL_ACTION_HOOK, namespace, (action) => {
			if (action === 'new-collection') {
				setCreatingCollection(true);
			} else if (action === 'import') {
				importInputRef.current?.click();
			} else if (action === 'export') {
				setExportOpen(true);
			}
		});
		return () => removeAction(PANEL_ACTION_HOOK, namespace);
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
		for (const cId of Object.keys(groups)) {
			groups[cId] = sortDocuments(groups[cId], sortModeFor(cId));
		}
		return {
			grouped: groups,
			uncategorized: sortDocuments(ungrouped, sortModeFor('_documents')),
		};
	}, [savedDocs, filterDocs, sortModeFor]);

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

	const performDeleteCollection = async ({ deleteContents }) => {
		if (!deleteTarget) {
			return;
		}
		const { id } = deleteTarget;
		if (deleteContents) {
			await deleteCollectionWithContents(id);
			await loadCollections();
		} else {
			await removeCollection(id);
		}
		// Documents in the deleted collection still carry the now-stale
		// term ID in their `collections` array. Reload from the server
		// so the renderer (which groups by live collections) treats
		// them as uncategorized instead of orphaning them under a
		// ghost group.
		reloadDocs();
	};

	const importInputRef = useRef(null);

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

	const handleDeleteAllUncategorized = async () => {
		const targets = uncategorized;
		if (targets.length === 0) {
			return;
		}
		const ok = await confirm({
			title: 'Delete all documents',
			message: `Delete all ${targets.length} document${
				targets.length === 1 ? '' : 's'
			} in "Documents"? This cannot be undone.`,
			confirmLabel: 'Delete all',
			isDestructive: true,
		});
		if (!ok) {
			return;
		}
		await Promise.all(targets.map((d) => removeDocument(d.id)));
	};

	const handleDiscardAllUnsaved = async () => {
		const targets = unsavedDocs;
		if (targets.length === 0) {
			return;
		}
		const ok = await confirm({
			title: 'Discard unsaved tabs',
			message: `Discard all ${targets.length} unsaved tab${
				targets.length === 1 ? '' : 's'
			}? Their contents will be lost.`,
			confirmLabel: 'Discard all',
			isDestructive: true,
		});
		if (!ok) {
			return;
		}
		await Promise.all(targets.map((d) => removeDocument(d.id)));
	};

	const renderDoc = (doc) => {
		const isActive = String(doc.id) === String(activeTab);
		const isUnsaved = isTempId(doc.id);
		const isPublished = !isUnsaved && doc.status === 'publish';
		const isDraft = !isUnsaved && !isPublished;
		const docCollections = doc.collections || [];
		const sectionKey =
			docCollections.length > 0 ? docCollections[0] : '_documents';
		const canDrag = !isUnsaved && sortModeFor(sectionKey) === 'manual';

		const isDropAbove =
			dropIndicator?.kind === 'doc' &&
			dropIndicator.id === String(doc.id) &&
			dropIndicator.position === 'before';
		const isDropBelow =
			dropIndicator?.kind === 'doc' &&
			dropIndicator.id === String(doc.id) &&
			dropIndicator.position === 'after';

		return (
			<li
				key={doc.id}
				draggable={canDrag}
				onDragStart={(e) => {
					dragDocRef.current = String(doc.id);
					dragCollectionRef.current = null;
					e.dataTransfer.setData('text/plain', String(doc.id));
					e.dataTransfer.setData(
						'application/x-wpgraphql-ide-doc',
						String(doc.id)
					);
					e.dataTransfer.effectAllowed = 'move';
				}}
				onDragOver={(e) => {
					if (!dragDocRef.current) {
						return;
					}
					if (String(dragDocRef.current) === String(doc.id)) {
						return;
					}
					e.preventDefault();
					e.dataTransfer.dropEffect = 'move';
					const rect = e.currentTarget.getBoundingClientRect();
					const position =
						e.clientY < rect.top + rect.height / 2
							? 'before'
							: 'after';
					setDropIndicator({
						kind: 'doc',
						id: String(doc.id),
						position,
					});
				}}
				onDrop={(e) => {
					if (!dragDocRef.current) {
						return;
					}
					e.preventDefault();
					e.stopPropagation();
					const sourceId = String(dragDocRef.current);
					const targetId = String(doc.id);
					if (sourceId === targetId) {
						return;
					}
					const rect = e.currentTarget.getBoundingClientRect();
					const before = e.clientY < rect.top + rect.height / 2;
					const ids = savedDocs.map((d) => String(d.id));
					const fromIdx = ids.indexOf(sourceId);
					if (fromIdx === -1) {
						return;
					}
					ids.splice(fromIdx, 1);
					let toIdx = ids.indexOf(targetId);
					if (toIdx === -1) {
						return;
					}
					if (!before) {
						toIdx += 1;
					}
					ids.splice(toIdx, 0, sourceId);
					reorderDocuments(ids);
					setDropIndicator(null);
					dragDocRef.current = null;
				}}
				onDragEnd={() => {
					dragDocRef.current = null;
					setDragOverId(null);
					setDropIndicator(null);
				}}
				className={`wpgraphql-ide-document-item${isActive ? ' is-active' : ''}${isPublished ? ' is-published' : ''}${canDrag ? ' is-draggable' : ''}${isDropAbove ? ' is-drop-above' : ''}${isDropBelow ? ' is-drop-below' : ''}`}
			>
				<button
					type="button"
					className="wpgraphql-ide-document-label"
					onClick={() => switchTab(String(doc.id))}
				>
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
										onClick={() => {
											closeMenu();
											setRenameTarget(doc);
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

	const emptyMessage = () => {
		if (statusFilter === 'draft') {
			return 'No drafts in this collection';
		}
		if (statusFilter === 'publish') {
			return 'No published documents in this collection';
		}
		return 'No documents in this collection';
	};

	const renderDocList = (docs) => {
		if (docs.length === 0) {
			return (
				<p className="wpgraphql-ide-collection-empty">
					{emptyMessage()}
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
				<input
					ref={importInputRef}
					type="file"
					accept="application/json,.json"
					onChange={handleImportFile}
					style={{ display: 'none' }}
				/>
			</div>

			<TabPanel
				className="wpgraphql-ide-status-filter-tabs"
				tabs={[
					{ name: 'all', title: 'All' },
					{ name: 'draft', title: 'Drafts' },
					{ name: 'publish', title: 'Published' },
				]}
				initialTabName={statusFilter}
				onSelect={(name) => setStatusFilter(name)}
			>
				{() => null}
			</TabPanel>

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
								setDeleteTarget({ id: c.id, name: c.name })
							}
							onRename={(newName) =>
								renameCollection(c.id, newName)
							}
							onDrop={(docId) =>
								handleDropToCollection(docId, c.id)
							}
							dropTargetId={`collection-${c.id}`}
							dragOverId={dragOverId}
							setDragOver={setDragOverId}
							collectionId={c.id}
							onCollectionDragStart={(id) => {
								dragCollectionRef.current = id;
								dragDocRef.current = null;
							}}
							onCollectionDragOver={(id, position) => {
								if (!dragCollectionRef.current) {
									return;
								}
								if (dragCollectionRef.current === id) {
									return;
								}
								setDropIndicator({
									kind: 'collection',
									id,
									position,
								});
							}}
							onCollectionDrop={(targetId) => {
								const sourceId = dragCollectionRef.current;
								if (!sourceId || sourceId === targetId) {
									return;
								}
								const ids = collections.map((col) => col.id);
								const fromIdx = ids.indexOf(sourceId);
								if (fromIdx === -1) {
									return;
								}
								ids.splice(fromIdx, 1);
								let toIdx = ids.indexOf(targetId);
								if (toIdx === -1) {
									return;
								}
								if (dropIndicator?.position === 'after') {
									toIdx += 1;
								}
								ids.splice(toIdx, 0, sourceId);
								reorderCollections(ids);
								setDropIndicator(null);
								dragCollectionRef.current = null;
							}}
							collectionDropPos={
								dropIndicator?.kind === 'collection' &&
								dropIndicator.id === c.id
									? dropIndicator.position
									: null
							}
							onCollectionDragEnd={() => {
								dragCollectionRef.current = null;
								setDropIndicator(null);
							}}
							sortMode={sortModeFor(c.id)}
							onSortModeChange={(mode) =>
								setCollectionSortMode(c.id, mode)
							}
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
					sortMode={sortModeFor('_documents')}
					onSortModeChange={(mode) =>
						setCollectionSortMode('_documents', mode)
					}
					onDeleteAll={
						uncategorized.length > 0
							? handleDeleteAllUncategorized
							: undefined
					}
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

				{unsavedDocs.length > 0 && (
					<CollectionSection
						title="Unsaved"
						count={unsavedDocs.length}
						collapsed={!!collapsedSections._unsaved}
						onToggle={() => toggleSection('_unsaved')}
						dropTargetId="collection-unsaved"
						dragOverId={dragOverId}
						setDragOver={setDragOverId}
						onDeleteAll={handleDiscardAllUnsaved}
						deleteAllLabel="Discard all unsaved"
					>
						<ul className="wpgraphql-ide-documents-list">
							{unsavedDocs.map(renderDoc)}
						</ul>
					</CollectionSection>
				)}
			</div>

			{creatingCollection && (
				<div className="wpgraphql-ide-new-collection-row">
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
						placeholder="New collection name…"
						// eslint-disable-next-line jsx-a11y/no-autofocus
						autoFocus
					/>
				</div>
			)}
			{exportOpen && (
				<ExportDialog
					fetchPayload={exportDocuments}
					collections={collections}
					onClose={() => setExportOpen(false)}
				/>
			)}
			{deleteTarget && (
				<DeleteCollectionDialog
					name={deleteTarget.name}
					onConfirm={performDeleteCollection}
					onClose={() => setDeleteTarget(null)}
				/>
			)}
			{renameTarget && (
				<SaveDialog
					mode="rename"
					defaultTitle={renameTarget.title || ''}
					defaultCollectionId={
						Array.isArray(renameTarget.collections) &&
						renameTarget.collections.length > 0
							? renameTarget.collections[0]
							: null
					}
					collections={collections}
					onCreateCollection={async (name) => {
						const created = await addCollection(name);
						return created || null;
					}}
					onSubmit={async ({ title, collectionId }) => {
						await updateDocument(renameTarget.id, {
							title,
							collections:
								collectionId !== null ? [collectionId] : [],
						});
						reloadDocs();
					}}
					onClose={() => setRenameTarget(null)}
				/>
			)}
		</div>
	);
}
