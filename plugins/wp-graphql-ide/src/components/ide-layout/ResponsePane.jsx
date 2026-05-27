import React, { useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	DropdownMenu,
	MenuGroup,
	MenuItem,
	Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { moreVertical } from '@wordpress/icons';
import { ResponseContent } from './ResponseContent';

/**
 * Right side of the editor split: response header (data-scope kebab,
 * status / duration / size meta, JSON/Table view toggle) plus the
 * `ResponseContent` body.
 *
 * Reads its response data (body, status, duration, size, headers) and
 * the registered extension-tab list directly from the relevant stores
 * so IDELayout doesn't have to forward them through a wide prop pipe.
 * Props are reserved for things IDELayout actually controls — layout
 * state (`responseViewerHeight`), view-mode selection that needs to
 * persist through the layout's preference adapter, and event callbacks
 * that wire back into the layout's logic.
 *
 * @param {Object}              props
 * @param {string}              props.response               - JSON-stringified body or empty string.
 * @param {'data'|'full'}       props.responseDataScope      - Which slice of the envelope to show.
 * @param {Function}            props.onSetDataScope         - Setter for `responseDataScope`.
 * @param {'formatted'|'table'} props.responseViewMode       - Top-pane render mode.
 * @param {Function}            props.onSetViewMode          - Setter for `responseViewMode` (also writes localStorage).
 * @param {boolean}             props.isFetching             - Whether a request is in flight.
 * @param {string|number}       props.responseViewerHeight   - Top pane height (px or '%').
 * @param {Function}            props.onResponseViewerResize - Called with the new px height on resize stop.
 * @param {boolean}             [props.bottomCollapsed]      - Whether the bottom tabs strip is collapsed.
 * @param {Function}            [props.onSetBottomCollapsed] - Setter for `bottomCollapsed`.
 * @param {string|null}         [props.bottomActiveTab]      - Last-active bottom tab name.
 * @param {Function}            [props.onSetBottomActiveTab] - Setter for `bottomActiveTab`.
 */
export function ResponsePane({
	response,
	responseDataScope,
	onSetDataScope,
	responseViewMode,
	onSetViewMode,
	isFetching,
	responseViewerHeight,
	onResponseViewerResize,
	bottomCollapsed = false,
	onSetBottomCollapsed,
	bottomActiveTab,
	onSetBottomActiveTab,
}) {
	const responseStatus = useSelect(
		(select) => select('wpgraphql-ide/app').getResponseStatus(),
		[]
	);
	const responseDuration = useSelect(
		(select) => select('wpgraphql-ide/app').getResponseDuration(),
		[]
	);
	const responseSize = useSelect(
		(select) => select('wpgraphql-ide/app').getResponseSize(),
		[]
	);
	const responseHeaders = useSelect(
		(select) => select('wpgraphql-ide/app').getResponseHeaders(),
		[]
	);
	const extensionTabs = useSelect(
		(select) => select('wpgraphql-ide/response-extensions').extensionTabs(),
		[]
	);
	// Programmatic tab navigation: status-bar badges set this, which
	// remounts the response TabPanel via `key` to honor the new
	// `initialTabName`. Each click increments `token` so re-clicking
	// the same badge while that tab is already active still produces
	// a key change and re-applies focus — without the token, the
	// second click would be a no-op.
	const [tabRequest, setTabRequest] = useState(null);

	const statusBarItems = useSelect(
		(s) => s('wpgraphql-ide/status-bar-items').statusBarItems(),
		[]
	);
	const viewModes = useSelect(
		(s) => s('wpgraphql-ide/response-view-modes').responseViewModes(),
		[]
	);
	const responseActions = useSelect(
		(s) => s('wpgraphql-ide/response-actions').responseActions(),
		[]
	);

	const parsedResponse = useMemo(() => {
		if (!response) {
			return null;
		}
		try {
			return JSON.parse(response);
		} catch {
			return null;
		}
	}, [response]);

	const focusResponseTab = (name) =>
		setTabRequest({ name, token: Date.now() });

	const statusBarCtx = {
		response,
		parsedResponse,
		responseStatus,
		responseDuration,
		responseSize,
		isFetching,
		focusResponseTab,
	};

	return (
		<div className="wpgraphql-ide-response-pane">
			<div className="wpgraphql-ide-response-header">
				<span className="wpgraphql-ide-response-label">
					{__('Response', 'wpgraphql-ide')}
				</span>
				<DropdownMenu
					icon={moreVertical}
					label={__('Response options', 'wpgraphql-ide')}
					toggleProps={{
						size: 'small',
						className: 'wpgraphql-ide-panel-kebab',
					}}
				>
					{({ onClose: closeMenu }) => {
						const ctx = {
							dataScope: responseDataScope,
							setDataScope: onSetDataScope,
							response,
							parsedResponse,
							closeMenu,
						};
						const visible = responseActions.filter((a) =>
							a.predicate ? a.predicate(ctx) : true
						);
						const groups = [];
						const groupIndex = new Map();
						for (const a of visible) {
							const key = a.group || '';
							if (!groupIndex.has(key)) {
								groupIndex.set(key, groups.length);
								groups.push({ label: key, items: [] });
							}
							groups[groupIndex.get(key)].items.push(a);
						}
						return (
							<>
								{groups.map((g, i) => (
									<MenuGroup
										key={g.label || `group-${i}`}
										label={g.label || undefined}
									>
										{g.items.map((item) => {
											const handleClick = () => {
												item.onClick(ctx);
											};
											const labelText =
												typeof item.label === 'function'
													? item.label(ctx)
													: item.label;
											return (
												<MenuItem
													key={item.name}
													onClick={handleClick}
													isSelected={
														item.isSelected
															? item.isSelected(
																	ctx
																)
															: undefined
													}
													isDestructive={
														!!item.isDestructive
													}
													disabled={
														item.isDisabled
															? !!item.isDisabled(
																	ctx
																)
															: false
													}
												>
													{labelText}
												</MenuItem>
											);
										})}
									</MenuGroup>
								))}
							</>
						);
					}}
				</DropdownMenu>
				<div className="wpgraphql-ide-editor-toolbar-spacer" />
				{isFetching && <Spinner />}
				{!isFetching && responseStatus !== null && (
					<span className="wpgraphql-ide-response-meta">
						{statusBarItems.map((item) => {
							const node = item.render(statusBarCtx);
							if (node === null || node === undefined) {
								return null;
							}
							return (
								<React.Fragment key={item.name}>
									{node}
								</React.Fragment>
							);
						})}
					</span>
				)}
				<div
					className="wpgraphql-ide-response-mode-toggle"
					role="group"
					aria-label={__('View format', 'wpgraphql-ide')}
				>
					{viewModes.map((mode) => (
						<button
							key={mode.value}
							type="button"
							aria-pressed={responseViewMode === mode.value}
							className={`wpgraphql-ide-response-mode-btn${responseViewMode === mode.value ? ' is-active' : ''}`}
							onClick={() => onSetViewMode(mode.value)}
						>
							{mode.label}
						</button>
					))}
				</div>
			</div>
			<ResponseContent
				response={response}
				responseViewMode={responseViewMode}
				responseDataScope={responseDataScope}
				responseHeaders={responseHeaders}
				extensionTabs={extensionTabs}
				responseViewerHeight={responseViewerHeight}
				onResponseViewerResize={onResponseViewerResize}
				tabRequest={tabRequest}
				bottomCollapsed={bottomCollapsed}
				onSetBottomCollapsed={onSetBottomCollapsed}
				bottomActiveTab={bottomActiveTab}
				onSetBottomActiveTab={onSetBottomActiveTab}
			/>
		</div>
	);
}
