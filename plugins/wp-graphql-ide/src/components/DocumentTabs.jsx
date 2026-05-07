import React, { useRef, useState, useLayoutEffect, useCallback } from 'react';
import {
	DropdownMenu,
	MenuGroup,
	MenuItem,
	Button,
	Tooltip,
} from '@wordpress/components';
import { Icon, plus, moreVertical } from '@wordpress/icons';
import { RenameInput } from './RenameInput';

const ADD_BTN_W = 32;
const KEBAB_BTN_W = 32;
const OVERFLOW_BTN_W = 32;

/**
 * IDE-style tab strip for open documents.
 *
 * Visible tabs are rendered inline; any tabs that don't fit fold into an
 * overflow dropdown on the right. Renames are triggered from the sidebar.
 *
 * @param {Object}   props
 * @param {Array}    props.tabs      Array of `{ id, title, dirty }` tab descriptors.
 * @param {string}   props.activeId  Id of the currently active tab.
 * @param {Function} props.onSwitch  Called with the clicked tab id.
 * @param {Function} props.onClose   Called with the id of the tab to close.
 * @param {Function} props.onCreate  Called when the "+" button is clicked.
 * @param {Function} props.onRename  Called with `(id, title)` after inline rename.
 * @param {Function} props.onReorder Called with the next id-array after a drag-drop reorder.
 *
 * @return {JSX.Element}
 */
export function DocumentTabs({
	tabs,
	activeId,
	onSwitch,
	onClose,
	onCreate,
	onRename,
	onReorder,
}) {
	const containerRef = useRef(null);
	const tabRefs = useRef({});
	const [cutoff, setCutoff] = useState(tabs.length);
	const [editingId, setEditingId] = useState(null);
	const [editValue, setEditValue] = useState('');
	// Drag-to-reorder state. `dragId` holds the id of the tab being
	// dragged; `dropTarget` holds the id and side ('before' | 'after')
	// of the tab the cursor is currently over. Both null when idle.
	const [dragId, setDragId] = useState(null);
	const [dropTarget, setDropTarget] = useState(null);

	const clearDrag = useCallback(() => {
		setDragId(null);
		setDropTarget(null);
	}, []);

	const handleDrop = useCallback(
		(targetId, position) => {
			if (!dragId || !targetId || dragId === targetId || !onReorder) {
				clearDrag();
				return;
			}
			const ids = tabs.map((t) => String(t.id));
			const fromIdx = ids.indexOf(String(dragId));
			let toIdx = ids.indexOf(String(targetId));
			if (fromIdx === -1 || toIdx === -1) {
				clearDrag();
				return;
			}
			// Remove first, then insert at the resolved index. Account
			// for the index shift when removing an earlier element.
			const [moved] = ids.splice(fromIdx, 1);
			if (fromIdx < toIdx) {
				toIdx -= 1;
			}
			const insertAt = position === 'after' ? toIdx + 1 : toIdx;
			ids.splice(insertAt, 0, moved);
			onReorder(ids);
			clearDrag();
		},
		[dragId, onReorder, tabs, clearDrag]
	);

	const recalculate = useCallback(() => {
		const container = containerRef.current;
		if (!container) {
			return;
		}
		const available = container.clientWidth;

		// First pass — do all tabs fit with the trailing "+" + kebab?
		let sum = ADD_BTN_W + KEBAB_BTN_W;
		for (const tab of tabs) {
			const el = tabRefs.current[tab.id];
			sum += el ? el.offsetWidth : 140;
		}
		if (sum <= available) {
			setCutoff(tabs.length);
			return;
		}

		// Second pass — reserve space for the overflow button too.
		sum = ADD_BTN_W + KEBAB_BTN_W + OVERFLOW_BTN_W;
		let count = 0;
		for (const tab of tabs) {
			const el = tabRefs.current[tab.id];
			const w = el ? el.offsetWidth : 140;
			if (sum + w > available) {
				break;
			}
			sum += w;
			count++;
		}
		setCutoff(Math.max(1, count));
	}, [tabs]);

	// Recalculate whenever the tab list changes.
	useLayoutEffect(() => {
		recalculate();
	}, [recalculate]);

	// Recalculate whenever the container resizes.
	useLayoutEffect(() => {
		const container = containerRef.current;
		if (!container) {
			return;
		}
		const obs = new window.ResizeObserver(recalculate);
		obs.observe(container);
		return () => obs.disconnect();
	}, [recalculate]);

	const closeMany = (ids) => {
		for (const id of ids) {
			onClose(String(id));
		}
	};

	const closeOthers = (keepId) => {
		closeMany(
			tabs.filter((t) => String(t.id) !== String(keepId)).map((t) => t.id)
		);
	};

	const closeAll = () => {
		closeMany(tabs.map((t) => t.id));
	};

	// Always keep the active tab in the visible range.
	const ordered = [...tabs];
	const activeIdx = ordered.findIndex(
		(t) => String(t.id) === String(activeId)
	);
	const safeCutoff = Math.min(cutoff, ordered.length);
	if (activeIdx >= safeCutoff && safeCutoff > 0) {
		const [activeTab] = ordered.splice(activeIdx, 1);
		ordered.splice(safeCutoff - 1, 0, activeTab);
	}

	const visibleTabs = ordered.slice(0, safeCutoff);
	const overflowTabs = ordered.slice(safeCutoff);

	return (
		<div
			ref={containerRef}
			className="wpgraphql-ide-tab-bar"
			role="tablist"
		>
			{visibleTabs.map((tab, idx) => {
				const isActive = String(tab.id) === String(activeId);
				return (
					<button
						key={tab.id}
						ref={(el) => {
							if (el) {
								tabRefs.current[tab.id] = el;
							}
						}}
						type="button"
						role="tab"
						aria-selected={isActive}
						tabIndex={isActive ? 0 : -1}
						draggable={editingId !== tab.id}
						className={`wpgraphql-ide-tab${isActive ? ' is-active' : ''}${tab.dirty ? ' is-dirty' : ''}${dragId === tab.id ? ' is-dragging' : ''}${dropTarget?.id === tab.id && dropTarget?.position === 'before' ? ' is-drop-before' : ''}${dropTarget?.id === tab.id && dropTarget?.position === 'after' ? ' is-drop-after' : ''}`}
						onClick={() => onSwitch(String(tab.id))}
						onDragStart={(e) => {
							setDragId(String(tab.id));
							e.dataTransfer.effectAllowed = 'move';
							// Required for Firefox to fire dragover/drop.
							e.dataTransfer.setData(
								'text/plain',
								String(tab.id)
							);
						}}
						onDragOver={(e) => {
							if (!dragId || dragId === String(tab.id)) {
								return;
							}
							e.preventDefault();
							e.dataTransfer.dropEffect = 'move';
							const rect =
								e.currentTarget.getBoundingClientRect();
							const position =
								e.clientX < rect.left + rect.width / 2
									? 'before'
									: 'after';
							setDropTarget((prev) => {
								if (
									prev?.id === String(tab.id) &&
									prev.position === position
								) {
									return prev;
								}
								return { id: String(tab.id), position };
							});
						}}
						onDragLeave={() => {
							// Clear only when leaving *this* tab; the
							// next dragover on a sibling will set fresh.
							setDropTarget((prev) =>
								prev?.id === String(tab.id) ? null : prev
							);
						}}
						onDrop={(e) => {
							e.preventDefault();
							handleDrop(String(tab.id), dropTarget?.position);
						}}
						onDragEnd={clearDrag}
						// Double-click to rename — matches macOS Finder, Chrome
						// bookmarks, and most native tab UIs. Lets the user
						// override the auto-derived title without hunting
						// through a kebab. Falls back to the existing kebab
						// rename in IDELayout for discoverability.
						onDoubleClick={(e) => {
							e.preventDefault();
							e.stopPropagation();
							setEditValue(tab.title || '');
							setEditingId(tab.id);
						}}
						onKeyDown={(e) => {
							if (
								e.key === 'ArrowRight' &&
								idx < visibleTabs.length - 1
							) {
								e.preventDefault();
								onSwitch(String(visibleTabs[idx + 1].id));
								tabRefs.current[
									visibleTabs[idx + 1].id
								]?.focus();
							}
							if (e.key === 'ArrowLeft' && idx > 0) {
								e.preventDefault();
								onSwitch(String(visibleTabs[idx - 1].id));
								tabRefs.current[
									visibleTabs[idx - 1].id
								]?.focus();
							}
						}}
					>
						<span className="wpgraphql-ide-tab-label">
							{tab.dirty && (
								<span
									className="wpgraphql-ide-tab-dirty"
									aria-label="Unsaved changes"
									role="img"
								/>
							)}
							{/* The static text is always rendered so it
							    drives the label's intrinsic width — no
							    layout shift when the input mounts on top
							    of it for inline rename. While editing
							    we show the in-flight value so the wrap
							    grows/shrinks naturally as the user types. */}
							<span className="wpgraphql-ide-tab-text-wrap">
								<span
									className="wpgraphql-ide-tab-text"
									aria-hidden={editingId === tab.id}
								>
									{editingId === tab.id
										? editValue || ' '
										: tab.title || 'Untitled'}
								</span>
								{editingId === tab.id && (
									<RenameInput
										className="wpgraphql-ide-tab-input"
										ariaLabel="Rename document"
										value={editValue}
										onChange={setEditValue}
										onCommit={(trimmed) => {
											onRename(tab.id, trimmed);
											setEditingId(null);
										}}
										onCancel={() => setEditingId(null)}
									/>
								)}
							</span>
						</span>

						{(() => {
							const isEditingThis = editingId === tab.id;
							return (
								<span
									className={`wpgraphql-ide-tab-close${isEditingThis ? ' is-disabled' : ''}`}
									role="button"
									tabIndex={isEditingThis ? -1 : 0}
									aria-disabled={
										isEditingThis ? true : undefined
									}
									onClick={(e) => {
										e.stopPropagation();
										if (isEditingThis) {
											return;
										}
										onClose(String(tab.id));
									}}
									onKeyDown={(e) => {
										if (isEditingThis) {
											return;
										}
										if (
											e.key === 'Enter' ||
											e.key === ' '
										) {
											e.preventDefault();
											e.stopPropagation();
											onClose(String(tab.id));
										}
									}}
									aria-label={`Close ${tab.title || 'Untitled'}`}
								>
									&times;
								</span>
							);
						})()}
					</button>
				);
			})}

			{overflowTabs.length > 0 && (
				<DropdownMenu
					icon={null}
					label="More tabs"
					toggleProps={{
						children: `+${overflowTabs.length}`,
						className: 'wpgraphql-ide-tab-overflow',
						size: 'compact',
					}}
				>
					{({ onClose: closeMenu }) => (
						<MenuGroup>
							{overflowTabs.map((tab) => (
								<MenuItem
									key={tab.id}
									onClick={() => {
										onSwitch(String(tab.id));
										closeMenu();
									}}
									suffix={
										<Button
											size="small"
											onClick={(e) => {
												e.stopPropagation();
												onClose(String(tab.id));
												closeMenu();
											}}
											aria-label={`Close ${tab.title || 'Untitled'}`}
											className="wpgraphql-ide-overflow-close"
										>
											&times;
										</Button>
									}
								>
									{tab.title || 'Untitled'}
								</MenuItem>
							))}
						</MenuGroup>
					)}
				</DropdownMenu>
			)}

			<Tooltip text="New document">
				<Button
					className="wpgraphql-ide-tab-add"
					onClick={onCreate}
					aria-label="New document"
					size="compact"
				>
					<Icon icon={plus} size={16} />
				</Button>
			</Tooltip>

			{tabs.length > 0 && (
				<DropdownMenu
					icon={moreVertical}
					label="Tab actions"
					toggleProps={{
						className: 'wpgraphql-ide-tab-kebab',
						size: 'compact',
					}}
				>
					{({ onClose: closeMenu }) => {
						const hasInactive = tabs.length > 1;
						return (
							<>
								<MenuGroup>
									<MenuItem
										onClick={() => {
											if (activeId) {
												onClose(String(activeId));
											}
											closeMenu();
										}}
										disabled={!activeId}
									>
										Close active tab
									</MenuItem>
									<MenuItem
										onClick={() => {
											closeOthers(activeId);
											closeMenu();
										}}
										disabled={!hasInactive}
									>
										Close inactive tabs
									</MenuItem>
									<MenuItem
										onClick={() => {
											closeAll();
											closeMenu();
										}}
										isDestructive
									>
										Close all tabs
									</MenuItem>
								</MenuGroup>
							</>
						);
					}}
				</DropdownMenu>
			)}
		</div>
	);
}
