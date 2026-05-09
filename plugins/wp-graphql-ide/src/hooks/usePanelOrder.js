import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { readDevicePreference, setPreference } from '../api/preferences';

/**
 * Activity-bar panel ordering with drag-to-reorder and per-device
 * persistence. The initial order comes from the device pref bucket
 * (or the bootstrap's `WPGRAPHQL_IDE_DATA.panelOrder` as a fallback
 * while the server still injects it); subsequent reorders are saved
 * via `setPreference('panel_order', …)`.
 *
 * Returns the input panels reordered to match the user's saved order,
 * with any panels missing from that order appended at the end so a
 * newly registered panel never disappears just because a stale pref
 * doesn't mention it.
 *
 * @param {Array<{ name: string }>} panels Source list of activity panels.
 *
 * @return {{
 *   navPanels: Array,
 *   dragOverPanel: { name: string, pos: 'before' | 'after' } | null,
 *   onDragStart: (name: string) => (e: DragEvent) => void,
 *   onDragOver: (name: string) => (e: DragEvent) => void,
 *   onDragLeave: () => void,
 *   onDrop: (name: string) => (e: DragEvent) => void,
 *   onDragEnd: () => void,
 * }}
 */
export function usePanelOrder(panels) {
	const [panelOrder, setPanelOrder] = useState([]);
	const [dragOverPanel, setDragOverPanel] = useState(null);
	const dragSrcPanel = useRef(null);

	useEffect(() => {
		const fromDevice = readDevicePreference('panel_order');
		if (Array.isArray(fromDevice) && fromDevice.length > 0) {
			setPanelOrder(fromDevice);
			return;
		}
		const fromBootstrap =
			typeof window !== 'undefined' &&
			window.WPGRAPHQL_IDE_DATA?.panelOrder;
		if (Array.isArray(fromBootstrap) && fromBootstrap.length > 0) {
			setPanelOrder(fromBootstrap);
		}
	}, []);

	const navPanels = useMemo(() => {
		if (panelOrder.length === 0) {
			return panels;
		}
		const ordered = [];
		for (const name of panelOrder) {
			const panel = panels.find((p) => p.name === name);
			if (panel) {
				ordered.push(panel);
			}
		}
		for (const panel of panels) {
			if (!panelOrder.includes(panel.name)) {
				ordered.push(panel);
			}
		}
		return ordered;
	}, [panels, panelOrder]);

	const handleDrop = useCallback(
		(targetName, pos) => {
			const srcName = dragSrcPanel.current;
			if (!srcName || srcName === targetName) {
				setDragOverPanel(null);
				return;
			}
			const names = navPanels.map((p) => p.name);
			const srcIdx = names.indexOf(srcName);
			if (srcIdx === -1) {
				setDragOverPanel(null);
				return;
			}
			names.splice(srcIdx, 1);
			let tgtIdx = names.indexOf(targetName);
			if (tgtIdx === -1) {
				setDragOverPanel(null);
				return;
			}
			if (pos === 'after') {
				tgtIdx += 1;
			}
			names.splice(tgtIdx, 0, srcName);
			setPanelOrder(names);
			setDragOverPanel(null);

			setPreference('panel_order', names);
		},
		[navPanels]
	);

	const onDragStart = useCallback(
		(name) => (e) => {
			dragSrcPanel.current = name;
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
				e.clientY < rect.top + rect.height / 2 ? 'before' : 'after';
			setDragOverPanel({ name, pos });
		},
		[]
	);

	const onDragLeave = useCallback(() => setDragOverPanel(null), []);

	const onDrop = useCallback(
		(name) => (e) => {
			e.preventDefault();
			handleDrop(name, dragOverPanel?.pos || 'after');
		},
		[handleDrop, dragOverPanel]
	);

	const onDragEnd = useCallback(() => {
		dragSrcPanel.current = null;
		setDragOverPanel(null);
	}, []);

	return {
		navPanels,
		dragOverPanel,
		onDragStart,
		onDragOver,
		onDragLeave,
		onDrop,
		onDragEnd,
	};
}
