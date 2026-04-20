import { Button, Tooltip } from '@wordpress/components';
import React from 'react';

export function ActivityBarPanels({ pluginContext, handlePluginClick }) {
	return (
		<div className="wpgraphql-ide-sidebar-section wpgraphql-ide-activity-bar-plugins">
			{pluginContext?.plugins.map((plugin, index) => {
				const isVisible = plugin === pluginContext.visiblePlugin;
				const label = `${isVisible ? 'Hide' : 'Show'} ${plugin.title}`;
				const PluginIcon = plugin.icon;
				return (
					<Tooltip key={plugin.title} text={label}>
						<Button
							variant="tertiary"
							className={isVisible ? 'active' : ''}
							onClick={handlePluginClick}
							data-index={index}
							aria-label={label}
						>
							<PluginIcon aria-hidden="true" />
						</Button>
					</Tooltip>
				);
			})}
		</div>
	);
}
