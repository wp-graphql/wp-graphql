import React, { useRef, useState, useLayoutEffect, useCallback } from 'react';
import { DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';

const OVERFLOW_BTN_W = 40;

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
	const containerRef = useRef(null);
	const tabRefs = useRef({});
	const [cutoff, setCutoff] = useState(tabs.length);
	const [activeName, setActiveName] = useState(
		initialTabName || tabs[0]?.name
	);

	const recalculate = useCallback(() => {
		const container = containerRef.current;
		if (!container) {
			return;
		}
		const available = container.clientWidth;

		// First pass — do all tabs fit at full width with no overflow button?
		let sum = 0;
		for (const tab of tabs) {
			const el = tabRefs.current[tab.name];
			sum += el ? el.offsetWidth : 100;
		}
		if (sum <= available) {
			setCutoff(tabs.length);
			return;
		}

		// Second pass — reserve space for the "+N" button.
		sum = OVERFLOW_BTN_W;
		let count = 0;
		for (const tab of tabs) {
			const el = tabRefs.current[tab.name];
			const w = el ? el.offsetWidth : 100;
			if (sum + w > available) {
				break;
			}
			sum += w;
			count++;
		}
		setCutoff(Math.max(1, count));
	}, [tabs]);

	useLayoutEffect(() => {
		recalculate();
	}, [recalculate]);

	useLayoutEffect(() => {
		const container = containerRef.current;
		if (!container) {
			return;
		}
		const obs = new window.ResizeObserver(recalculate);
		obs.observe(container);
		return () => obs.disconnect();
	}, [recalculate]);

	if (tabs.length === 0) {
		return null;
	}

	// Derive the effective active name in case `tabs` shrunk and removed the
	// previously-active tab — never set state during render.
	const effectiveActive =
		tabs.find((t) => t.name === activeName)?.name || tabs[0].name;

	const ordered = [...tabs];
	const activeIdx = ordered.findIndex((t) => t.name === effectiveActive);
	const safeCutoff = Math.min(cutoff, ordered.length);
	if (activeIdx >= safeCutoff && safeCutoff > 0) {
		const [activeTab] = ordered.splice(activeIdx, 1);
		ordered.splice(safeCutoff - 1, 0, activeTab);
	}

	const visible = ordered.slice(0, safeCutoff);
	const overflow = ordered.slice(safeCutoff);
	const activeTab = tabs.find((t) => t.name === effectiveActive);

	return (
		<div className={`components-tab-panel ${className}`.trim()}>
			<div
				ref={containerRef}
				className="components-tab-panel__tabs"
				role="tablist"
			>
				{visible.map((tab) => {
					const isActive = tab.name === effectiveActive;
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
							onClick={() => setActiveName(tab.name)}
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
							size: 'compact',
						}}
					>
						{({ onClose }) => (
							<MenuGroup>
								{overflow.map((tab) => (
									<MenuItem
										key={tab.name}
										onClick={() => {
											setActiveName(tab.name);
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
			<div className="components-tab-panel__tab-content">
				{activeTab && children(activeTab)}
			</div>
		</div>
	);
}
