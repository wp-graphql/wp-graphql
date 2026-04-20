import { Button, Tooltip } from '@wordpress/components';
import { Icon, update, keyboard, cog } from '@wordpress/icons';
import React from 'react';

export const ActivityBarUtilities = ({
	schemaContext,
	handleRefetchSchema,
	handleShowDialog,
}) => {
	return (
		<div className="wpgraphql-ide-sidebar-section wpgraphql-ide-activity-bar-utilities">
			<Tooltip text="Re-fetch GraphQL schema" placement="right">
				<Button
					variant="tertiary"
					disabled={schemaContext.isFetching}
					onClick={handleRefetchSchema}
					aria-label="Re-fetch GraphQL schema"
				>
					<Icon
						icon={update}
						className={
							schemaContext.isFetching ? 'wpgraphql-ide-spin' : ''
						}
						aria-hidden="true"
					/>
				</Button>
			</Tooltip>
			<Tooltip text="Open short keys dialog" placement="right">
				<Button
					variant="tertiary"
					data-value="short-keys"
					onClick={handleShowDialog}
					aria-label="Open short keys dialog"
				>
					<Icon icon={keyboard} aria-hidden="true" />
				</Button>
			</Tooltip>
			<Tooltip text="Open settings dialog" placement="right">
				<Button
					variant="tertiary"
					data-value="settings"
					onClick={handleShowDialog}
					aria-label="Open settings dialog"
				>
					<Icon icon={cog} aria-hidden="true" />
				</Button>
			</Tooltip>
		</div>
	);
};
