import React, { useMemo, useState } from 'react';
import {
	DropdownMenu,
	MenuGroup,
	MenuItem,
	Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { moreVertical } from '@wordpress/icons';
import { ResponseContent } from './ResponseContent';

const VIEW_MODES = [
	{ value: 'formatted', label: 'JSON' },
	{ value: 'table', label: 'Table' },
];

/**
 * Right side of the editor split: response header (data-scope kebab,
 * status / duration / size meta, JSON/Table view toggle) plus the
 * `ResponseContent` body.
 *
 * @param {Object}              props
 * @param {string}              props.response               - JSON-stringified body or empty string.
 * @param {'data'|'full'}       props.responseDataScope      - Which slice of the envelope to show.
 * @param {Function}            props.onSetDataScope         - Setter for `responseDataScope`.
 * @param {'formatted'|'table'} props.responseViewMode       - Top-pane render mode.
 * @param {Function}            props.onSetViewMode          - Setter for `responseViewMode` (also writes localStorage).
 * @param {number|null}         props.responseStatus         - HTTP status of the last response.
 * @param {number|null}         props.responseDuration       - Last response duration in ms.
 * @param {number|null}         props.responseSize           - Last response payload size in bytes.
 * @param {Object|null}         props.responseHeaders        - Header map for the Headers tab.
 * @param {Array}               props.extensionTabs          - Registered extension tab descriptors.
 * @param {boolean}             props.isFetching             - Whether a request is in flight.
 * @param {string|number}       props.responseViewerHeight   - Top pane height (px or '%').
 * @param {Function}            props.onResponseViewerResize - Called with the new px height on resize stop.
 */
export function ResponsePane({
	response,
	responseDataScope,
	onSetDataScope,
	responseViewMode,
	onSetViewMode,
	responseStatus,
	responseDuration,
	responseSize,
	responseHeaders,
	extensionTabs,
	isFetching,
	responseViewerHeight,
	onResponseViewerResize,
}) {
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
				<span className="wpgraphql-ide-response-label">Response</span>
				<DropdownMenu
					icon={moreVertical}
					label="Response options"
					toggleProps={{
						size: 'small',
						className: 'wpgraphql-ide-panel-kebab',
					}}
				>
					{({ onClose: closeMenu }) => (
						<MenuGroup>
							<MenuItem
								onClick={() => {
									onSetDataScope('data');
									closeMenu();
								}}
								isSelected={responseDataScope === 'data'}
							>
								Show data only
							</MenuItem>
							<MenuItem
								onClick={() => {
									onSetDataScope('full');
									closeMenu();
								}}
								isSelected={responseDataScope === 'full'}
							>
								Show full response
							</MenuItem>
						</MenuGroup>
					)}
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
					aria-label="View format"
				>
					{VIEW_MODES.map((opt) => (
						<button
							key={opt.value}
							type="button"
							aria-pressed={responseViewMode === opt.value}
							className={`wpgraphql-ide-response-mode-btn${responseViewMode === opt.value ? ' is-active' : ''}`}
							onClick={() => onSetViewMode(opt.value)}
						>
							{opt.label}
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
			/>
		</div>
	);
}
