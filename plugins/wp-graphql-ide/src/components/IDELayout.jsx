import React, { useState, useCallback } from 'react';
import {
	Button,
	ResizableBox,
	TabPanel,
	Spinner,
	Tooltip,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { GraphQLEditor } from './editors/GraphQLEditor';
import { JSONEditor } from './editors/JSONEditor';
import { ResponseViewer } from './editors/ResponseViewer';
import { EditorToolbar } from './EditorToolbar';
import { ActivityBar } from './ActivityBar';
import ActivityPanel from './ActivityPanel';
import { ShortKeysDialog } from './ShortKeysDialog';
import { SettingsDialog } from './SettingsDialog';
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
	const [showDialog, setShowDialog] = useState(null);

	const handleShowDialog = useCallback((e) => {
		setShowDialog(e.currentTarget.dataset.value);
	}, []);

	const executeQuery = () => {
		if (isFetching) {
			stop();
		} else {
			run();
		}
	};

	const PlayIcon = () => (
		<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">
			<path d="M9 5v14l10-7z" fill="currentColor" />
		</svg>
	);

	const StopIcon = () => (
		<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">
			<rect x="6" y="6" width="12" height="12" fill="currentColor" />
		</svg>
	);

	return (
		<div className="wpgraphql-ide-layout">
			<div className="wpgraphql-ide-main">
				<ActivityBar
					schemaContext={{ isFetching: isSchemaLoading }}
					handleRefetchSchema={refetch}
					handleShowDialog={handleShowDialog}
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
						{isSchemaLoading && (
							<div className="wpgraphql-ide-schema-spinner">
								<Spinner />
							</div>
						)}
						<GraphQLEditor
							value={query}
							onChange={setQuery}
							schema={schema}
						/>
						<div className="wpgraphql-ide-editor-actions">
							<Tooltip
								text={isFetching ? 'Stop' : 'Execute query'}
							>
								<Button
									variant="primary"
									onClick={executeQuery}
									disabled={isSchemaLoading}
									className="wpgraphql-ide-execute-button"
									aria-label={
										isFetching ? 'Stop' : 'Execute query'
									}
								>
									{isFetching ? <StopIcon /> : <PlayIcon />}
								</Button>
							</Tooltip>
							<EditorToolbar />
						</div>
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
										key="variables"
										value={variables}
										onChange={setVariables}
										placeholder="Variables (JSON)"
									/>
								);
							}
							return (
								<JSONEditor
									key="headers"
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
			<ShortKeysDialog
				showDialog={showDialog}
				handleOpenShortKeysDialog={() => setShowDialog(null)}
			/>
			<SettingsDialog
				showDialog={showDialog}
				handleOpenSettingsDialog={() => setShowDialog(null)}
			/>
		</div>
	);
}
