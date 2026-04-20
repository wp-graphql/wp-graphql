import React from 'react';

const ActivityPanel = ({
	firstRef,
	dragBarRef,
	PluginContent,
	pluginContext,
}) => {
	return (
		<>
			<div
				ref={firstRef}
				style={{
					// Make sure the container shrinks when containing long
					// non-breaking texts
					minWidth: '200px',
				}}
				className="wpgraphql-ide-activity-panel"
			>
				<div className="wpgraphql-ide-plugin">
					{PluginContent ? <PluginContent /> : null}
				</div>
			</div>
			{pluginContext?.visiblePlugin && (
				<div
					className="wpgraphql-ide-horizontal-drag-bar"
					ref={dragBarRef}
				/>
			)}
		</>
	);
};

export default ActivityPanel;
