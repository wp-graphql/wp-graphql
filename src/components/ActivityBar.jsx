import React from 'react';
import { ActivityBarUtilities } from './ActivityBarUtilities';
import { ActivityBarPanels } from './ActivityBarPanels';

export const ActivityBar = ( {
	pluginContext,
	handlePluginClick,
	schemaContext,
	handleRefetchSchema,
	handleShowDialog,
} ) => {
	return (
		<div className="graphiql-sidebar graphiql-activity-bar">
			<ActivityBarPanels
				pluginContext={ pluginContext }
				handlePluginClick={ handlePluginClick }
			/>
			<ActivityBarUtilities
				handleShowDialog={ handleShowDialog }
				handleRefetchSchema={ handleRefetchSchema }
				schemaContext={ schemaContext }
			/>
		</div>
	);
};
