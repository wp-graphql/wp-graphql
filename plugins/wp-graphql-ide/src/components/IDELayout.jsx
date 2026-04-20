import React, { useState } from 'react';
import { Button, ResizableBox, TabPanel, Spinner } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { GraphQLEditor } from './editors/GraphQLEditor';
import { JSONEditor } from './editors/JSONEditor';
import { ResponseViewer } from './editors/ResponseViewer';
import { EditorToolbar } from './EditorToolbar';
import { ActivityBar } from './ActivityBar';
import ActivityPanel from './ActivityPanel';
import { useSchema } from '../hooks/useSchema';
import { useExecution } from '../hooks/useExecution';

/**
 * Main IDE layout component.
 *
 * Composes the CodeMirror 6 editors with @wordpress/components to provide a
 * native WordPress admin look and feel. State is managed via the
 * `wpgraphql-ide/app` @wordpress/data store.
 *
 * @param {Object}   props
 * @param {Function} props.fetcher - GraphQL fetcher function.
 */
export function IDELayout({ fetcher }) {
	const { query, variables, headers, response } = useSelect((select) => {
		const app = select('wpgraphql-ide/app');
		return {
			query: app.getQuery() || '',
			variables: app.getVariables(),
			headers: app.getHeaders(),
			response: app.getResponse(),
		};
	}, []);

	const { setQuery, setVariables, setHeaders } =
		useDispatch('wpgraphql-ide/app');

	const { schema, isLoading: isSchemaLoading, refetch } = useSchema(fetcher);
	const { isFetching, run, stop } = useExecution(fetcher);

	const [queryPaneWidth, setQueryPaneWidth] = useState(null);
	const [editorHeight, setEditorHeight] = useState(null);
	const executeQuery = () => {
		if (isFetching) {
			stop();
		} else {
			run();
		}
	};

	return (
		<div className="wpgraphql-ide-layout">
			<div className="wpgraphql-ide-toolbar">
				<Button
					variant="primary"
					onClick={executeQuery}
					disabled={isSchemaLoading}
				>
					{isFetching ? 'Stop' : 'Execute'}
				</Button>
				<Button variant="secondary" onClick={refetch}>
					Refresh schema
				</Button>
				{isSchemaLoading && <Spinner />}
				<div className="wpgraphql-ide-toolbar-separator" />
				<EditorToolbar />
			</div>

			<div className="wpgraphql-ide-main">
				<ActivityBar
					schemaContext={{ isFetching: isSchemaLoading }}
					handleRefetchSchema={refetch}
				/>
				<ActivityPanel />
				<ResizableBox
					size={{
						width: queryPaneWidth || '50%',
						height: 'auto',
					}}
					minWidth={200}
					enable={{
						top: false,
						right: true,
						bottom: false,
						left: false,
					}}
					onResizeStop={(event, direction, elt) => {
						setQueryPaneWidth(elt.offsetWidth);
					}}
					className="wpgraphql-ide-query-pane"
				>
					<ResizableBox
						size={{
							width: '100%',
							height: editorHeight || '70%',
						}}
						minHeight={50}
						enable={{
							top: false,
							right: false,
							bottom: true,
							left: false,
						}}
						onResizeStop={(event, direction, elt) => {
							setEditorHeight(elt.offsetHeight);
						}}
						className="wpgraphql-ide-editor-resizable"
					>
						<GraphQLEditor
							value={query}
							onChange={setQuery}
							schema={schema}
						/>
					</ResizableBox>
					<TabPanel
						className="wpgraphql-ide-editor-tools"
						tabs={[
							{ name: 'variables', title: 'Variables' },
							{ name: 'headers', title: 'Headers' },
						]}
					>
						{(tab) => {
							if (tab.name === 'variables') {
								return (
									<JSONEditor
										value={variables}
										onChange={setVariables}
										placeholder="Variables (JSON)"
									/>
								);
							}
							return (
								<JSONEditor
									value={headers}
									onChange={setHeaders}
									placeholder="Headers (JSON)"
								/>
							);
						}}
					</TabPanel>
				</ResizableBox>

				<div className="wpgraphql-ide-response-pane">
					{isFetching && (
						<div className="wpgraphql-ide-response-spinner">
							<Spinner />
						</div>
					)}
					<ResponseViewer value={response} />
				</div>
			</div>
		</div>
	);
}
