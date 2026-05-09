import React, {
	useRef,
	useState,
	useLayoutEffect,
	useCallback,
	useEffect,
} from 'react';
import { DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';

const OVERFLOW_BTN_W = 40;

/**
 * Tab strip-only sub-component. Owning the cutoff state here (instead of in
 * <OverflowTabs>) keeps the parent's content area from re-rendering every
 * time ResizeObserver fires during a drag — only the strip rebuilds.
 *
 * @param {Object}   props
 * @param {Array}    props.tabs       `{ name, title }` descriptors.
 * @param {string}   props.activeName Currently selected tab name.
 * @param {Function} props.onSelect   Called with a tab name on click.
 *
 * @return {JSX.Element}
 */
function TabStrip({ tabs, activeName, onSelect }) {
	const containerRef = useRef(null);
	const tabRefs = useRef({});
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
						className={`components-tab-panel__tabs-item${isActive ? ' is-active' : ''}`}
						onClick={() => onSelect(tab.name)}
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
 * @param {Object}   props
 * @param {Array}    props.tabs           `{ name, title }` descriptors, ordered.
 * @param {string}   props.initialTabName Tab that should be active on mount.
 * @param {string}   props.className      Class added next to `.components-tab-panel`.
 * @param {Function} props.children       Render-prop receiving the active tab.
 *
 * @return {JSX.Element|null}
 */
export function OverflowTabs({
	tabs,
	initialTabName,
	className = '',
	children,
}) {
	const [activeName, setActiveName] = useState(
		initialTabName || tabs[0]?.name
	);

	if (tabs.length === 0) {
		return null;
	}

	const effectiveActive =
		tabs.find((t) => t.name === activeName)?.name || tabs[0].name;
	const activeTab = tabs.find((t) => t.name === effectiveActive);

	return (
		<div className={`components-tab-panel ${className}`.trim()}>
			<TabStrip
				tabs={tabs}
				activeName={effectiveActive}
				onSelect={setActiveName}
			/>
			<div className="components-tab-panel__tab-content">
				{activeTab && children(activeTab)}
			</div>
		</div>
	);
}
