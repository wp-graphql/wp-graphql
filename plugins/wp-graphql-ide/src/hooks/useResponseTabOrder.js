import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { readDevicePreference, setPreference } from '../api/preferences';

/**
 * Response-tab ordering with drag-to-reorder and per-device persistence.
 * Mirrors `usePanelOrder` (the activity-bar reorder hook) but persists
 * under the `response_tab_order` device preference and emits drag-pos
 * indicators tailored for a horizontal tab strip ('left' / 'right').
 *
 * Returns the input tabs reordered to match the user's saved order, with
 * any tabs missing from that order appended at the end so a newly
 * registered extension never disappears just because a stale pref
 * doesn't mention it.
 *
 * @param {Array<{ name: string }>} tabs Source list of tab descriptors.
 *
 * @return {{
 *   orderedTabs: Array,
 *   dragOverTab: { name: string, pos: 'left' | 'right' } | null,
 *   onDragStart: (name: string) => (e: DragEvent) => void,
 *   onDragOver: (name: string) => (e: DragEvent) => void,
 *   onDragLeave: () => void,
 *   onDrop: (name: string) => (e: DragEvent) => void,
 *   onDragEnd: () => void,
 * }}
 */
export function useResponseTabOrder(tabs) {
	const [tabOrder, setTabOrder] = useState([]);
	const [dragOverTab, setDragOverTab] = useState(null);
	const dragSrcTab = useRef(null);

	useEffect(() => {
		const saved = readDevicePreference('response_tab_order');
		if (Array.isArray(saved) && saved.length > 0) {
			setTabOrder(saved);
		}
	}, []);

	const orderedTabs = useMemo(() => {
		if (tabOrder.length === 0) {
			return tabs;
		}
		const ordered = [];
		for (const name of tabOrder) {
			const tab = tabs.find((t) => t.name === name);
			if (tab) {
				ordered.push(tab);
			}
		}
		for (const tab of tabs) {
			if (!tabOrder.includes(tab.name)) {
				ordered.push(tab);
			}
		}
		return ordered;
	}, [tabs, tabOrder]);

	const handleDrop = useCallback(
		(targetName, pos) => {
			const srcName = dragSrcTab.current;
			if (!srcName || srcName === targetName) {
				setDragOverTab(null);
				return;
			}
			const names = orderedTabs.map((t) => t.name);
			const srcIdx = names.indexOf(srcName);
			if (srcIdx === -1) {
				setDragOverTab(null);
				return;
			}
			names.splice(srcIdx, 1);
			let tgtIdx = names.indexOf(targetName);
			if (tgtIdx === -1) {
				setDragOverTab(null);
				return;
			}
			if (pos === 'right') {
				tgtIdx += 1;
			}
			names.splice(tgtIdx, 0, srcName);
			setTabOrder(names);
			setDragOverTab(null);

			setPreference('response_tab_order', names);
		},
		[orderedTabs]
	);

	const onDragStart = useCallback(
		(name) => (e) => {
			dragSrcTab.current = name;
			e.dataTransfer.effectAllowed = 'move';
		},
		[]
	);

	const onDragOver = useCallback(
		(name) => (e) => {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
			const rect = e.currentTarget.getBoundingClientRect();
			const pos =
				e.clientX < rect.left + rect.width / 2 ? 'left' : 'right';
			setDragOverTab({ name, pos });
		},
		[]
	);

	const onDragLeave = useCallback(() => setDragOverTab(null), []);

	const onDrop = useCallback(
		(name) => (e) => {
			e.preventDefault();
			handleDrop(name, dragOverTab?.pos || 'right');
		},
		[handleDrop, dragOverTab]
	);

	const onDragEnd = useCallback(() => {
		dragSrcTab.current = null;
		setDragOverTab(null);
	}, []);

	return {
		orderedTabs,
		dragOverTab,
		onDragStart,
		onDragOver,
		onDragLeave,
		onDrop,
		onDragEnd,
	};
}
