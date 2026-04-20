import React from 'react';
import { ResizableBox } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

const ActivityPanel = () => {
	const visiblePanel = useSelect(
		(select) => select('wpgraphql-ide/activity-bar').visiblePanel(),
		[]
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
			<div className="wpgraphql-ide-plugin">
				{PluginContent ? <PluginContent /> : null}
			</div>
		</ResizableBox>
	);
};

export default ActivityPanel;
