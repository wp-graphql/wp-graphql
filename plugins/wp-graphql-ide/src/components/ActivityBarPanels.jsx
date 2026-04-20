import { Button, Tooltip } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import React from 'react';

export function ActivityBarPanels() {
	const { panels, visiblePanel } = useSelect((select) => {
		const activityBar = select('wpgraphql-ide/activity-bar');
		return {
			panels: activityBar.activityPanels(),
			visiblePanel: activityBar.visiblePanel(),
		};
	}, []);

	const { toggleActivityPanelVisibility } = useDispatch(
		'wpgraphql-ide/activity-bar'
	);

	return (
		<div className="wpgraphql-ide-sidebar-section wpgraphql-ide-activity-bar-plugins">
			{panels.map((panel) => {
				const isVisible =
					visiblePanel && visiblePanel.name === panel.name;
				const label = `${isVisible ? 'Hide' : 'Show'} ${panel.title}`;
				const PanelIcon = panel.icon;
				return (
					<Tooltip key={panel.name} text={label}>
						<Button
							variant="tertiary"
							className={isVisible ? 'active' : ''}
							onClick={() =>
								toggleActivityPanelVisibility(panel.name)
							}
							aria-label={label}
						>
							{PanelIcon && <PanelIcon aria-hidden="true" />}
						</Button>
					</Tooltip>
				);
			})}
		</div>
	);
}
