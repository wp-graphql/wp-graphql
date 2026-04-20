import React, { useState, useCallback, useEffect, useRef } from 'react';
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
import { TabBar } from './TabBar';
import { ShortKeysDialog } from './ShortKeysDialog';
import { SettingsDialog } from './SettingsDialog';
import { useSchema } from '../hooks/useSchema';
import { useExecution } from '../hooks/useExecution';

const AUTOSAVE_DELAY = 2000;

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

	const activeDocument = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getActiveDocument(),
		[]
	);

	const { setQuery, setVariables, setHeaders, setResponse } =
		useDispatch('wpgraphql-ide/app');

	const { loadDocuments, saveDocument, createTab } = useDispatch(
		'wpgraphql-ide/document-editor'
	);

	const { schema, isLoading: isSchemaLoading, refetch } = useSchema(fetcher);

	const activeDocRef = useRef(null);
	activeDocRef.current = activeDocument;

	const handleExecutionComplete = useCallback(
		({
			result,
			duration_ms: duration,
			status: execStatus,
			variables: vars,
		}) => {
			const doc = activeDocRef.current;
			if (!doc) {
				return;
			}
			const history = doc.history || [];
			const entry = {
				timestamp: Math.floor(Date.now() / 1000),
				variables: vars,
				duration_ms: duration,
				response_summary: JSON.stringify(result).slice(0, 200),
				status: execStatus,
			};
			const updated = [...history, entry].slice(-20);
			saveDocument(doc.id, { history: updated });
		},
		[saveDocument]
	);

	const executionOptions = useRef({ onComplete: handleExecutionComplete });
	executionOptions.current.onComplete = handleExecutionComplete;

	const { isFetching, run, stop } = useExecution(
		fetcher,
		executionOptions.current
	);

	const [queryPaneWidth, setQueryPaneWidth] = useState('50%');
	const [editorHeight, setEditorHeight] = useState('70%');
	const [showDialog, setShowDialog] = useState(null);
	const [isLoaded, setIsLoaded] = useState(false);
	const saveTimerRef = useRef(null);

	// Load documents on mount. Create a default tab if none exist.
	useEffect(() => {
		(async () => {
			await loadDocuments();
			setIsLoaded(true);
		})();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	// When active document changes, populate editors.
	useEffect(() => {
		if (!activeDocument) {
			return;
		}
		setQuery(activeDocument.query || '');
		setVariables(activeDocument.variables || '');
		setHeaders(activeDocument.headers || '');
		setResponse('');
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [activeDocument?.id]);

	// Create a default tab if loaded but no tabs exist.
	useEffect(() => {
		if (isLoaded && !activeDocument) {
			createTab('Untitled');
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [isLoaded, activeDocument]);

	// Debounced auto-save.
	const scheduleAutoSave = useCallback(
		(field, value) => {
			if (!activeDocument) {
				return;
			}
			if (saveTimerRef.current) {
				clearTimeout(saveTimerRef.current);
			}
			saveTimerRef.current = setTimeout(() => {
				saveDocument(activeDocument.id, { [field]: value });
			}, AUTOSAVE_DELAY);
		},
		[activeDocument, saveDocument]
	);

	const handleQueryChange = useCallback(
		(value) => {
			setQuery(value);
			scheduleAutoSave('query', value);
		},
		[setQuery, scheduleAutoSave]
	);

	const handleVariablesChange = useCallback(
		(value) => {
			setVariables(value);
			scheduleAutoSave('variables', value);
		},
		[setVariables, scheduleAutoSave]
	);

	const handleHeadersChange = useCallback(
		(value) => {
			setHeaders(value);
			scheduleAutoSave('headers', value);
		},
		[setHeaders, scheduleAutoSave]
	);

	const handleShowDialog = useCallback((e) => {
		setShowDialog(e.currentTarget.dataset.value);
	}, []);

	const executeQueryRef = useRef(null);
	executeQueryRef.current = () => {
		if (isFetching) {
			stop();
		} else {
			run();
		}
	};

	const executeQuery = () => executeQueryRef.current();

	const { prettifyQuery } = useDispatch('wpgraphql-ide/app');
	const prettifyRef = useRef(null);
	prettifyRef.current = () => {
		if (query) {
			prettifyQuery(query);
		}
	};

	const editorKeyBindings = useRef([
		{
			key: 'Mod-Enter',
			run: () => {
				executeQueryRef.current();
				return true;
			},
		},
		{
			key: 'Ctrl-Shift-p',
			run: () => {
				prettifyRef.current();
				return true;
			},
		},
	]);

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
			<TabBar />
			<div className="wpgraphql-ide-main">
				<ActivityBar
					schemaContext={{ isFetching: isSchemaLoading }}
					handleRefetchSchema={refetch}
					handleShowDialog={handleShowDialog}
				/>
				<ActivityPanel />
				<div className="wpgraphql-ide-editor-area">
					<div className="wpgraphql-ide-toolbar">
						<Tooltip text={isFetching ? 'Stop' : 'Execute query'}>
							<Button
								variant="primary"
								onClick={executeQuery}
								disabled={isSchemaLoading}
								className="wpgraphql-ide-execute-button"
								aria-label={
									isFetching ? 'Stop' : 'Execute query'
								}
								size="compact"
							>
								{isFetching ? <StopIcon /> : <PlayIcon />}
							</Button>
						</Tooltip>
						{isSchemaLoading && <Spinner />}
						<EditorToolbar />
					</div>
					<div className="wpgraphql-ide-editor-content">
						<ResizableBox
							size={{
								width: queryPaneWidth,
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
									height: editorHeight,
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
									key={activeDocument?.id || 'empty'}
									value={query}
									onChange={handleQueryChange}
									schema={schema}
									extraKeys={editorKeyBindings.current}
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
												key="variables"
												value={variables}
												onChange={handleVariablesChange}
												placeholder="Variables (JSON)"
											/>
										);
									}
									return (
										<JSONEditor
											key="headers"
											value={headers}
											onChange={handleHeadersChange}
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
