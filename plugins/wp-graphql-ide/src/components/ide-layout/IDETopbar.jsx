import React from 'react';
import { Button, Tooltip } from '@wordpress/components';
import { Icon, close, edit, sidebar, update } from '@wordpress/icons';

/**
 * Global top bar — sidebar toggle, schema refetch, registered topbar
 * actions, and an optional close button when the IDE is mounted in
 * drawer mode.
 *
 * @param {Object}      props
 * @param {Object|null} props.visiblePanel    - Currently visible activity panel descriptor (or null).
 * @param {Function}    props.onSidebarToggle - Click handler for the sidebar toggle.
 * @param {boolean}     props.isSchemaLoading - Whether a schema fetch is in flight.
 * @param {Function}    props.onRefetchSchema - Async handler called when the user clicks the refresh button.
 * @param {Array}       props.topbarActions   - Extension-registered topbar action descriptors.
 * @param {Function}    [props.onClose]       - Close handler for drawer mode (omitted on the dedicated page).
 */
export function IDETopbar({
	visiblePanel,
	onSidebarToggle,
	isSchemaLoading,
	onRefetchSchema,
	topbarActions,
	onClose,
}) {
	return (
		<div className="wpgraphql-ide-topbar">
			<div className="wpgraphql-ide-topbar-left">
				<Tooltip
					placement="right"
					text={visiblePanel ? 'Collapse sidebar' : 'Expand sidebar'}
				>
					<Button
						onClick={onSidebarToggle}
						aria-label={
							visiblePanel ? 'Collapse sidebar' : 'Expand sidebar'
						}
						size="compact"
						className={`wpgraphql-ide-topbar-btn${visiblePanel ? ' is-active' : ''}`}
					>
						<Icon icon={sidebar} />
					</Button>
				</Tooltip>
			</div>
			<div className="wpgraphql-ide-topbar-center">
				<span className="wpgraphql-ide-topbar-title">WPGraphQL</span>
			</div>
			<div className="wpgraphql-ide-topbar-right">
				<Tooltip text="Re-fetch schema">
					<Button
						onClick={onRefetchSchema}
						disabled={isSchemaLoading}
						aria-label="Re-fetch schema"
						size="compact"
						className={`wpgraphql-ide-topbar-btn${isSchemaLoading ? ' is-loading' : ''}`}
					>
						<Icon icon={update} />
					</Button>
				</Tooltip>
				{topbarActions.length > 0 && (
					<>
						<div className="wpgraphql-ide-topbar-sep" />
						{topbarActions.map((action) => (
							<Tooltip key={action.name} text={action.title}>
								<Button
									onClick={() =>
										window.WPGraphQLIDE?.openWorkspaceTab(
											action.tabType,
											{
												id: action.tabId,
												title: action.title,
											}
										)
									}
									aria-label={action.title}
									size="compact"
									className="wpgraphql-ide-topbar-btn"
								>
									{action.icon ? (
										<action.icon />
									) : (
										<Icon icon={edit} />
									)}
								</Button>
							</Tooltip>
						))}
					</>
				)}
				{onClose && (
					<>
						<div className="wpgraphql-ide-topbar-sep" />
						<Tooltip text="Close">
							<Button
								onClick={onClose}
								aria-label="Close"
								size="compact"
								className="wpgraphql-ide-topbar-btn"
							>
								<Icon icon={close} />
							</Button>
						</Tooltip>
					</>
				)}
			</div>
		</div>
	);
}
