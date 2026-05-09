import React from 'react';
import { Button, Tooltip } from '@wordpress/components';
import { Icon, close, edit, sidebar } from '@wordpress/icons';

/**
 * Global top bar — sidebar toggle, registered topbar actions, and an
 * optional close button when the IDE is mounted in drawer mode. The
 * schema-refetch button is registered through the same `registerTopbarAction`
 * registry as Settings; renders alongside any other extension actions.
 *
 * @param {Object}      props
 * @param {Object|null} props.visiblePanel    - Currently visible activity panel descriptor (or null).
 * @param {Function}    props.onSidebarToggle - Click handler for the sidebar toggle.
 * @param {Array}       props.topbarActions   - Topbar action descriptors (built-ins + extensions).
 * @param {Object}      props.topbarCtx       - Context passed to action callables (refetchSchema, isSchemaLoading, etc.).
 * @param {string}      [props.signInUrl]     - When set, renders a "Sign in" link at the right of the topbar (used by the public-endpoint render for anonymous visitors).
 * @param {Function}    [props.onClose]       - Close handler for drawer mode (omitted on the dedicated page).
 */
export function IDETopbar({
	visiblePanel,
	onSidebarToggle,
	topbarActions,
	topbarCtx,
	signInUrl,
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
				{topbarActions.map((action) => {
					const handler = action.onClick
						? () => action.onClick(topbarCtx)
						: () =>
								window.WPGraphQLIDE?.openWorkspaceTab(
									action.tabType,
									{
										id: action.tabId,
										title: action.title,
									}
								);
					const disabled =
						typeof action.isDisabled === 'function'
							? action.isDisabled(topbarCtx)
							: false;
					const extraClass =
						typeof action.className === 'function'
							? action.className(topbarCtx)
							: '';
					return (
						<Tooltip key={action.name} text={action.title}>
							<Button
								onClick={handler}
								disabled={disabled}
								aria-label={action.title}
								size="compact"
								className={`wpgraphql-ide-topbar-btn${extraClass ? ` ${extraClass}` : ''}`}
							>
								{action.icon ? (
									<action.icon />
								) : (
									<Icon icon={edit} />
								)}
							</Button>
						</Tooltip>
					);
				})}
				{signInUrl && (
					<>
						<div className="wpgraphql-ide-topbar-sep" />
						<Tooltip text="Sign in to your WordPress account for the full IDE">
							<Button
								variant="primary"
								size="compact"
								href={signInUrl}
								className="wpgraphql-ide-topbar-signin"
							>
								Sign in
							</Button>
						</Tooltip>
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
