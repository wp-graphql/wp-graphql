import React, { useMemo } from 'react';
import { ResizableBox, TabPanel } from '@wordpress/components';
import { ResponseViewer } from '../editors/ResponseViewer';
import { ErrorsPanel } from '../ErrorsPanel';
import { HeadersPanel } from '../HeadersPanel';
import { ResponseTableView } from '../ResponseTableView';

/**
 * Body of the response pane: the JSON / table viewer up top with a
 * resizable bottom strip carrying Headers / Errors / Extensions.
 *
 * When there's no response yet, the panel is intentionally bare — no
 * tabs, no resizer, just an empty surface with a "Run the query…" hint
 * filling the panel height.
 *
 * @param {Object}              props
 * @param {string}              props.response               - JSON-stringified response body, or empty string.
 * @param {'formatted'|'table'} props.responseViewMode       - Top-pane render mode.
 * @param {'data'|'full'}       props.responseDataScope      - Whether the viewer renders only `data` or the full envelope.
 * @param {Object|null}         props.responseHeaders        - Response headers map (count drives the Headers tab label).
 * @param {Array}               props.extensionTabs          - Extension tab descriptors registered via the response-extensions store.
 * @param {string|number}       props.responseViewerHeight   - Height of the top pane (px or '%').
 * @param {Function}            props.onResponseViewerResize - Called with the new px height on resize stop.
 */
export function ResponseContent({
	response,
	responseViewMode,
	responseDataScope,
	responseHeaders,
	extensionTabs,
	responseViewerHeight,
	onResponseViewerResize,
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

	const activeExtTabs = extensionTabs.filter(
		(tab) => extensions[tab.name] !== undefined
	);

	const headersCount =
		responseHeaders && typeof responseHeaders === 'object'
			? Object.keys(responseHeaders).length
			: 0;

	const bottomTabs = [
		{ name: 'headers', title: `Headers (${headersCount})` },
		{ name: 'errors', title: `Errors (${errors.length})` },
		{
			name: 'extensions',
			title: `Extensions (${activeExtTabs.length})`,
		},
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
				onResizeStop={(e, d, elt) => {
					onResponseViewerResize(elt.offsetHeight);
				}}
				className="wpgraphql-ide-response-viewer wpgraphql-ide-resizable-split"
			>
				{renderViewer()}
			</ResizableBox>
			<TabPanel
				key={errors.length > 0 ? 'has-errors' : 'no-errors'}
				className={`wpgraphql-ide-response-tabs${errors.length > 0 ? ' has-errors' : ''}`}
				tabs={bottomTabs}
				initialTabName={errors.length > 0 ? 'errors' : 'headers'}
			>
				{(tab) => {
					if (tab.name === 'headers') {
						return <HeadersPanel headers={responseHeaders} />;
					}
					if (tab.name === 'errors') {
						return <ErrorsPanel errors={errors} />;
					}
					if (tab.name === 'extensions') {
						if (activeExtTabs.length === 0) {
							const hasUnregistered =
								Object.keys(extensions).length > 0;
							return (
								<p className="wpgraphql-ide-extensions-empty">
									{hasUnregistered
										? 'The response contains extension data, but no extension has registered a tab to display it.'
										: 'No extensions in the last response.'}
								</p>
							);
						}
						return (
							<TabPanel
								className="wpgraphql-ide-extension-tabs"
								key={activeExtTabs.map((t) => t.name).join('|')}
								tabs={activeExtTabs.map((t) => ({
									name: t.name,
									title: t.title || t.name,
								}))}
							>
								{(extTab) => {
									const ext = activeExtTabs.find(
										(t) => t.name === extTab.name
									);
									const ExtContent = ext?.content;
									return ExtContent ? (
										<ExtContent
											data={extensions[extTab.name]}
											response={response}
										/>
									) : null;
								}}
							</TabPanel>
						);
					}
					return null;
				}}
			</TabPanel>
		</div>
	);
}
