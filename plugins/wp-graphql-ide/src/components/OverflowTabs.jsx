import React, {
	useRef,
	useState,
	useLayoutEffect,
	useCallback,
	useEffect,
} from 'react';
import { DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { Icon, chevronDown, chevronUp } from '@wordpress/icons';

const OVERFLOW_BTN_W = 40;

/**
 * Tab strip-only sub-component. Owning the cutoff state here (instead of in
 * <OverflowTabs>) keeps the parent's content area from re-rendering every
 * time ResizeObserver fires during a drag — only the strip rebuilds.
 *
 * Reorder D&D handlers are optional; when omitted, the strip renders the
 * tabs as plain non-draggable buttons. When present they wire native
 * HTML5 drag-and-drop on each tab so the user can sort the strip; the
 * surrounding hook persists the order.
 *
 * @param {Object}      props
 * @param {Array}       props.tabs          `{ name, title }` descriptors.
 * @param {string}      props.activeName    Currently selected tab name.
 * @param {Function}    props.onSelect      Called with a tab name on click.
 * @param {Object|null} [props.dragOverTab] `{ name, pos }` when dragging.
 * @param {Function}    [props.onDragStart] Factory `(name) => (e) => void`.
 * @param {Function}    [props.onDragOver]  Factory `(name) => (e) => void`.
 * @param {Function}    [props.onDragLeave] `() => void`.
 * @param {Function}    [props.onDrop]      Factory `(name) => (e) => void`.
 * @param {Function}    [props.onDragEnd]   `() => void`.
 *
 * @return {JSX.Element}
 */
function TabStrip({
	tabs,
	activeName,
	onSelect,
	dragOverTab,
	onDragStart,
	onDragOver,
	onDragLeave,
	onDrop,
	onDragEnd,
}) {
	const containerRef = useRef(null);
	const tabRefs = useRef({});
	const [draggingName, setDraggingName] = useState(null);
	const reorderable = typeof onDragStart === 'function';
	// Per-tab measured widths persist across renders. Without this, tabs
	// that fold into the overflow dropdown unmount, lose their refs, and
	// can't be re-measured when the panel grows back — which would make the
	// strip flicker as the cutoff bounced on continuous resize.
	const widthCacheRef = useRef({});
	const rafRef = useRef(0);
	const [cutoff, setCutoff] = useState(tabs.length);

	const recalculate = useCallback(() => {
		const container = containerRef.current;
		if (!container) {
			return;
		}
		const available = container.clientWidth;

		for (const name in tabRefs.current) {
			const el = tabRefs.current[name];
			if (el) {
				widthCacheRef.current[name] = el.offsetWidth;
			}
		}

		const widths = tabs.map((t) => widthCacheRef.current[t.name] || 100);
		const total = widths.reduce((a, b) => a + b, 0);

		if (total <= available) {
			setCutoff(tabs.length);
			return;
		}

		let sum = OVERFLOW_BTN_W;
		let count = 0;
		for (let i = 0; i < tabs.length; i++) {
			if (sum + widths[i] > available) {
				break;
			}
			sum += widths[i];
			count++;
		}
		setCutoff(Math.max(1, count));
	}, [tabs]);

	// Coalesce resize-driven recalculations to one per animation frame so
	// the tab strip doesn't re-render at the rate the OS hands us pointer
	// events during a drag.
	const scheduleRecalculate = useCallback(() => {
		if (rafRef.current) {
			return;
		}
		rafRef.current = window.requestAnimationFrame(() => {
			rafRef.current = 0;
			recalculate();
		});
	}, [recalculate]);

	useLayoutEffect(() => {
		recalculate();
	}, [recalculate]);

	useEffect(() => {
		const container = containerRef.current;
		if (!container) {
			return undefined;
		}
		const obs = new window.ResizeObserver(scheduleRecalculate);
		obs.observe(container);
		return () => {
			obs.disconnect();
			if (rafRef.current) {
				window.cancelAnimationFrame(rafRef.current);
				rafRef.current = 0;
			}
		};
	}, [scheduleRecalculate]);

	const ordered = [...tabs];
	const activeIdx = ordered.findIndex((t) => t.name === activeName);
	const safeCutoff = Math.min(cutoff, ordered.length);
	if (activeIdx >= safeCutoff && safeCutoff > 0) {
		const [activeTab] = ordered.splice(activeIdx, 1);
		ordered.splice(safeCutoff - 1, 0, activeTab);
	}

	const visible = ordered.slice(0, safeCutoff);
	const overflow = ordered.slice(safeCutoff);

	return (
		<div
			ref={containerRef}
			className="components-tab-panel__tabs"
			role="tablist"
		>
			{visible.map((tab) => {
				const isActive = tab.name === activeName;
				const isDragOver = dragOverTab?.name === tab.name;
				const isDragging = draggingName === tab.name;
				const classes = [
					'components-tab-panel__tabs-item',
					isActive && 'is-active',
					isDragOver && `is-drag-${dragOverTab.pos}`,
					isDragging && 'is-dragging',
				]
					.filter(Boolean)
					.join(' ');
				if (!reorderable) {
					return (
						<button
							key={tab.name}
							ref={(el) => {
								if (el) {
									tabRefs.current[tab.name] = el;
								}
							}}
							type="button"
							role="tab"
							aria-selected={isActive}
							tabIndex={isActive ? 0 : -1}
							className={classes}
							onClick={() => onSelect(tab.name)}
						>
							{tab.title}
						</button>
					);
				}
				const startDrag = onDragStart(tab.name);
				return (
					<button
						key={tab.name}
						ref={(el) => {
							if (el) {
								tabRefs.current[tab.name] = el;
							}
						}}
						type="button"
						role="tab"
						aria-selected={isActive}
						tabIndex={isActive ? 0 : -1}
						className={classes}
						onClick={() => onSelect(tab.name)}
						draggable
						onDragStart={(e) => {
							// `setData` keeps Firefox and Safari from
							// cancelling the drag — some engines treat a
							// dragstart that never calls setData as a
							// signal to abort. `text/plain` is the
							// universally-accepted mime; the value is
							// informational for any drop target that
							// might want to read it.
							if (e.dataTransfer) {
								try {
									e.dataTransfer.setData(
										'text/plain',
										tab.name
									);
								} catch {
									// Some test environments reject
									// setData on synthetic events; the
									// drag still works.
								}
							}
							setDraggingName(tab.name);
							startDrag(e);
						}}
						onDragOver={onDragOver(tab.name)}
						onDragLeave={onDragLeave}
						onDrop={onDrop(tab.name)}
						onDragEnd={(e) => {
							setDraggingName(null);
							onDragEnd(e);
						}}
					>
						{tab.title}
					</button>
				);
			})}

			{overflow.length > 0 && (
				<DropdownMenu
					icon={null}
					label="More tabs"
					toggleProps={{
						children: `+${overflow.length}`,
						className: 'wpgraphql-ide-tabs-overflow',
					}}
				>
					{({ onClose }) => (
						<MenuGroup>
							{overflow.map((tab) => (
								<MenuItem
									key={tab.name}
									onClick={() => {
										onSelect(tab.name);
										onClose();
									}}
								>
									{tab.title}
								</MenuItem>
							))}
						</MenuGroup>
					)}
				</DropdownMenu>
			)}
		</div>
	);
}

/**
 * Tab strip that mirrors @wordpress/components TabPanel's class names + render
 * shape, but folds tabs that don't fit into a trailing `+N` dropdown instead
 * of letting flex squeeze them. The active tab is always pulled into the
 * visible range so the user never has to open the overflow to see what they
 * have selected.
 *
 * Re-mount via React `key` to re-apply `initialTabName` (matches how the
 * surrounding response pane already drives programmatic tab navigation).
 *
 * Pass `reorder` (the return value from `useResponseTabOrder` or a
 * shape-compatible object) to enable drag-to-reorder on the strip. Omit
 * it to keep tabs static.
 *
 * @param {Object}   props
 * @param {Array}    props.tabs                `{ name, title }` descriptors, ordered.
 * @param {string}   props.initialTabName      Tab that should be active on mount. Ignored when `activeTabName` is controlled.
 * @param {string}   props.className           Class added next to `.components-tab-panel`.
 * @param {Object}   [props.reorder]           `{ dragOverTab, onDragStart, … }` D&D bundle.
 * @param {boolean}  [props.collapsed]         Render only the tab bar; content area is hidden.
 * @param {Function} [props.onCollapse]        Click handler for the trailing chevron when expanded.
 * @param {Function} [props.onExpand]          Called with the clicked tab name when user clicks a tab while collapsed (or hits the chevron).
 * @param {string}   [props.activeTabName]     Controlled active tab — pair with `onActiveTabChange` to hoist state.
 * @param {Function} [props.onActiveTabChange] Called with the new active tab name.
 * @param {Function} props.children            Render-prop receiving the active tab.
 *
 * @return {JSX.Element|null}
 */
export function OverflowTabs({
	tabs,
	initialTabName,
	className = '',
	reorder,
	children,
	collapsed = false,
	onCollapse,
	onExpand,
	activeTabName,
	onActiveTabChange,
}) {
	const [internalActive, setInternalActive] = useState(
		initialTabName || tabs[0]?.name
	);

	if (tabs.length === 0) {
		return null;
	}

	const isControlled = typeof activeTabName === 'string';
	const currentActive = isControlled ? activeTabName : internalActive;

	const setActive = (name) => {
		if (!isControlled) {
			setInternalActive(name);
		}
		if (typeof onActiveTabChange === 'function') {
			onActiveTabChange(name);
		}
	};

	const handleSelect = (name) => {
		setActive(name);
		// Clicking a tab in collapsed mode is "expand + switch". The
		// chevron alone isn't enough — users hitting a tab label expect
		// it to do something visible.
		if (collapsed && typeof onExpand === 'function') {
			onExpand(name);
		}
	};

	const effectiveActive =
		tabs.find((t) => t.name === currentActive)?.name || tabs[0].name;
	const activeTab = tabs.find((t) => t.name === effectiveActive);

	const showChevron = typeof onCollapse === 'function';

	// Collapsed state mirrors the tab strip but with no content area —
	// all tab labels are visible, the active one prominent, the rest
	// subdued. Clicking any tab expands AND switches to it; clicking
	// the trailing chevron expands with the previously-active tab
	// restored.
	if (collapsed) {
		const handleExpandTo = (name) => {
			if (typeof onExpand === 'function') {
				onExpand(name);
			}
		};
		return (
			<div
				className={`components-tab-panel ${className} is-collapsed`.trim()}
			>
				<div className="wpgraphql-ide-tab-collapsed-handle">
					{tabs.map((tab) => {
						const isActive = tab.name === effectiveActive;
						return (
							<button
								key={tab.name}
								type="button"
								className={`wpgraphql-ide-tab-collapsed-tab${isActive ? ' is-active' : ''}`}
								aria-pressed={isActive}
								onClick={() => handleExpandTo(tab.name)}
							>
								{tab.title}
							</button>
						);
					})}
					<button
						type="button"
						className="wpgraphql-ide-tab-collapsed-chevron"
						aria-label={`Expand ${activeTab?.title || 'panel'}`}
						aria-expanded={false}
						onClick={() => handleExpandTo(effectiveActive)}
					>
						<Icon icon={chevronUp} size={18} />
					</button>
				</div>
			</div>
		);
	}

	return (
		<div className={`components-tab-panel ${className}`.trim()}>
			<div className="wpgraphql-ide-tab-strip-row">
				<TabStrip
					tabs={tabs}
					activeName={effectiveActive}
					onSelect={handleSelect}
					dragOverTab={reorder?.dragOverTab}
					onDragStart={reorder?.onDragStart}
					onDragOver={reorder?.onDragOver}
					onDragLeave={reorder?.onDragLeave}
					onDrop={reorder?.onDrop}
					onDragEnd={reorder?.onDragEnd}
				/>
				{showChevron && (
					<button
						type="button"
						className="wpgraphql-ide-tab-collapse-btn"
						aria-label="Collapse panel"
						aria-expanded
						onClick={onCollapse}
					>
						<Icon icon={chevronDown} size={18} />
					</button>
				)}
			</div>
			<div className="components-tab-panel__tab-content">
				{activeTab && children(activeTab)}
			</div>
		</div>
	);
}
