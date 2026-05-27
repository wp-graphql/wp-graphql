import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Tooltip } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { Icon, close, edit, sidebar } from '@wordpress/icons';
import { endpointMode } from '../../bootstrap';

/**
 * Global top bar — sidebar toggle, registered topbar actions, and an
 * optional close button when the IDE is mounted in drawer mode. The
 * schema-refetch button is registered through the same `registerTopbarAction`
 * registry as Settings; renders alongside any other extension actions.
 *
 * Reads the registered topbar actions from the store directly and gates
 * them on `endpointMode` (public-endpoint mode hides extension actions
 * — the topbar there is a read-only surface). IDELayout doesn't need to
 * forward either.
 *
 * @param {Object}      props
 * @param {Object|null} props.visiblePanel    - Currently visible activity panel descriptor (or null).
 * @param {Function}    props.onSidebarToggle - Click handler for the sidebar toggle.
 * @param {Object}      props.topbarCtx       - Context passed to action callables (refetchSchema, isSchemaLoading, etc.).
 * @param {Function}    [props.onClose]       - Close handler for drawer mode (omitted on the dedicated page).
 */
export function IDETopbar({
	visiblePanel,
	onSidebarToggle,
	topbarCtx,
	onClose,
}) {
	const registeredActions = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getTopbarActions(),
		[]
	);
	const topbarActions = endpointMode ? [] : registeredActions;
	return (
		<div className="wpgraphql-ide-topbar">
			<div className="wpgraphql-ide-topbar-left">
				<Tooltip
					placement="right"
					text={
						visiblePanel
							? __('Collapse sidebar', 'wpgraphql-ide')
							: __('Expand sidebar', 'wpgraphql-ide')
					}
				>
					<Button
						onClick={onSidebarToggle}
						aria-label={
							visiblePanel
								? __('Collapse sidebar', 'wpgraphql-ide')
								: __('Expand sidebar', 'wpgraphql-ide')
						}
						size="compact"
						className={`wpgraphql-ide-topbar-btn${visiblePanel ? ' is-active' : ''}`}
					>
						<Icon icon={sidebar} />
					</Button>
				</Tooltip>
			</div>
			<div className="wpgraphql-ide-topbar-center">
				{/* WPGraphQL is a proper noun / product name; intentionally not
				    translated so screenshots and docs stay consistent across
				    locales. */}
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
				{onClose && (
					<>
						<div className="wpgraphql-ide-topbar-sep" />
						<Tooltip text={__('Close', 'wpgraphql-ide')}>
							<Button
								onClick={onClose}
								aria-label={__('Close', 'wpgraphql-ide')}
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
