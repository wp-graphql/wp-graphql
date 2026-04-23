import React, { useRef, useState, useLayoutEffect, useCallback } from 'react';
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
 * overflow dropdown on the right. Double-click a tab title to rename it.
 *
 * @param {Object}   props
 * @param {Array}    props.tabs     Array of `{ id, title }` tab descriptors.
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
	const [cutoff, setCutoff] = useState(tabs.length);
	const [editingId, setEditingId] = useState(null);
	const [editValue, setEditValue] = useState('');

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
		<div ref={containerRef} className="wpgraphql-ide-tab-bar">
			{visibleTabs.map((tab) => {
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
						className={`wpgraphql-ide-tab${isActive ? ' is-active' : ''}`}
						onClick={() => onSwitch(String(tab.id))}
						onDoubleClick={() => {
							setEditingId(tab.id);
							setEditValue(tab.title || 'Untitled');
						}}
					>
						{editingId === tab.id ? (
							<input
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
								{tab.title || 'Untitled'}
							</span>
						)}
						{tabs.length > 1 && (
							<span
								className="wpgraphql-ide-tab-close"
								role="button"
								tabIndex={-1}
								onClick={(e) => {
									e.stopPropagation();
									onClose(String(tab.id));
								}}
								onKeyDown={(e) => {
									if (e.key === 'Enter') {
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
										tabs.length > 1 ? (
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
										) : null
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
		</div>
	);
}
