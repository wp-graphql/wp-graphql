import React, { useMemo } from 'react';
import { ResizableBox } from '@wordpress/components';
import { ResponseViewer } from '../editors/ResponseViewer';
import { ResponseTableView } from '../ResponseTableView';
import { useResizeReporter } from '../ResizeOverlay';
import { OverflowTabs } from '../OverflowTabs';

/**
 * Body of the response pane: the JSON / table viewer up top with a
 * resizable bottom strip carrying Headers / Errors / Extensions.
 *
 * When there's no response yet, the panel is intentionally bare — no
 * tabs, no resizer, just an empty surface with a "Run the query…" hint
 * filling the panel height.
 *
 * @param {Object}                             props
 * @param {string}                             props.response               - JSON-stringified response body, or empty string.
 * @param {'formatted'|'table'}                props.responseViewMode       - Top-pane render mode.
 * @param {'data'|'full'}                      props.responseDataScope      - Whether the viewer renders only `data` or the full envelope.
 * @param {Object|null}                        props.responseHeaders        - Response headers map (count drives the Headers tab label).
 * @param {Array}                              props.extensionTabs          - Extension tab descriptors registered via the response-extensions store.
 * @param {string|number}                      props.responseViewerHeight   - Height of the top pane (px or '%').
 * @param {Function}                           props.onResponseViewerResize - Called with the new px height on resize stop.
 * @param {{name: string, token: number}|null} props.tabRequest             - Programmatic tab navigation request from the parent (e.g. status-bar badge). `name` is the tab to focus; `token` is bumped on every click so re-clicking the same tab still re-keys the TabPanel and re-applies focus.
 */
export function ResponseContent({
	response,
	responseViewMode,
	responseDataScope,
	responseHeaders,
	extensionTabs,
	responseViewerHeight,
	onResponseViewerResize,
	tabRequest,
}) {
	const parsed = useMemo(() => {
		if (!response) {
			return null;
		}
		try {
			return JSON.parse(response);
		} catch {
			return null;
		}
	}, [response]);

	const errors = parsed?.errors || [];
	const extensions = parsed?.extensions || {};

	// Synthetic data slots — Errors and Headers describe the response
	// envelope itself, not response.extensions, so they don't have a
	// matching extensions[name] entry. We surface them under their tab
	// `name` so a single map can resolve every registered tab's data.
	const slotData = { ...extensions, errors, headers: responseHeaders };

	const activeExtTabs = extensionTabs.filter(
		(tab) => tab.alwaysShow || extensions[tab.name] !== undefined
	);

	// The synthetic "Extensions" tab still appears when the response
	// includes extension data that no plugin has registered a renderer
	// for — useful as a "you have data here but nothing's reading it"
	// signal. It hides itself as soon as that data is registered or the
	// response stops emitting it.
	const unregisteredExtensionKeys = Object.keys(extensions).filter(
		(key) => !extensionTabs.some((t) => t.name === key)
	);
	const showUnregisteredFallback = unregisteredExtensionKeys.length > 0;

	const bottomTabs = [
		...activeExtTabs.map((t) => {
			const data = slotData[t.name];
			const title =
				typeof t.title === 'function'
					? t.title({ data, response, errors, responseHeaders })
					: t.title || t.name;
			return { name: `ext:${t.name}`, title };
		}),
		...(showUnregisteredFallback
			? [{ name: 'extensions:unregistered', title: 'Extensions' }]
			: []),
	];

	const viewerContent = useMemo(() => {
		if (!response) {
			return '';
		}
		if (responseDataScope === 'data') {
			if (parsed?.data !== undefined && parsed?.data !== null) {
				return JSON.stringify(parsed.data, null, 2);
			}
			return '// No data in response';
		}
		return response;
	}, [response, responseDataScope, parsed]);

	const reporter = useResizeReporter('Response viewer');
	const tabsReporter = useResizeReporter('Response tabs');

	const renderViewer = () => {
		if (!response) {
			return <div className="wpgraphql-ide-response-empty" />;
		}
		if (responseViewMode === 'table') {
			return (
				<ResponseTableView
					response={
						responseDataScope === 'data' ? parsed?.data : parsed
					}
				/>
			);
		}
		return <ResponseViewer value={viewerContent} />;
	};

	if (!response) {
		return (
			<div className="wpgraphql-ide-response-body wpgraphql-ide-response-body--empty">
				{reporter.indicator}
				<div className="wpgraphql-ide-response-empty">
					<div className="wpgraphql-ide-response-empty-hint">
						<h3 className="wpgraphql-ide-response-empty-title">
							No response yet
						</h3>
						<p className="wpgraphql-ide-response-empty-description">
							Run a query to see results here.
						</p>
					</div>
				</div>
			</div>
		);
	}

	return (
		<div className="wpgraphql-ide-response-body">
			<ResizableBox
				size={{ width: '100%', height: responseViewerHeight }}
				minHeight={50}
				enable={{ bottom: true }}
				onResizeStart={reporter.reportStart}
				onResize={reporter.reportResize}
				onResizeStop={(e, d, elt) => {
					reporter.reportStop();
					onResponseViewerResize(elt.offsetHeight);
				}}
				className="wpgraphql-ide-response-viewer wpgraphql-ide-resizable-split"
			>
				{reporter.indicator}
				{renderViewer()}
			</ResizableBox>
			<div className="wpgraphql-ide-response-tabs-wrap">
				{tabsReporter.indicator}
				<OverflowTabs
					// Re-key on the request token so every status-bar click
					// (even repeated clicks targeting the same tab) remounts
					// the tab strip with the requested initialTabName. Without
					// the token, a click while the requested tab is already
					// active is a no-op state change.
					key={`${errors.length > 0 ? 'has-errors' : 'no-errors'}|${tabRequest?.token || ''}`}
					className={`wpgraphql-ide-response-tabs${errors.length > 0 ? ' has-errors' : ''}`}
					tabs={bottomTabs}
					initialTabName={
						tabRequest?.name ||
						(errors.length > 0 ? 'ext:errors' : 'ext:tracing')
					}
				>
					{(tab) => {
						if (tab.name.startsWith('ext:')) {
							const extName = tab.name.slice(4);
							const ext = activeExtTabs.find(
								(t) => t.name === extName
							);
							const ExtContent = ext?.content;
							return ExtContent ? (
								<ExtContent
									data={slotData[extName]}
									response={response}
								/>
							) : null;
						}
						if (tab.name === 'extensions:unregistered') {
							return (
								<div className="wpgraphql-ide-extensions-empty">
									<p>
										The response contains extension data,
										but no plugin has registered a renderer
										for it:
									</p>
									<ul>
										{unregisteredExtensionKeys.map(
											(key) => (
												<li key={key}>
													<code>{key}</code>
												</li>
											)
										)}
									</ul>
								</div>
							);
						}
						return null;
					}}
				</OverflowTabs>
			</div>
		</div>
	);
}
