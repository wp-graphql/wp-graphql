import React, { useState, useCallback, useEffect, useRef } from 'react';
import {
	Button,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	ResizableBox,
	TabPanel,
	Spinner,
	Tooltip,
} from '@wordpress/components';
import {
	Icon,
	edit,
	help,
	backup,
	update,
	keyboard,
	chevronDown,
	plus,
} from '@wordpress/icons';
import { useDispatch, useSelect } from '@wordpress/data';
import { GraphQLEditor } from './editors/GraphQLEditor';
import { JSONEditor } from './editors/JSONEditor';
import { ResponseViewer } from './editors/ResponseViewer';
import { EditorToolbar } from './EditorToolbar';
import ActivityPanel from './ActivityPanel';
import authStyles from '../../styles/ToggleAuthenticationButton.module.css';
import { ShortKeysDialog } from './ShortKeysDialog';
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

	const { activeDocument, allDocuments, openTabs } = useSelect((select) => {
		const editor = select('wpgraphql-ide/document-editor');
		return {
			activeDocument: editor.getActiveDocument(),
			allDocuments: editor.getDocuments(),
			openTabs: editor.getOpenTabs(),
		};
	}, []);

	const isAuthenticated = useSelect(
		(select) => select('wpgraphql-ide/app').isAuthenticated(),
		[]
	);

	const {
		setQuery,
		setVariables,
		setHeaders,
		setResponse,
		toggleAuthentication,
	} = useDispatch('wpgraphql-ide/app');

	const {
		loadDocuments,
		saveDocument,
		createTab,
		switchTab,
		closeTab,
		setDocumentResponse,
	} = useDispatch('wpgraphql-ide/document-editor');

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

			// Store response on the document for tab switching.
			setDocumentResponse(doc.id, JSON.stringify(result, null, 2));
		},
		[saveDocument, setDocumentResponse]
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

	// When active document changes, populate editors and restore response.
	useEffect(() => {
		if (!activeDocument) {
			return;
		}
		setQuery(activeDocument.query || '');
		setVariables(activeDocument.variables || '');
		setHeaders(activeDocument.headers || '');
		setResponse(activeDocument.lastResponse || '');
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

	const { toggleActivityPanelVisibility } = useDispatch(
		'wpgraphql-ide/activity-bar'
	);

	const visiblePanel = useSelect(
		(select) => select('wpgraphql-ide/activity-bar').visiblePanel(),
		[]
	);

	const panels = useSelect(
		(select) => select('wpgraphql-ide/activity-bar').activityPanels(),
		[]
	);

	const panelIcons = {
		'query-composer': edit,
		help,
		history: backup,
	};

	// Filter out documents panel — it's accessed via the header title dropdown.
	const toolPanels = panels.filter((p) => p.name !== 'documents');

	return (
		<div className="wpgraphql-ide-container">
			{/* Header bar — matches Gutenberg post editor header */}
			<div className="wpgraphql-ide-header">
				<div className="wpgraphql-ide-header-left">
					{toolPanels.map((panel) => (
						<Tooltip key={panel.name} text={panel.title}>
							<Button
								isPressed={visiblePanel?.name === panel.name}
								onClick={() =>
									toggleActivityPanelVisibility(panel.name)
								}
								aria-label={panel.title}
								size="compact"
							>
								<Icon icon={panelIcons[panel.name] || edit} />
							</Button>
						</Tooltip>
					))}
					<div className="wpgraphql-ide-header-separator" />
					<Tooltip text="Re-fetch schema">
						<Button
							onClick={refetch}
							disabled={isSchemaLoading}
							aria-label="Re-fetch schema"
							size="compact"
						>
							<Icon icon={update} />
						</Button>
					</Tooltip>
					{isSchemaLoading && (
						<span className="wpgraphql-ide-loading-label">
							<Spinner />
						</span>
					)}
				</div>
				<div className="wpgraphql-ide-header-center">
					<DropdownMenu
						icon={chevronDown}
						label="Switch document"
						toggleProps={{
							children: (
								<span className="wpgraphql-ide-document-name">
									{activeDocument?.title || 'Untitled'}
								</span>
							),
							className: 'wpgraphql-ide-document-switcher',
							size: 'compact',
						}}
					>
						{({ onClose }) => (
							<>
								<MenuGroup>
									{openTabs
										.map((tabId) =>
											allDocuments.find(
												(d) =>
													String(d.id) ===
													String(tabId)
											)
										)
										.filter(Boolean)
										.map((doc) => (
											<MenuItem
												key={doc.id}
												onClick={() => {
													switchTab(String(doc.id));
													onClose();
												}}
												isSelected={
													String(doc.id) ===
													String(activeDocument?.id)
												}
												suffix={
													openTabs.length > 1 ? (
														<Button
															size="small"
															onClick={(e) => {
																e.stopPropagation();
																closeTab(
																	String(
																		doc.id
																	)
																);
															}}
															aria-label="Close document"
															className="wpgraphql-ide-doc-close"
														>
															&times;
														</Button>
													) : null
												}
											>
												{doc.title || 'Untitled'}
											</MenuItem>
										))}
								</MenuGroup>
								<MenuGroup>
									<MenuItem
										onClick={() => {
											createTab();
											onClose();
										}}
									>
										<Icon icon={plus} />
										New document
									</MenuItem>
								</MenuGroup>
							</>
						)}
					</DropdownMenu>
				</div>
				<div className="wpgraphql-ide-header-right">
					<Tooltip text="Keyboard shortcuts">
						<Button
							data-value="short-keys"
							onClick={handleShowDialog}
							aria-label="Keyboard shortcuts"
							size="compact"
						>
							<Icon icon={keyboard} />
						</Button>
					</Tooltip>
					<div className="wpgraphql-ide-header-separator" />
					<div className="wpgraphql-ide-send-group">
						<Tooltip
							text={
								isAuthenticated
									? 'Sending as logged-in user (click to switch)'
									: 'Sending as public user (click to switch)'
							}
						>
							<button
								type="button"
								onClick={toggleAuthentication}
								className={`wpgraphql-ide-auth-avatar ${!isAuthenticated ? authStyles.authAvatarPublic : ''}`}
								aria-label={
									isAuthenticated
										? 'Switch to public'
										: 'Switch to authenticated'
								}
							>
								<span
									className={authStyles.authAvatar}
									style={{
										backgroundImage: `url(${window.WPGRAPHQL_IDE_DATA?.context?.avatarUrl || ''})`,
									}}
								>
									<span className={authStyles.authBadge} />
								</span>
							</button>
						</Tooltip>
						<span className="wpgraphql-ide-method-label">POST</span>
						<Button
							variant="primary"
							onClick={executeQuery}
							disabled={isSchemaLoading}
							className="wpgraphql-ide-send-button"
							size="compact"
						>
							{isFetching ? 'Stop' : 'Send'}
						</Button>
					</div>
				</div>
			</div>
			{/* Main content area */}
			<div className="wpgraphql-ide-main">
				<ActivityPanel />
				<ResizableBox
					size={{ width: queryPaneWidth, height: 'auto' }}
					minWidth={200}
					enable={{ right: true }}
					onResizeStop={(e, d, elt) =>
						setQueryPaneWidth(elt.offsetWidth)
					}
					className="wpgraphql-ide-query-pane"
				>
					<div className="wpgraphql-ide-editor-toolbar">
						<EditorToolbar />
					</div>
					<ResizableBox
						size={{ width: '100%', height: editorHeight }}
						minHeight={50}
						enable={{ bottom: true }}
						onResizeStop={(e, d, elt) =>
							setEditorHeight(elt.offsetHeight)
						}
						className="wpgraphql-ide-editor-resizable"
					>
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
						{(tab) =>
							tab.name === 'variables' ? (
								<JSONEditor
									key="variables"
									value={variables}
									onChange={handleVariablesChange}
									placeholder="Variables (JSON)"
								/>
							) : (
								<JSONEditor
									key="headers"
									value={headers}
									onChange={handleHeadersChange}
									placeholder="Headers (JSON)"
								/>
							)
						}
					</TabPanel>
				</ResizableBox>

				<div className="wpgraphql-ide-response-pane">
					<div className="wpgraphql-ide-response-header">
						<span className="wpgraphql-ide-response-label">
							Response
						</span>
						{isFetching && <Spinner />}
					</div>
					<ResponseViewer value={response} />
				</div>
			</div>
			<ShortKeysDialog
				showDialog={showDialog}
				handleOpenShortKeysDialog={() => setShowDialog(null)}
			/>
		</div>
	);
}
