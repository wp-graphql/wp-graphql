import React, { useMemo, useState } from 'react';
import {
	DropdownMenu,
	MenuGroup,
	MenuItem,
	Spinner,
} from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';
import { ResponseContent } from './ResponseContent';
import { detectNPlusOne } from '../response-extensions/detect-n-plus-one';

const VIEW_MODES = [
	{ value: 'formatted', label: 'JSON' },
	{ value: 'table', label: 'Table' },
];

function formatDuration(ms) {
	if (ms === null) {
		return null;
	}
	return ms >= 1000 ? `${(ms / 1000).toFixed(1)}s` : `${ms}ms`;
}

function formatSize(bytes) {
	if (bytes === null) {
		return null;
	}
	return bytes >= 1024 ? `${(bytes / 1024).toFixed(1)}KB` : `${bytes}B`;
}

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
	// `initialTabName`. Cleared after the parent restream by passing
	// down the same value (idempotent).
	const [requestedTab, setRequestedTab] = useState(null);

	// Surface tracing headlines in the always-visible status bar so
	// "this query has 11 resolvers and 1 likely N+1" is information
	// the user gets *before* clicking into the Tracing tab.
	const tracingSummary = useMemo(() => {
		if (!response) {
			return null;
		}
		try {
			const parsed = JSON.parse(response);
			const tracing = parsed?.extensions?.tracing;
			if (!tracing || typeof tracing !== 'object') {
				return null;
			}
			const resolvers = Array.isArray(tracing.execution?.resolvers)
				? tracing.execution.resolvers
				: [];
			const nPlusOne = detectNPlusOne(resolvers);
			return {
				resolverCount: resolvers.length,
				nPlusOneCount: nPlusOne.length,
			};
		} catch {
			return null;
		}
	}, [response]);

	const focusTracing = () => setRequestedTab('ext:tracing');

	return (
		<div className="wpgraphql-ide-response-pane">
			<div className="wpgraphql-ide-response-header">
				<span className="wpgraphql-ide-response-label">Response</span>
				<DropdownMenu icon={moreVertical} label="Response options">
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
						<span
							className={`wpgraphql-ide-response-status wpgraphql-ide-response-status--${responseStatus >= 200 && responseStatus < 300 ? 'success' : 'error'}`}
						>
							{responseStatus}
						</span>
						{responseDuration !== null && (
							<span className="wpgraphql-ide-response-duration">
								{formatDuration(responseDuration)}
							</span>
						)}
						{responseSize !== null && (
							<span className="wpgraphql-ide-response-size">
								{formatSize(responseSize)}
							</span>
						)}
						{tracingSummary && (
							<>
								<button
									type="button"
									className="wpgraphql-ide-response-trace-badge"
									onClick={focusTracing}
									title="Open the Tracing tab"
								>
									{tracingSummary.resolverCount} resolver
									{tracingSummary.resolverCount === 1
										? ''
										: 's'}
								</button>
								{tracingSummary.nPlusOneCount > 0 && (
									<button
										type="button"
										className="wpgraphql-ide-response-trace-badge wpgraphql-ide-response-trace-badge--warning"
										onClick={focusTracing}
										title="Open the Tracing tab to see the N+1 patterns"
									>
										⚠ {tracingSummary.nPlusOneCount} N+1
									</button>
								)}
							</>
						)}
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
				requestedTab={requestedTab}
			/>
		</div>
	);
}
