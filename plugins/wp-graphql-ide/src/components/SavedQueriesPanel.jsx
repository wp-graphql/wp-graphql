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
import hooks from '../wordpress-hooks';
import { useDialog } from './dialogs/DialogProvider';
import { DeleteCollectionDialog } from './dialogs/DeleteCollectionDialog';
import { ExportDialog } from './dialogs/ExportDialog';
import { SaveDialog } from './dialogs/SaveDialog';
import { NewCollectionDialog } from './dialogs/NewCollectionDialog';
import { ShareCollectionDialog } from './dialogs/ShareCollectionDialog';
import { RenameInput } from './RenameInput';
import { useDebouncedCallback } from '../hooks/useDebouncedCallback';
import {
	updateDocument,
	deleteCollectionWithContents,
	exportDocuments,
	importDocuments,
} from '../api/documents';
import { displayDocTitle } from '../utils/derive-doc-title';
import { isTempId } from '../utils/document-id';

/**
 * Hydrate the `collapsedSections` map from the bootstrap-localized
 * `sectionStates` meta blob. The server stores arbitrary per-section
 * state under each key; here we project it down to just the
 * `collapsed` booleans this component cares about. Keys we don't
 * recognize (or sections without a `collapsed` field) are ignored,
 * which means the future-proof envelope can grow new fields without
 * breaking the hydration path.
 *
 * @return {Object<string, boolean>} Map of section key to collapsed flag.
 */
function hydrateCollapsedSections() {
	const data = window.WPGRAPHQL_IDE_DATA || {};
	const raw = data.sectionStates || {};
	const out = {};
	for (const [key, state] of Object.entries(raw)) {
		if (state && typeof state.collapsed === 'boolean') {
			out[key] = state.collapsed;
		}
	}
	return out;
}

/**
 * Whether the current WordPress user can enumerate other users.
 *
 * The Sharing dialog needs to search and resolve user IDs, which the
 * GraphQL `users` field gates on the `list_users` capability. IDE
 * access is gated on `manage_graphql_ide`, which is a strict superset
 * — admins have both, but a custom role granted IDE access (e.g. an
 * author with the editor toolkit) may not. Hide the Sharing
 * affordance entirely in that case so the dialog never opens onto a
 * "0 users found" / silent 401 dead end.
 */
function userCanListUsers() {
	const data = window.WPGRAPHQL_IDE_DATA || {};
	return Boolean(data.capabilities && data.capabilities.listUsers);
}

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
			popoverProps={{ placement: 'bottom-start' }}
		>
			{({ onClose: closeMenu }) => (
				<>
					<MenuGroup>
						<MenuItem
							onClick={() => {
								closeMenu();
								hooks.doAction(
									PANEL_ACTION_HOOK,
									'new-collection'
								);
							}}
						>
							New collection
						</MenuItem>
					</MenuGroup>
					<MenuGroup>
						<MenuItem
							onClick={() => {
								closeMenu();
								hooks.doAction(PANEL_ACTION_HOOK, 'import');
							}}
						>
							Import documents…
						</MenuItem>
						<MenuItem
							disabled={collectionCount === 0}
							onClick={() => {
								closeMenu();
								hooks.doAction(PANEL_ACTION_HOOK, 'export');
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
		displayDocTitle(a).localeCompare(displayDocTitle(b), undefined, {
			sensitivity: 'base',
		});

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
 * @param {Function}        [root0.onShare]               Open a sharing dialog for this collection.
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
	onShare,
	children,
}) {
	const isOver = !!dropTargetId && dragOverId === dropTargetId;
	const [editing, setEditing] = useState(false);
	const [editValue, setEditValue] = useState(title);
	const hasMenu =
		onDelete ||
		onRename ||
		typeof onSortModeChange === 'function' ||
		typeof onDeleteAll === 'function' ||
		typeof onShare === 'function';
	const isReorderable = typeof collectionId === 'number';

	return (
		<div
			className={`wpgraphql-ide-collection-section${collectionDropPos === 'before' ? ' is-drop-above' : ''}${collectionDropPos === 'after' ? ' is-drop-below' : ''}`}
		>
			<div
				className={`wpgraphql-ide-collection-header${isOver ? ' is-drag-over' : ''}${isReorderable ? ' is-reorderable' : ''}`}
				role="button"
				tabIndex={editing ? -1 : 0}
				aria-expanded={!collapsed}
				aria-label={`${title}: ${collapsed ? 'expand' : 'collapse'} section`}
				onClick={() => {
					// Don't toggle when the user is mid-rename or interacting
					// with a control inside the row (kebab, rename input).
					// Inner controls stop propagation themselves; this guard
					// covers focus rings on link hover etc.
					if (editing) {
						return;
					}
					onToggle();
				}}
				onKeyDown={(e) => {
					if (editing) {
						return;
					}
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						onToggle();
					}
				}}
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
				{/* Chevron is a static visual cue now; the parent header
				    handles the click target so the entire row is hit-able. */}
				<span
					className="wpgraphql-ide-collection-toggle"
					aria-hidden="true"
				>
					<Icon
						icon={collapsed ? chevronRight : chevronDown}
						size={18}
					/>
				</span>
				{editing ? (
					<RenameInput
						className="wpgraphql-ide-collection-rename-input"
						ariaLabel="Rename collection"
						value={editValue}
						onChange={setEditValue}
						onCommit={(trimmed) => {
							if (trimmed !== title && onRename) {
								onRename(trimmed);
							}
							setEditing(false);
						}}
						onCancel={() => {
							setEditing(false);
							setEditValue(title);
						}}
					/>
				) : (
					<span className="wpgraphql-ide-collection-title">
						{title}
					</span>
				)}
				<span className="wpgraphql-ide-collection-count">
					({count})
				</span>
				{hasMenu ? (
					// Wrap in a stopPropagation span so clicks on the kebab
					// (toggle, menu items) don't bubble up to the header's
					// expand/collapse handler.
					// eslint-disable-next-line jsx-a11y/no-static-element-interactions, jsx-a11y/click-events-have-key-events
					<span
						className="wpgraphql-ide-collection-kebab-wrap"
						onClick={(e) => e.stopPropagation()}
					>
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
									{/* Order is risk-ascending: safe / reversible
								    actions first, data-mutating actions
								    further down, destructive at the bottom.
								    Share is the most common safe action so
								    it leads. Sort is purely a view setting.
								    Rename modifies the source-of-truth name
								    so it sits closer to Delete to make
								    accidental clicks less likely. */}
									{typeof onShare === 'function' && (
										<MenuGroup>
											<MenuItem
												onClick={() => {
													onShare();
													closeMenu();
												}}
											>
												Sharing
											</MenuItem>
										</MenuGroup>
									)}
									{typeof onSortModeChange === 'function' && (
										<MenuGroup label="Sort by">
											{SORT_OPTIONS.map((opt) => (
												<MenuItem
													key={opt.value}
													icon={
														(sortMode ||
															'manual') ===
														opt.value
															? check
															: null
													}
													onClick={() => {
														onSortModeChange(
															opt.value
														);
														closeMenu();
													}}
												>
													{opt.label}
												</MenuItem>
											))}
										</MenuGroup>
									)}
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
					</span>
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
		hooks.doAction('wpgraphql-ide.notice', content, type);

	const [search, setSearch] = useState('');
	// WP-style filter row. Acts as a *filter*, not a folder — sections
	// render in their own order below; the filter just hides the ones
	// that don't match. Avoids the public/private "folder" mental model
	// that taxonomy-style tabs invite.
	//
	// Currently `'all' | 'mine'`. The internal predicates already
	// distinguish sitewide / personal / shared independently, so adding
	// a 'sitewide' filter is a one-line addition: a new tab descriptor
	// below + a `filter === 'sitewide'` branch wherever a section's
	// `show*` flag needs it.
	const [filter, setFilter] = useState('all');
	// Seed from per-user meta so a user's collapse choices survive a
	// reload. The hydrator only pulls the `collapsed` field; other
	// per-section state (added later) sits alongside it server-side.
	const [collapsedSections, setCollapsedSections] = useState(
		hydrateCollapsedSections
	);
	const [newCollectionOpen, setNewCollectionOpen] = useState(false);
	const [shareTarget, setShareTarget] = useState(null);
	const canListUsers = userCanListUsers();
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
		// Workspace tabs (e.g. Settings) live in the documents store so
		// the tab strip can render their titles, but they aren't query
		// documents — exclude them from the Saved Queries panel.
		const all = editor.getDocuments();
		return {
			documents: all.filter((d) => !d.tabType),
			activeTab: editor.getActiveTab(),
		};
	}, []);

	const { collections, sortModes, personalCollections, sharedCollections } =
		useSelect((select) => {
			const app = select('wpgraphql-ide/app');
			return {
				collections: app.getCollections(),
				sortModes: app.getCollectionSortModes(),
				personalCollections: app.getPersonalCollections(),
				sharedCollections: app.getSharedCollections(),
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
		createPersonalCollection,
		renamePersonalCollection,
		removePersonalCollection,
		togglePersonalCollectionMembership,
		updatePersonalCollectionSharedWith,
		saveUserPreference,
	} = useDispatch('wpgraphql-ide/app');

	// Debounce the persist so rapid toggles coalesce into a single
	// store dispatch (which in turn handles the REST write). 500ms keeps
	// the network quiet while still feeling instantaneous to the user.
	const [persistSectionStates] = useDebouncedCallback((blob) => {
		saveUserPreference('section_states', JSON.stringify(blob));
	}, 500);

	const [exportOpen, setExportOpen] = useState(false);

	useEffect(() => {
		loadCollections();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	useEffect(() => {
		const namespace = 'wpgraphql-ide/saved-queries-panel';
		hooks.addAction(PANEL_ACTION_HOOK, namespace, (action) => {
			if (action === 'new-collection') {
				setNewCollectionOpen(true);
			} else if (action === 'import') {
				importInputRef.current?.click();
			} else if (action === 'export') {
				setExportOpen(true);
			}
		});
		return () => hooks.removeAction(PANEL_ACTION_HOOK, namespace);
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
			if (search.trim()) {
				const q = search.toLowerCase();
				filtered = filtered.filter(
					(d) =>
						displayDocTitle(d).toLowerCase().includes(q) ||
						(d.query || '').toLowerCase().includes(q)
				);
			}
			return filtered;
		},
		[search]
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

	// Personal-collection groupings, derived from each entry's document_ids.
	// Lookup is O(n) per personal collection; fine at IDE-scale.
	const personalGrouped = useMemo(() => {
		const filtered = filterDocs(savedDocs);
		const byId = new Map(filtered.map((d) => [Number(d.id), d]));
		const groups = {};
		for (const pc of personalCollections) {
			const docs = (pc.document_ids || [])
				.map((id) => byId.get(Number(id)))
				.filter(Boolean);
			groups[pc.id] = sortDocuments(docs, sortModeFor(pc.id));
		}
		return groups;
	}, [savedDocs, filterDocs, personalCollections, sortModeFor]);

	// Filter-row counts: unique-doc counts so a doc in multiple
	// collections doesn't double-count in `All`. Sharing semantics are
	// communicated per-section (inline attribution + kebab actions),
	// not via a dedicated filter — keeping the filter row to two
	// values matches WP admin convention.
	const { allCount, mineCount } = useMemo(() => {
		// `Mine` counts only docs that live in a personal collection.
		// Uncategorized docs surface under `All` (which already covers
		// everything the user can see), so they're intentionally
		// excluded from Mine to keep Mine = "my organized collections."
		const mineIds = new Set();
		for (const pc of personalCollections) {
			for (const id of pc.document_ids || []) {
				if (savedDocs.find((d) => Number(d.id) === Number(id))) {
					mineIds.add(Number(id));
				}
			}
		}
		const incomingSharedIds = new Set();
		for (const sc of sharedCollections) {
			for (const d of sc.documents || []) {
				incomingSharedIds.add(Number(d.id));
			}
		}
		const allIds = new Set([
			...savedDocs.map((d) => Number(d.id)),
			...incomingSharedIds,
		]);
		return {
			allCount: allIds.size,
			mineCount: mineIds.size,
		};
	}, [savedDocs, personalCollections, sharedCollections]);

	const showSitewide = filter === 'all';
	const showPersonal = filter === 'all' || filter === 'mine';
	const showShared = filter === 'all';
	// Uncategorized "Documents" — author-scoped catch-all that doesn't
	// belong to a curated personal collection. Surfaces under All only;
	// Mine is reserved for the user's *organized* collections.
	const showUncategorized = filter === 'all';

	const toggleSection = (key) => {
		setCollapsedSections((prev) => {
			const next = { ...prev, [key]: !prev[key] };
			// Wrap each boolean in a per-section object so the envelope
			// can carry additional fields later (sort preferences,
			// last-viewed timestamps, etc.) without breaking the
			// hydration path or requiring a server release.
			const blob = {};
			for (const [k, collapsed] of Object.entries(next)) {
				blob[k] = { collapsed };
			}
			persistSectionStates(blob);
			return next;
		});
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
						{displayDocTitle(doc)}
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
												message: `Delete "${displayDocTitle(doc)}"? This cannot be undone.`,
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

	const emptyMessage = () => 'No documents in this collection';

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
				className="wpgraphql-ide-saved-queries-filter"
				tabs={[
					{ name: 'all', title: `All (${allCount})` },
					{ name: 'mine', title: `Mine (${mineCount})` },
				]}
				initialTabName={filter}
				onSelect={(name) => setFilter(name)}
			>
				{() => null}
			</TabPanel>

			<div className="wpgraphql-ide-collections-list">
				{/* "Documents" — uncategorized author-scoped docs. Pinned
				    above the curated collections per the inbox-at-top
				    pattern (Gmail, Linear, VS Code Source Control): the
				    user's default working bucket goes first, organized
				    layers below. Renders under "All" and "Mine" since
				    these are effectively private until assigned to a
				    sitewide collection. */}
				{showUncategorized && (
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
				)}

				{showSitewide &&
					collections.map((c) => {
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
									const ids = collections.map(
										(col) => col.id
									);
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

				{/* Shared with me — read-only personal collections owned by
				    other users that have been shared with the current user. */}
				{showShared &&
					sharedCollections.map((sc) => {
						const sectionKey = `shared-${sc.id}`;
						const sortedDocs = sortDocuments(
							Array.isArray(sc.documents) ? sc.documents : [],
							sortModeFor(sectionKey)
						);
						return (
							<CollectionSection
								key={sectionKey}
								title={`${sc.name} — shared by ${sc.owner?.display_name || 'another user'}`}
								count={sortedDocs.length}
								collapsed={
									sectionKey in collapsedSections
										? collapsedSections[sectionKey]
										: sortedDocs.length === 0
								}
								onToggle={() => toggleSection(sectionKey)}
								sortMode={sortModeFor(sectionKey)}
								onSortModeChange={(mode) =>
									setCollectionSortMode(sectionKey, mode)
								}
							>
								{sortedDocs.length > 0 ? (
									<ul className="wpgraphql-ide-documents-list">
										{sortedDocs.map((doc) => (
											<li
												key={doc.id}
												className="wpgraphql-ide-document-item"
											>
												<button
													type="button"
													className="wpgraphql-ide-document-button"
													onClick={() =>
														switchTab(doc.id)
													}
												>
													<span className="wpgraphql-ide-document-title-text">
														{doc.title ||
															'Untitled'}
													</span>
												</button>
											</li>
										))}
									</ul>
								) : (
									<p className="wpgraphql-ide-collection-empty">
										No documents
									</p>
								)}
							</CollectionSection>
						);
					})}

				{/* Personal collections — per-user, with sharing ACL. */}
				{showPersonal &&
					personalCollections.map((pc) => {
						const docs = personalGrouped[pc.id] || [];
						return (
							<CollectionSection
								key={`pc-${pc.id}`}
								title={pc.name}
								count={docs.length}
								collapsed={
									pc.id in collapsedSections
										? collapsedSections[pc.id]
										: docs.length === 0
								}
								onToggle={() => toggleSection(pc.id)}
								onDelete={() => removePersonalCollection(pc.id)}
								onRename={(newName) =>
									renamePersonalCollection(pc.id, newName)
								}
								onShare={
									canListUsers
										? () => setShareTarget(pc)
										: undefined
								}
								sortMode={sortModeFor(pc.id)}
								onSortModeChange={(mode) =>
									setCollectionSortMode(pc.id, mode)
								}
							>
								{renderDocList(docs)}
							</CollectionSection>
						);
					})}
			</div>

			{/* Unsaved tabs — orthogonal to the visibility filter (they
			    aren't on the server yet), so render them outside the
			    filtered list so they're always visible when present. */}
			{unsavedDocs.length > 0 && (
				<div className="wpgraphql-ide-collections-list wpgraphql-ide-collections-list--unsaved">
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
				</div>
			)}

			{newCollectionOpen && (
				<NewCollectionDialog
					onClose={() => setNewCollectionOpen(false)}
					onCreateSitewide={async (name) => {
						await addCollection(name);
					}}
					onCreatePersonal={async (name) => {
						await createPersonalCollection(name);
					}}
				/>
			)}
			{shareTarget && (
				<ShareCollectionDialog
					collection={shareTarget}
					onClose={() => setShareTarget(null)}
					onSubmit={async (sharedWith) => {
						await updatePersonalCollectionSharedWith(
							shareTarget.id,
							sharedWith
						);
					}}
				/>
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
					defaultCollectionIds={
						Array.isArray(renameTarget.collections)
							? renameTarget.collections
							: []
					}
					collections={collections}
					personalCollections={personalCollections}
					onSubmit={async ({
						title,
						collectionIds,
						personalCollectionIds,
					}) => {
						await updateDocument(renameTarget.id, {
							title,
							collections: Array.isArray(collectionIds)
								? collectionIds
								: [],
						});
						if (
							Array.isArray(personalCollectionIds) &&
							personalCollectionIds.length > 0
						) {
							for (const pcId of personalCollectionIds) {
								await togglePersonalCollectionMembership(
									pcId,
									renameTarget.id
								);
							}
						}
						reloadDocs();
					}}
					onClose={() => setRenameTarget(null)}
				/>
			)}
		</div>
	);
}
