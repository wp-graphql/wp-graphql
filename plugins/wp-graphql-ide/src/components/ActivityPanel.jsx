import React from 'react';
import { Button, ResizableBox } from '@wordpress/components';
import { Icon, close } from '@wordpress/icons';
import { useSelect, useDispatch } from '@wordpress/data';

const ActivityPanel = () => {
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
			size={{ width: 300, height: 'auto' }}
			minWidth={200}
			maxWidth={600}
			enable={{
				top: false,
				right: true,
				bottom: false,
				left: false,
			}}
			className="wpgraphql-ide-activity-panel"
		>
			<div className="wpgraphql-ide-panel-header">
				<span className="wpgraphql-ide-panel-title">
					{visiblePanel.title}
				</span>
				<Button
					className="wpgraphql-ide-panel-close"
					onClick={() =>
						toggleActivityPanelVisibility(visiblePanel.name)
					}
					aria-label="Close panel"
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
