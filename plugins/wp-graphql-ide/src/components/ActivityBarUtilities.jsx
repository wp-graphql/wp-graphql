import {
	KeyboardShortcutIcon,
	ReloadIcon,
	SettingsIcon,
	Tooltip,
	UnStyledButton,
} from '@graphiql/react';
import React from 'react';

export const ActivityBarUtilities = ({
	schemaContext,
	handleRefetchSchema,
	handleShowDialog,
}) => {
	return (
		<div className="graphiql-sidebar-section graphiql-activity-bar-utilities">
			<Tooltip label="Re-fetch GraphQL schema">
				<UnStyledButton
					type="button"
					disabled={schemaContext.isFetching}
					onClick={handleRefetchSchema}
					aria-label="Re-fetch GraphQL schema"
				>
					<ReloadIcon
						className={
							schemaContext.isFetching ? 'graphiql-spin' : ''
						}
						aria-hidden="true"
					/>
				</UnStyledButton>
			</Tooltip>
			<Tooltip label="Open short keys dialog">
				<UnStyledButton
					type="button"
					data-value="short-keys"
					onClick={handleShowDialog}
					aria-label="Open short keys dialog"
				>
					<KeyboardShortcutIcon aria-hidden="true" />
				</UnStyledButton>
			</Tooltip>
			<Tooltip label="Open settings dialog">
				<UnStyledButton
					type="button"
					data-value="settings"
					onClick={handleShowDialog}
					aria-label="Open settings dialog"
				>
					<SettingsIcon aria-hidden="true" />
				</UnStyledButton>
			</Tooltip>
		</div>
	);
};
