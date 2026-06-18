import React from 'react';
import { Button, Tooltip } from '@wordpress/components';
import { Icon, backup, edit, file, help, search } from '@wordpress/icons';

const PANEL_ICONS = {
	'saved-queries': file,
	'docs-explorer': search,
	help,
	history: backup,
};

/**
 * Vertical activity bar with drag-to-reorder. Each registered global
 * panel renders as an icon button; clicking toggles its visibility,
 * dragging reorders. Drag handlers come from `usePanelOrder` so the
 * bar stays presentational.
 *
 * @param {Object}      props
 * @param {Array}       props.navPanels     - Activity panel descriptors in display order.
 * @param {Object|null} props.visiblePanel  - Currently active panel descriptor.
 * @param {Object|null} props.dragOverPanel - `{ name, pos }` when a panel is being dragged over, else null.
 * @param {Function}    props.onDragStart   - Drag-start handler factory `(name) => (event) => void`.
 * @param {Function}    props.onDragOver    - Drag-over handler factory.
 * @param {Function}    props.onDragLeave   - Drag-leave handler.
 * @param {Function}    props.onDrop        - Drop handler factory.
 * @param {Function}    props.onDragEnd     - Drag-end handler.
 * @param {Function}    props.onPanelClick  - Called with the clicked panel name.
 */
export function IDEActivityBar({
	navPanels,
	visiblePanel,
	dragOverPanel,
	onDragStart,
	onDragOver,
	onDragLeave,
	onDrop,
	onDragEnd,
	onPanelClick,
}) {
	return (
		<div className="wpgraphql-ide-activity-bar">
			{navPanels.map((panel) => (
				<Tooltip key={panel.name} text={panel.title} placement="right">
					<Button
						draggable
						onDragStart={onDragStart(panel.name)}
						onDragOver={onDragOver(panel.name)}
						onDragLeave={onDragLeave}
						onDrop={onDrop(panel.name)}
						onDragEnd={onDragEnd}
						onClick={() => onPanelClick(panel.name)}
						aria-label={panel.title}
						aria-pressed={visiblePanel?.name === panel.name}
						size="compact"
						className={`wpgraphql-ide-activity-btn${visiblePanel?.name === panel.name ? ' is-active' : ''}${dragOverPanel?.name === panel.name ? ` is-drag-${dragOverPanel.pos}` : ''}`}
					>
						{panel.icon ? (
							<panel.icon />
						) : (
							<Icon icon={PANEL_ICONS[panel.name] ?? edit} />
						)}
					</Button>
				</Tooltip>
			))}
		</div>
	);
}
