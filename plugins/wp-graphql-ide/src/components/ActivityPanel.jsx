import React from 'react';

const ActivityPanel = ( {
	firstRef,
	dragBarRef,
	PluginContent,
	pluginContext,
} ) => {
	return (
		<>
			<div
				ref={ firstRef }
				style={ {
					// Make sure the container shrinks when containing long
					// non-breaking texts
					minWidth: '200px',
				} }
				className="graphiql-activity-panel"
			>
				<div className="graphiql-plugin">
					{ PluginContent ? <PluginContent /> : null }
				</div>
			</div>
			{ pluginContext?.visiblePlugin && (
				<div
					className="graphiql-horizontal-drag-bar"
					ref={ dragBarRef }
				/>
			) }
		</>
	);
};

export default ActivityPanel;
