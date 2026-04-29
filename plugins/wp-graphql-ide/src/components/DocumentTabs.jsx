import React, {
	useRef,
	useState,
	useLayoutEffect,
	useCallback,
	useEffect,
} from 'react';
import {
	DropdownMenu,
	MenuGroup,
	MenuItem,
	Button,
	Tooltip,
} from '@wordpress/components';
import { Icon, plus } from '@wordpress/icons';

const ADD_BTN_W = 32;
const OVERFLOW_BTN_W = 32;

/**
 * IDE-style tab strip for open documents.
 *
 * Visible tabs are rendered inline; any tabs that don't fit fold into an
 * overflow dropdown on the right. Renames are triggered from the sidebar.
 *
 * @param {Object}   props
 * @param {Array}    props.tabs     Array of `{ id, title, dirty }` tab descriptors.
 * @param {string}   props.activeId Id of the currently active tab.
 * @param {Function} props.onSwitch Called with the clicked tab id.
 * @param {Function} props.onClose  Called with the id of the tab to close.
 * @param {Function} props.onCreate Called when the "+" button is clicked.
 * @param {Function} props.onRename Called with `(id, title)` after inline rename.
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
}) {
	const containerRef = useRef(null);
	const tabRefs = useRef({});
	const menuRef = useRef(null);
	const [cutoff, setCutoff] = useState(tabs.length);
	const [editingId, setEditingId] = useState(null);
	const [editValue, setEditValue] = useState('');
	// Right-click context menu: { tabId, x, y } | null. Position is in
	// viewport coords so the menu can render outside the tab bar.
	const [contextMenu, setContextMenu] = useState(null);

	const recalculate = useCallback(() => {
		const container = containerRef.current;
		if (!container) {
			return;
		}
		const available = container.clientWidth;

		// First pass — do all tabs fit with just the "+" button?
		let sum = ADD_BTN_W;
		for (const tab of tabs) {
			const el = tabRefs.current[tab.id];
			sum += el ? el.offsetWidth : 140;
		}
		if (sum <= available) {
			setCutoff(tabs.length);
			return;
		}

		// Second pass — reserve space for the overflow button too.
		sum = ADD_BTN_W + OVERFLOW_BTN_W;
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

	// Dismiss the context menu on outside click or Escape.
	useEffect(() => {
		if (!contextMenu) {
			return undefined;
		}
		const handleDown = (e) => {
			if (menuRef.current && !menuRef.current.contains(e.target)) {
				setContextMenu(null);
			}
		};
		const handleKey = (e) => {
			if (e.key === 'Escape') {
				setContextMenu(null);
			}
		};
		document.addEventListener('mousedown', handleDown);
		document.addEventListener('keydown', handleKey);
		return () => {
			document.removeEventListener('mousedown', handleDown);
			document.removeEventListener('keydown', handleKey);
		};
	}, [contextMenu]);

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

	const closeToRight = (anchorId) => {
		const anchorIdx = tabs.findIndex(
			(t) => String(t.id) === String(anchorId)
		);
		if (anchorIdx < 0) {
			return;
		}
		closeMany(tabs.slice(anchorIdx + 1).map((t) => t.id));
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
				const isUnsaved = String(tab.id).startsWith('temp-');
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
						className={`wpgraphql-ide-tab${isActive ? ' is-active' : ''}${isUnsaved ? ' is-unsaved' : ''}`}
						onClick={() => onSwitch(String(tab.id))}
						onContextMenu={(e) => {
							e.preventDefault();
							setContextMenu({
								tabId: String(tab.id),
								x: e.clientX,
								y: e.clientY,
							});
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
						{editingId === tab.id ? (
							<input
								aria-label="Rename document"
								className="wpgraphql-ide-tab-input"
								value={editValue}
								onChange={(e) => setEditValue(e.target.value)}
								onBlur={() => {
									if (editValue.trim()) {
										onRename(tab.id, editValue.trim());
									}
									setEditingId(null);
								}}
								onKeyDown={(e) => {
									if (e.key === 'Enter') {
										e.target.blur();
									}
									if (e.key === 'Escape') {
										setEditingId(null);
									}
								}}
								onClick={(e) => e.stopPropagation()}
								// eslint-disable-next-line jsx-a11y/no-autofocus
								autoFocus
							/>
						) : (
							<span className="wpgraphql-ide-tab-label">
								{tab.dirty && (
									<span className="wpgraphql-ide-tab-dirty" />
								)}
								{tab.title || 'Untitled'}
							</span>
						)}
						{editingId !== tab.id && (
							<span
								className="wpgraphql-ide-tab-close"
								role="button"
								tabIndex={0}
								onClick={(e) => {
									e.stopPropagation();
									onClose(String(tab.id));
								}}
								onKeyDown={(e) => {
									if (e.key === 'Enter' || e.key === ' ') {
										e.preventDefault();
										e.stopPropagation();
										onClose(String(tab.id));
									}
								}}
								aria-label={`Close ${tab.title || 'Untitled'}`}
							>
								&times;
							</span>
						)}
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

			{contextMenu &&
				(() => {
					const targetIdx = tabs.findIndex(
						(t) => String(t.id) === contextMenu.tabId
					);
					const hasRight =
						targetIdx >= 0 && targetIdx < tabs.length - 1;
					const hasOthers = tabs.length > 1;
					return (
						<div
							ref={menuRef}
							className="wpgraphql-ide-tab-context-menu"
							role="menu"
							style={{
								top: contextMenu.y,
								left: contextMenu.x,
							}}
						>
							<button
								type="button"
								role="menuitem"
								onClick={() => {
									onClose(contextMenu.tabId);
									setContextMenu(null);
								}}
							>
								Close
							</button>
							<button
								type="button"
								role="menuitem"
								disabled={!hasOthers}
								onClick={() => {
									closeOthers(contextMenu.tabId);
									setContextMenu(null);
								}}
							>
								Close others
							</button>
							<button
								type="button"
								role="menuitem"
								disabled={!hasRight}
								onClick={() => {
									closeToRight(contextMenu.tabId);
									setContextMenu(null);
								}}
							>
								Close to the right
							</button>
							<button
								type="button"
								role="menuitem"
								onClick={() => {
									closeAll();
									setContextMenu(null);
								}}
							>
								Close all
							</button>
						</div>
					);
				})()}
		</div>
	);
}
