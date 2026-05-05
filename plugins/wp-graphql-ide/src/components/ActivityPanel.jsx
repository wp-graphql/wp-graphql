import React, { useState } from 'react';
import { Button, ResizableBox } from '@wordpress/components';
import { Icon, close } from '@wordpress/icons';
import { useSelect, useDispatch } from '@wordpress/data';
import { getStorageItem, setStorageItem } from '../utils/storage';

// Persist panel width across open/close cycles via window.localStorage.
function getPersistedWidth() {
	const w = parseInt(getStorageItem('wpgraphql_ide_panel_width'), 10);
	return w > 0 ? w : 300;
}

const ActivityPanel = () => {
	const [panelWidth, setPanelWidth] = useState(getPersistedWidth);

	const visiblePanel = useSelect(
		(select) => select('wpgraphql-ide/activity-bar').visiblePanel(),
		[]
	);

	const { toggleActivityPanelVisibility } = useDispatch(
		'wpgraphql-ide/activity-bar'
	);

	if (!visiblePanel) {
		return null;
	}

	const PluginContent = visiblePanel.content;

	return (
		<ResizableBox
			size={{ width: panelWidth, height: '100%' }}
			minWidth={200}
			maxWidth={600}
			enable={{
				top: false,
				right: true,
				bottom: false,
				left: false,
			}}
			onResizeStop={(e, d, elt) => {
				const w = elt.offsetWidth;
				setPanelWidth(w);
				setStorageItem('wpgraphql_ide_panel_width', w);
			}}
			className="wpgraphql-ide-activity-panel"
		>
			<div className="wpgraphql-ide-panel-header">
				<span className="wpgraphql-ide-panel-title">
					{visiblePanel.title}
				</span>
				{visiblePanel.headerAction && <visiblePanel.headerAction />}
				<div className="wpgraphql-ide-panel-header-spacer" />
				<Button
					className="wpgraphql-ide-panel-close"
					onClick={() =>
						toggleActivityPanelVisibility(visiblePanel.name)
					}
					aria-label={`Close ${visiblePanel.title} panel`}
					size="small"
				>
					<Icon icon={close} size={20} />
				</Button>
			</div>
			<div className="wpgraphql-ide-plugin">
				{PluginContent ? <PluginContent /> : null}
			</div>
		</ResizableBox>
	);
};

export default ActivityPanel;
