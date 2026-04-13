import { Tooltip, UnStyledButton } from '@graphiql/react';
import React from 'react';

export function ActivityBarPanels({ pluginContext, handlePluginClick }) {
	return (
		<div className="graphiql-sidebar-section graphiql-activity-bar-plugins">
			{pluginContext?.plugins.map((plugin, index) => {
				const isVisible = plugin === pluginContext.visiblePlugin;
				const label = `${isVisible ? 'Hide' : 'Show'} ${plugin.title}`;
				const Icon = plugin.icon;
				return (
					<Tooltip key={plugin.title} label={label}>
						<UnStyledButton
							type="button"
							className={isVisible ? 'active' : ''}
							onClick={handlePluginClick}
							data-index={index}
							aria-label={label}
						>
							<Icon aria-hidden="true" />
						</UnStyledButton>
					</Tooltip>
				);
			})}
		</div>
	);
}
