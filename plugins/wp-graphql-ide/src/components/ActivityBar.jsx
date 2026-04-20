import React from 'react';
import { ActivityBarPanels } from './ActivityBarPanels';
import { ActivityBarUtilities } from './ActivityBarUtilities';

export const ActivityBar = ({
	schemaContext,
	handleRefetchSchema,
	handleShowDialog,
}) => {
	return (
		<div className="wpgraphql-ide-sidebar wpgraphql-ide-activity-bar">
			<ActivityBarPanels />
			<ActivityBarUtilities
				handleShowDialog={handleShowDialog}
				handleRefetchSchema={handleRefetchSchema}
				schemaContext={schemaContext}
			/>
		</div>
	);
};
