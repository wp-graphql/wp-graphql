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
	plus,
	moreVertical,
	close,
	search,
} from '@wordpress/icons';
import { useDispatch, useSelect } from '@wordpress/data';
import { GraphQLEditor } from './editors/GraphQLEditor';
import { JSONEditor } from './editors/JSONEditor';
import { ResponseViewer } from './editors/ResponseViewer';
import { ErrorsPanel } from './ErrorsPanel';
import { HeadersPanel } from './HeadersPanel';
import { ResponseTableView } from './ResponseTableView';
import { EditorToolbar } from './EditorToolbar';
import ActivityPanel from './ActivityPanel';
import authStyles from '../../styles/ToggleAuthenticationButton.module.css';
import { useSchema } from '../hooks/useSchema';
import { useExecution } from '../hooks/useExecution';

// eslint-disable-next-line jsdoc/require-param
function ResponseContent({
	response,
	responseViewMode,
	responseHeaders,
	extensionTabs,
}) {
	if (!response) {
		return (
			<div className="wpgraphql-ide-response-empty">
				Run a query to see results
			</div>
		);
	}

	if (responseViewMode === 'raw') {
		return <ResponseViewer value={response} />;
	}

	const parsed = (() => {
		try {
			return JSON.parse(response);
		} catch {
			return null;
		}
	})();

	if (responseViewMode === 'table') {
		return <ResponseTableView response={parsed} />;
	}

	// Formatted mode: data viewer + tabs for Headers/Errors/Extensions.
	const errors = parsed?.errors || [];
	const extensions = parsed?.extensions || {};
	const dataStr = parsed?.data ? JSON.stringify(parsed.data, null, 2) : '';

	// Filter extension tabs to only those with data in the current response.
	const activeExtTabs = extensionTabs.filter(
		(tab) => extensions[tab.name] !== undefined
	);

	const bottomTabs = [
		{
			name: 'headers',
			title: `Headers${responseHeaders ? ` (${Object.keys(responseHeaders).length})` : ''}`,
		},
		...(errors.length > 0
			? [{ name: 'errors', title: `Errors (${errors.length})` }]
			: []),
		...(activeExtTabs.length > 0
			? [
					{
						name: 'extensions',
						title: `Extensions (${activeExtTabs.length})`,
					},
				]
			: []),
	];

	return (
		<div className="wpgraphql-ide-response-formatted">
			<ResizableBox
				size={{ width: '100%', height: '60%' }}
				minHeight={50}
				enable={{ bottom: true }}
				className="wpgraphql-ide-response-data"
			>
				<ResponseViewer value={dataStr || response} />
			</ResizableBox>
			{bottomTabs.length > 0 && (
				<TabPanel
					className="wpgraphql-ide-response-tabs"
					tabs={bottomTabs}
				>
					{(tab) => {
						if (tab.name === 'headers') {
							return <HeadersPanel headers={responseHeaders} />;
						}
						if (tab.name === 'errors') {
							return <ErrorsPanel errors={errors} />;
						}
						if (tab.name === 'extensions') {
							return (
								<TabPanel
									className="wpgraphql-ide-extension-tabs"
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
											/>
										) : null;
									}}
								</TabPanel>
							);
						}
						return null;
					}}
				</TabPanel>
			)}
		</div>
	);
}

const AUTOSAVE_DELAY = 2000;
const TAB_WIDTH_ESTIMATE = 140; // px per tab for overflow calculation

// eslint-disable-next-line jsdoc/require-param
function TabBar({
	openTabs,
	allDocuments,
	activeDocument,
	isEditingTitle,
	editTitle,
	setEditTitle,
	setIsEditingTitle,
	saveDocument,
	switchTab,
	closeTab,
	createTab,
	getNextTabName,
}) {
	const barRef = useRef(null);
	const [maxVisible, setMaxVisible] = useState(Infinity);

	useEffect(() => {
		const el = barRef.current;
		// eslint-disable-next-line no-undef
		if (!el || typeof ResizeObserver === 'undefined') {
			return;
		}
		// eslint-disable-next-line no-undef
		const observer = new ResizeObserver(([entry]) => {
			// Reserve 80px for the overflow button and "+" button.
			const available = entry.contentRect.width - 80;
			setMaxVisible(
				Math.max(1, Math.floor(available / TAB_WIDTH_ESTIMATE))
			);
		});
		observer.observe(el);
		return () => observer.disconnect();
	}, []);

	const tabDocs = openTabs
		.map((tabId) =>
			allDocuments.find((d) => String(d.id) === String(tabId))
		)
		.filter(Boolean);

	// Always include the active tab in the visible set.
	const activeIdx = tabDocs.findIndex(
		(d) => String(d.id) === String(activeDocument?.id)
	);
	let visibleDocs = tabDocs;
	let overflowDocs = [];

	if (tabDocs.length > maxVisible) {
		visibleDocs = tabDocs.slice(0, maxVisible);
		overflowDocs = tabDocs.slice(maxVisible);

		// If active tab is in overflow, swap it into visible.
		if (activeIdx >= maxVisible) {
			const active = tabDocs[activeIdx];
			visibleDocs[maxVisible - 1] = active;
			overflowDocs = tabDocs
				.slice(maxVisible)
				.filter((d) => String(d.id) !== String(active.id));
			overflowDocs.unshift(tabDocs[maxVisible - 1]);
		}
	}

	const renderTab = (doc) => {
		const isActive = String(doc.id) === String(activeDocument?.id);
		if (isEditingTitle && isActive) {
			return (
				<input
					key={doc.id}
					type="text"
					className="wpgraphql-ide-tab-input"
					value={editTitle}
					onChange={(e) => setEditTitle(e.target.value)}
					onBlur={() => {
						if (editTitle.trim()) {
							saveDocument(doc.id, {
								title: editTitle.trim(),
							});
						}
						setIsEditingTitle(false);
					}}
					onKeyDown={(e) => {
						if (e.key === 'Enter') {
							e.target.blur();
						}
						if (e.key === 'Escape') {
							setIsEditingTitle(false);
						}
					}}
					// eslint-disable-next-line jsx-a11y/no-autofocus
					autoFocus
				/>
			);
		}
		return (
			<button
				key={doc.id}
				type="button"
				className={`wpgraphql-ide-tab${isActive ? ' is-active' : ''}`}
				onClick={() => switchTab(String(doc.id))}
				onDoubleClick={() => {
					setEditTitle(doc.title || 'Untitled');
					setIsEditingTitle(true);
				}}
			>
				<span className="wpgraphql-ide-tab-label">
					{doc.title || 'Untitled'}
				</span>
				<span
					className={`wpgraphql-ide-tab-close${openTabs.length <= 1 ? ' is-hidden' : ''}`}
					role="button"
					tabIndex={-1}
					onClick={(e) => {
						e.stopPropagation();
						if (openTabs.length > 1) {
							closeTab(String(doc.id));
						}
					}}
					onKeyDown={(e) => {
						if (e.key === 'Enter' && openTabs.length > 1) {
							e.stopPropagation();
							closeTab(String(doc.id));
						}
					}}
					aria-label="Close tab"
				>
					&times;
				</span>
			</button>
		);
	};

	return (
		<div className="wpgraphql-ide-tab-bar" ref={barRef}>
			{visibleDocs.map(renderTab)}
			{overflowDocs.length > 0 && (
				<DropdownMenu
					icon={null}
					label="More tabs"
					toggleProps={{
						children: `+${overflowDocs.length}`,
						className: 'wpgraphql-ide-tab-overflow',
						size: 'compact',
					}}
				>
					{({ onClose: closeMenu }) => (
						<MenuGroup>
							{overflowDocs.map((doc) => (
								<MenuItem
									key={doc.id}
									onClick={() => {
										switchTab(String(doc.id));
										closeMenu();
									}}
									suffix={
										openTabs.length > 1 ? (
											<Button
												size="small"
												onClick={(e) => {
													e.stopPropagation();
													closeTab(String(doc.id));
												}}
												aria-label="Close tab"
												className="wpgraphql-ide-overflow-close"
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
					)}
				</DropdownMenu>
			)}
			<Tooltip text="New document">
				<Button
					className="wpgraphql-ide-tab-add"
					onClick={() => createTab(getNextTabName())}
					aria-label="New document"
					size="compact"
				>
					<Icon icon={plus} size={16} />
				</Button>
			</Tooltip>
		</div>
	);
}

/**
 * Main IDE layout component.
 *
 * Composes the CodeMirror 6 editors with @wordpress/components to provide a
 * native WordPress admin look and feel. State is managed via the
 * `wpgraphql-ide/app` @wordpress/data store.
 *
 * @param {Object}   props
 * @param {Function} props.fetcher   - GraphQL fetcher function.
 * @param {Function} [props.onClose] - Optional close handler for drawer mode.
 */
export function IDELayout({ fetcher, onClose }) {
	const query = useSelect(
		(select) => select('wpgraphql-ide/app').getQuery() || '',
		[]
	);
	const variables = useSelect(
		(select) => select('wpgraphql-ide/app').getVariables(),
		[]
	);
	const headers = useSelect(
		(select) => select('wpgraphql-ide/app').getHeaders(),
		[]
	);
	const response = useSelect(
		(select) => select('wpgraphql-ide/app').getResponse(),
		[]
	);

	const activeDocument = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getActiveDocument(),
		[]
	);
	const allDocuments = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getDocuments(),
		[]
	);
	const openTabs = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getOpenTabs(),
		[]
	);

	const isAuthenticated = useSelect(
		(select) => select('wpgraphql-ide/app').isAuthenticated(),
		[]
	);
	const responseHeaders = useSelect(
		(select) => select('wpgraphql-ide/app').getResponseHeaders(),
		[]
	);
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
	const extensionTabs = useSelect(
		(select) => select('wpgraphql-ide/response-extensions').extensionTabs(),
		[]
	);

	const {
		setQuery,
		setVariables,
		setHeaders,
		setResponse,
		toggleAuthentication,
		loadHistory,
		addHistoryEntry,
	} = useDispatch('wpgraphql-ide/app');

	const { loadDocuments, saveDocument, createTab, switchTab, closeTab } =
		useDispatch('wpgraphql-ide/document-editor');

	const { schema, isLoading: isSchemaLoading, refetch } = useSchema(fetcher);

	const activeDocRef = useRef(null);
	activeDocRef.current = activeDocument;

	// Capture the document ID and query when execution starts, so the
	// result goes to the correct document even if the user switches tabs.
	const executingDocIdRef = useRef(null);
	const executingQueryRef = useRef(null);
	const executingHeadersRef = useRef(null);

	const handleExecutionComplete = useCallback(
		({
			result,
			duration_ms: duration,
			status: execStatus,
			variables: vars,
		}) => {
			const docId = executingDocIdRef.current;
			const responseStr = JSON.stringify(result, null, 2);

			// Save to global history via CPT.
			addHistoryEntry({
				query: executingQueryRef.current || '',
				variables: vars || '',
				headers: executingHeadersRef.current || '',
				duration_ms: duration,
				status: execStatus,
				document_id: docId || 0,
			});

			// Store response on the document for display.
			if (docId) {
				const { dispatch: dis } =
					// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
					require('@wordpress/data');
				dis('wpgraphql-ide/document-editor').setDocumentResponse(
					docId,
					responseStr
				);
			}
		},
		[addHistoryEntry]
	);

	const executionOptions = useRef({ onComplete: handleExecutionComplete });
	executionOptions.current.onComplete = handleExecutionComplete;

	const { isFetching, run, stop } = useExecution(
		fetcher,
		executionOptions.current
	);

	const savedQueryWidth =
		window.localStorage.getItem('wpgraphql_ide_query_width') || '50%';
	const savedEditorHeight =
		window.localStorage.getItem('wpgraphql_ide_editor_height') || '70%';
	const [queryPaneWidth, setQueryPaneWidth] = useState(savedQueryWidth);
	const [editorHeight, setEditorHeight] = useState(savedEditorHeight);
	const [isLoaded, setIsLoaded] = useState(false);
	const [isEditingTitle, setIsEditingTitle] = useState(false);
	const [editTitle, setEditTitle] = useState('');
	const [responseViewMode, setResponseViewMode] = useState(
		() =>
			window.localStorage.getItem('wpgraphql_ide_response_mode') ||
			'formatted'
	);
	const saveTimerRef = useRef(null);

	// ESC key closes the drawer when in drawer mode.
	useEffect(() => {
		if (!onClose) {
			return;
		}
		const handleKeyDown = (e) => {
			if (e.key === 'Escape') {
				onClose();
			}
		};
		document.addEventListener('keydown', handleKeyDown);
		return () => document.removeEventListener('keydown', handleKeyDown);
	}, [onClose]);

	// Load documents and history after mount.
	useEffect(() => {
		loadDocuments().then(() => setIsLoaded(true));
		loadHistory();
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

	const getNextTabName = useCallback(() => {
		const existing = allDocuments.filter((d) =>
			/^New Tab( \d+)?$/.test(d.title)
		);
		if (existing.length === 0) {
			return 'New Tab';
		}
		const nums = existing.map((d) => {
			const m = d.title.match(/^New Tab (\d+)$/);
			return m ? parseInt(m[1], 10) : 1;
		});
		return `New Tab ${Math.max(...nums) + 1}`;
	}, [allDocuments]);

	// Create a default tab if loaded but no tabs exist.
	useEffect(() => {
		if (isLoaded && !activeDocument) {
			createTab(getNextTabName());
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [isLoaded, activeDocument]);

	// Debounced auto-save. When saving a query, also auto-name the tab
	// from the operation name if the title is still "Untitled".
	const scheduleAutoSave = useCallback(
		(field, value) => {
			if (!activeDocument) {
				return;
			}
			if (saveTimerRef.current) {
				clearTimeout(saveTimerRef.current);
			}
			saveTimerRef.current = setTimeout(() => {
				const data = { [field]: value };

				// Auto-name from operation name when title is default.
				if (
					field === 'query' &&
					/^(Untitled|New Tab( \d+)?)$/.test(activeDocument.title)
				) {
					const match = value.match(
						/(?:query|mutation|subscription)\s+(\w+)/
					);
					if (match) {
						data.title = match[1];
					}
				}

				saveDocument(activeDocument.id, data);
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

	const executeQueryRef = useRef(null);
	executeQueryRef.current = () => {
		if (isFetching) {
			stop();
		} else {
			// Load schema on first execution if not loaded yet.
			if (!schema) {
				refetch();
			}
			// Capture execution context for the correct document.
			executingDocIdRef.current = activeDocument?.id || null;
			executingQueryRef.current = query;
			executingHeadersRef.current = headers;
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
		'docs-explorer': search,
		help,
		history: backup,
	};

	// Filter out documents panel — it's accessed via the header title dropdown.
	const toolPanels = panels.filter((p) => p.name !== 'documents');

	return (
		<div className="wpgraphql-ide-container">
			{/* Tab bar — own row */}
			<div className="wpgraphql-ide-tab-row">
				<TabBar
					openTabs={openTabs}
					allDocuments={allDocuments}
					activeDocument={activeDocument}
					isEditingTitle={isEditingTitle}
					editTitle={editTitle}
					setEditTitle={setEditTitle}
					setIsEditingTitle={setIsEditingTitle}
					saveDocument={saveDocument}
					switchTab={switchTab}
					closeTab={closeTab}
					createTab={createTab}
					getNextTabName={getNextTabName}
				/>
				{onClose && (
					<Tooltip text="Close">
						<Button
							onClick={onClose}
							aria-label="Close drawer"
							size="compact"
							className="wpgraphql-ide-close-btn"
						>
							<Icon icon={close} />
						</Button>
					</Tooltip>
				)}
			</div>
			{/* Main content area with vertical activity bar */}
			<div className="wpgraphql-ide-body">
				<div className="wpgraphql-ide-activity-bar-vertical">
					{toolPanels.map((panel) => (
						<Tooltip
							key={panel.name}
							text={panel.title}
							placement="right"
						>
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
					<div className="wpgraphql-ide-activity-bar-spacer" />
					<Tooltip text="Re-fetch schema" placement="right">
						<Button
							onClick={refetch}
							disabled={isSchemaLoading}
							aria-label="Re-fetch schema"
							size="compact"
							className={`wpgraphql-ide-refresh-button${isSchemaLoading ? ' is-loading' : ''}`}
						>
							<Icon icon={update} />
						</Button>
					</Tooltip>
				</div>
				<div className="wpgraphql-ide-main">
					<ActivityPanel />
					<ResizableBox
						size={{ width: queryPaneWidth, height: 'auto' }}
						minWidth={200}
						enable={{ right: true }}
						onResizeStop={(e, d, elt) => {
							const w = elt.offsetWidth;
							setQueryPaneWidth(w);
							window.localStorage.setItem(
								'wpgraphql_ide_query_width',
								String(w)
							);
						}}
						className="wpgraphql-ide-query-pane"
					>
						<div className="wpgraphql-ide-editor-toolbar">
							<span className="wpgraphql-ide-editor-label">
								Query
							</span>
							<div className="wpgraphql-ide-editor-toolbar-spacer" />
							<DropdownMenu
								icon={moreVertical}
								label="Editor actions"
							>
								{({ onClose: closeMenu }) => (
									<MenuGroup>
										<EditorToolbar onClose={closeMenu} />
									</MenuGroup>
								)}
							</DropdownMenu>
							<div className="wpgraphql-ide-send-group">
								<span className="wpgraphql-ide-method-label">
									POST
								</span>
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
											<span
												className={authStyles.authBadge}
											/>
										</span>
									</button>
								</Tooltip>
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
						<ResizableBox
							size={{ width: '100%', height: editorHeight }}
							minHeight={50}
							enable={{ bottom: true }}
							onResizeStop={(e, d, elt) => {
								const h = elt.offsetHeight;
								setEditorHeight(h);
								window.localStorage.setItem(
									'wpgraphql_ide_editor_height',
									String(h)
								);
							}}
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
							{!isFetching && responseStatus !== null && (
								<span className="wpgraphql-ide-response-meta">
									<span
										className={`wpgraphql-ide-response-status wpgraphql-ide-response-status--${responseStatus >= 200 && responseStatus < 300 ? 'success' : 'error'}`}
									>
										{responseStatus}
									</span>
									{responseDuration !== null && (
										<span className="wpgraphql-ide-response-duration">
											{responseDuration >= 1000
												? `${(responseDuration / 1000).toFixed(1)}s`
												: `${responseDuration}ms`}
										</span>
									)}
									{responseSize !== null && (
										<span className="wpgraphql-ide-response-size">
											{responseSize >= 1024
												? `${(responseSize / 1024).toFixed(1)}KB`
												: `${responseSize}B`}
										</span>
									)}
								</span>
							)}
							<div className="wpgraphql-ide-response-mode-toggle">
								{['formatted', 'table', 'raw'].map((mode) => (
									<button
										key={mode}
										type="button"
										className={`wpgraphql-ide-response-mode-btn${responseViewMode === mode ? ' is-active' : ''}`}
										onClick={() => {
											setResponseViewMode(mode);
											window.localStorage.setItem(
												'wpgraphql_ide_response_mode',
												mode
											);
										}}
									>
										{mode.charAt(0).toUpperCase() +
											mode.slice(1)}
									</button>
								))}
							</div>
						</div>
						<ResponseContent
							response={response}
							responseViewMode={responseViewMode}
							responseHeaders={responseHeaders}
							extensionTabs={extensionTabs}
						/>
					</div>
				</div>
			</div>
		</div>
	);
}
