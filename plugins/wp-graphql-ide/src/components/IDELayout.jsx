import React, { useState, useCallback, useEffect, useRef } from 'react';
import {
	Button,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	ResizableBox,
	SnackbarList,
	TabPanel,
	Spinner,
	Tooltip,
} from '@wordpress/components';
import {
	Icon,
	edit,
	file,
	help,
	backup,
	update,
	listView,
	moreVertical,
	close,
	search,
	sidebar,
} from '@wordpress/icons';
import { useDispatch, useSelect } from '@wordpress/data';
import { GraphQLEditor } from './editors/GraphQLEditor';
import { JSONEditor } from './editors/JSONEditor';
import { ResponseViewer } from './editors/ResponseViewer';
import { ErrorsPanel } from './ErrorsPanel';
import { HeadersPanel } from './HeadersPanel';
import { ResponseTableView } from './ResponseTableView';
import { EditorToolbar } from './EditorToolbar';
import { DocumentTabs } from './DocumentTabs';
import ActivityPanel from './ActivityPanel';
import authStyles from '../../styles/ToggleAuthenticationButton.module.css';
import hooks from '../wordpress-hooks';
import { useSchema } from '../hooks/useSchema';
import { useExecution } from '../hooks/useExecution';

// eslint-disable-next-line jsdoc/require-param
function ResponseContent({
	response,
	responseViewMode,
	responseDataScope,
	responseHeaders,
	extensionTabs,
	responseViewerHeight,
	onResponseViewerResize,
}) {
	const parsed = React.useMemo(() => {
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

	// Determine what to show in the viewer based on scope.
	const viewerContent = React.useMemo(() => {
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

	// Render the viewer based on view mode.
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

	return (
		<div className="wpgraphql-ide-response-body">
			{/* Top: response viewer — resizable, matching query editor split */}
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
			{/* Bottom: always-visible tabs for Headers/Errors/Extensions */}
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

const PANEL_ICONS = {
	'saved-queries': file,
	'docs-explorer': search,
	help,
	history: backup,
};

/**
 * Main IDE layout component.
 *
 * Layout follows Jason's scoping principle:
 * - Global controls (activity bar, sidebar panels) are on the left
 * - Document-scoped controls (tabs, auth toggle, query composer, send) are
 *   nested inside the editor area, visually tied to the active document
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
	const activeTabType = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getActiveTabType(),
		[]
	);
	const tabTypes = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getTabTypes(),
		[]
	);
	const topbarActions = useSelect(
		(select) => select('wpgraphql-ide/document-editor').getTopbarActions(),
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
	const httpMethod = useSelect(
		(select) => select('wpgraphql-ide/app').getHttpMethod(),
		[]
	);

	const {
		setQuery,
		setVariables,
		setHeaders,
		setResponse,
		toggleAuthentication,
		setHttpMethod,
		loadHistory,
		addHistoryEntry,
		setDocsNavTarget,
	} = useDispatch('wpgraphql-ide/app');

	const {
		loadDocuments,
		saveTab,
		publishTab,
		saveDocument,
		createTab,
		switchTab,
		closeTab,
		setDocumentDirty,
	} = useDispatch('wpgraphql-ide/document-editor');

	const { schema, isLoading: isSchemaLoading, refetch } = useSchema(fetcher);

	const activeDocRef = useRef(null);
	activeDocRef.current = activeDocument;

	// Capture the document ID and query when execution starts, so the
	// result goes to the correct document even if the user switches tabs.
	const executingDocIdRef = useRef(null);
	const executingQueryRef = useRef(null);
	const executingHeadersRef = useRef(null);
	const executingAuthRef = useRef(true);
	const executingMethodRef = useRef('POST');

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
				is_authenticated: executingAuthRef.current,
				http_method: executingMethodRef.current || 'POST',
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
	const savedResponseViewerHeight =
		window.localStorage.getItem('wpgraphql_ide_response_viewer_height') ||
		'50%';
	const [queryPaneWidth, setQueryPaneWidth] = useState(savedQueryWidth);
	const [editorHeight, setEditorHeight] = useState(savedEditorHeight);
	const [responseViewerHeight, setResponseViewerHeight] = useState(
		savedResponseViewerHeight
	);
	const [responseDataScope, setResponseDataScope] = useState('data');
	const [responseViewMode, setResponseViewMode] = useState(
		() =>
			window.localStorage.getItem('wpgraphql_ide_response_mode') ||
			'formatted'
	);
	const saveTimerRef = useRef(null);
	const [notices, setNotices] = useState([]);

	const addNotice = useCallback((content, type = 'default') => {
		const id = `notice-${Date.now()}`;
		setNotices((prev) => [...prev, { id, content, type }]);
	}, []);

	const removeNotice = useCallback((id) => {
		setNotices((prev) => prev.filter((n) => n.id !== id));
	}, []);

	// Listen for notice events from extensions via hooks. The optional
	// `type` arg lets callers raise error/warning notices without prop
	// drilling addNotice everywhere.
	useEffect(() => {
		const hookName = 'wpgraphql-ide.notice';
		const ns = 'wpgraphql-ide/layout';
		const handler = (content, type = 'default') => addNotice(content, type);
		hooks.addAction(hookName, ns, handler);
		return () => hooks.removeAction(hookName, ns);
	}, [addNotice]);

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
		loadDocuments();
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

	// Check if any editor content differs from the saved document.
	const checkDirty = useCallback(
		(nextQuery, nextVars, nextHeaders) => {
			if (!activeDocument) {
				return;
			}
			const isDirty =
				nextQuery !== (activeDocument.query || '') ||
				nextVars !== (activeDocument.variables || '') ||
				nextHeaders !== (activeDocument.headers || '');
			setDocumentDirty(activeDocument.id, isDirty);
		},
		[activeDocument, setDocumentDirty]
	);

	// Auto-save drafts after 2 seconds of inactivity.
	const scheduleAutoSave = useCallback(
		(field, value) => {
			if (
				!activeDocument ||
				String(activeDocument.id).startsWith('temp-')
			) {
				return;
			}
			if (saveTimerRef.current) {
				clearTimeout(saveTimerRef.current);
			}
			saveTimerRef.current = setTimeout(() => {
				saveDocument(activeDocument.id, { [field]: value });
			}, 2000);
		},
		[activeDocument, saveDocument]
	);

	const handleQueryChange = useCallback(
		(value) => {
			setQuery(value);
			checkDirty(value, variables, headers);
			scheduleAutoSave('query', value);
		},
		[setQuery, checkDirty, variables, headers, scheduleAutoSave]
	);

	const handleVariablesChange = useCallback(
		(value) => {
			setVariables(value);
			checkDirty(query, value, headers);
			scheduleAutoSave('variables', value);
		},
		[setVariables, checkDirty, query, headers, scheduleAutoSave]
	);

	const handleHeadersChange = useCallback(
		(value) => {
			setHeaders(value);
			checkDirty(query, variables, value);
			scheduleAutoSave('headers', value);
		},
		[setHeaders, checkDirty, query, variables, scheduleAutoSave]
	);

	// Explicit save — Cmd+S / Save button.
	const saveCurrentDoc = useCallback(async () => {
		if (!activeDocument) {
			return;
		}
		try {
			// Prompt for a name if the tab still has a generic title.
			const isGenericName = /^New Tab( \d+)?$/.test(activeDocument.title);
			let title;
			if (isGenericName) {
				// eslint-disable-next-line no-alert
				const input = window.prompt(
					'Name this document:',
					activeDocument.title
				);
				title = input?.trim() || undefined;
			}

			await saveTab(activeDocument.id, {
				query,
				variables,
				headers,
				...(title ? { title } : {}),
			});
			addNotice('Document saved');
		} catch {
			addNotice('Failed to save document', 'error');
		}
	}, [activeDocument, query, variables, headers, saveTab, addNotice]);

	const saveCurrentDocRef = useRef(null);
	saveCurrentDocRef.current = saveCurrentDoc;

	// Publish the current document (draft → published with hash).
	const publishCurrentDoc = useCallback(async () => {
		if (!activeDocument || String(activeDocument.id).startsWith('temp-')) {
			return;
		}
		// Save first to ensure content is persisted.
		try {
			await saveTab(activeDocument.id, {
				query,
				variables,
				headers,
			});
			const result = await publishTab(activeDocument.id);
			if (result?.already_exists) {
				addNotice('This query is already published');
			} else {
				addNotice('Document published');
			}
		} catch {
			addNotice('Failed to publish document', 'error');
		}
	}, [
		activeDocument,
		query,
		variables,
		headers,
		saveTab,
		publishTab,
		addNotice,
	]);

	// Whether the active document is published (immutable query).
	const isPublished = activeDocument?.status === 'publish';
	const isTempDoc = activeDocument
		? String(activeDocument.id).startsWith('temp-')
		: true;
	const isSavedDraft =
		activeDocument && !isTempDoc && activeDocument.status !== 'publish';

	// Close tab with confirmation for dirty documents.
	const handleCloseTab = useCallback(
		(tabId) => {
			const doc = allDocuments.find(
				(d) => String(d.id) === String(tabId)
			);
			if (doc?.dirty) {
				// eslint-disable-next-line no-alert
				const answer = window.confirm(
					`Save changes to "${doc.title || 'Untitled'}" before closing?`
				);
				if (answer) {
					saveTab(tabId, {
						query: doc.query || query,
						variables: doc.variables || variables,
						headers: doc.headers || headers,
					}).then(() => closeTab(tabId));
					return;
				}
			}
			closeTab(tabId);
		},
		[allDocuments, closeTab, saveTab, query, variables, headers]
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
			executingAuthRef.current = isAuthenticated;
			executingMethodRef.current = httpMethod;
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
			key: 'Mod-s',
			run: () => {
				saveCurrentDocRef.current();
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

	const { toggleActivityPanelVisibility, setVisiblePanel } = useDispatch(
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

	// Global panels for the activity bar — exclude document-scoped panels.
	// Query composer is per-document (rendered inline in the editor area).
	// Documents panel is accessed via tabs, not the activity bar.
	const navPanels = panels.filter((p) => p.name !== 'query-composer');

	// Query composer panel — rendered inline within the document/editor area.
	const queryComposerPanel = panels.find((p) => p.name === 'query-composer');
	const ComposerContent = queryComposerPanel?.content || null;

	const [showQueryComposer, setShowQueryComposer] = useState(() => {
		try {
			return (
				window.localStorage.getItem(
					'wpgraphql_ide_show_query_composer'
				) === 'true'
			);
		} catch {
			return false;
		}
	});

	const toggleQueryComposer = () => {
		setShowQueryComposer((prev) => {
			const next = !prev;
			try {
				window.localStorage.setItem(
					'wpgraphql_ide_show_query_composer',
					String(next)
				);
			} catch {
				// ignore
			}
			return next;
		});
	};

	// Remember the last open panel so the sidebar toggle can restore it.
	const lastPanelRef = useRef(null);
	useEffect(() => {
		if (visiblePanel) {
			lastPanelRef.current = visiblePanel.name;
		}
	}, [visiblePanel]);

	const handleSidebarToggle = () => {
		if (visiblePanel) {
			toggleActivityPanelVisibility(visiblePanel.name);
		} else {
			const target = lastPanelRef.current || navPanels[0]?.name;
			if (target) {
				setVisiblePanel(target);
			}
		}
	};

	// Cmd/ctrl-click on an identifier in the editor: open the Docs panel and
	// hand it a navigation target through the app store. We use a store-backed
	// "one-shot" target rather than a hook event so that mount-vs-event timing
	// can't drop the request: the panel reads the value via useSelect, pushes
	// onto its stack when it appears, and dispatches the target back to null.
	const handleShowInDocs = useCallback(
		(field, type, parentType) => {
			// cm6-graphql hands us types via `.toString()`, which includes
			// non-null (`User!`) and list (`[Post]`) wrappers. The schema's
			// `getType()` only resolves named types, so we have to unwrap.
			const unwrap = (name) =>
				name ? name.replace(/[[\]!]/g, '') : null;

			// When the user clicks a field, jump to the field's parent type
			// so the field is in context. When clicking a type literal, jump
			// to that type directly.
			let targetType;
			let targetField = null;
			if (field && parentType) {
				targetType = unwrap(parentType);
				targetField = field;
			} else {
				targetType = unwrap(type) || unwrap(parentType);
			}
			if (!targetType) {
				return;
			}
			setDocsNavTarget({
				typeName: targetType,
				fieldName: targetField,
			});
			setVisiblePanel('docs-explorer');
		},
		[setVisiblePanel, setDocsNavTarget]
	);

	return (
		<div className="wpgraphql-ide-container">
			{/* Global top bar */}
			<div className="wpgraphql-ide-topbar">
				<div className="wpgraphql-ide-topbar-left">
					<Tooltip
						text={
							visiblePanel ? 'Collapse sidebar' : 'Expand sidebar'
						}
					>
						<Button
							onClick={handleSidebarToggle}
							aria-label={
								visiblePanel
									? 'Collapse sidebar'
									: 'Expand sidebar'
							}
							size="compact"
							className={`wpgraphql-ide-topbar-btn${visiblePanel ? ' is-active' : ''}`}
						>
							<Icon icon={sidebar} />
						</Button>
					</Tooltip>
				</div>
				<div className="wpgraphql-ide-topbar-center">
					<span className="wpgraphql-ide-topbar-title">
						WPGraphQL
					</span>
				</div>
				<div className="wpgraphql-ide-topbar-right">
					<Tooltip text="Re-fetch schema">
						<Button
							onClick={() => {
								refetch();
								addNotice('Schema refreshed');
							}}
							disabled={isSchemaLoading}
							aria-label="Re-fetch schema"
							size="compact"
							className={`wpgraphql-ide-topbar-btn${isSchemaLoading ? ' is-loading' : ''}`}
						>
							<Icon icon={update} />
						</Button>
					</Tooltip>
					{topbarActions.length > 0 && (
						<>
							<div className="wpgraphql-ide-topbar-sep" />
							{topbarActions.map((action) => (
								<Tooltip key={action.name} text={action.title}>
									<Button
										onClick={() =>
											window.WPGraphQLIDE?.openWorkspaceTab(
												action.tabType,
												{
													id: action.tabId,
													title: action.title,
												}
											)
										}
										aria-label={action.title}
										size="compact"
										className="wpgraphql-ide-topbar-btn"
									>
										{action.icon ? (
											<action.icon />
										) : (
											<Icon icon={edit} />
										)}
									</Button>
								</Tooltip>
							))}
						</>
					)}
					{onClose && (
						<>
							<div className="wpgraphql-ide-topbar-sep" />
							<Tooltip text="Close">
								<Button
									onClick={onClose}
									aria-label="Close"
									size="compact"
									className="wpgraphql-ide-topbar-btn"
								>
									<Icon icon={close} />
								</Button>
							</Tooltip>
						</>
					)}
				</div>
			</div>

			<div className="wpgraphql-ide-main">
				{/* Vertical activity bar — global, owns panel toggle buttons */}
				<div className="wpgraphql-ide-activity-bar">
					{navPanels.map((panel) => (
						<Tooltip key={panel.name} text={panel.title}>
							<Button
								onClick={() =>
									toggleActivityPanelVisibility(panel.name)
								}
								aria-label={panel.title}
								size="compact"
								className={`wpgraphql-ide-activity-btn${visiblePanel?.name === panel.name ? ' is-active' : ''}`}
							>
								{panel.icon ? (
									<panel.icon />
								) : (
									<Icon
										icon={PANEL_ICONS[panel.name] ?? edit}
									/>
								)}
							</Button>
						</Tooltip>
					))}
				</div>

				{/* Collapsible side panel — global, shows active panel content */}
				<ActivityPanel />

				{/* Editor area: tabs + editors are document-scoped */}
				<div className="wpgraphql-ide-editor-area">
					{openTabs.length === 0 ? (
						<div className="wpgraphql-ide-workspace-empty">
							<p>No open documents</p>
							<p>
								Open a document from the sidebar, or create a
								new one.
							</p>
							<Button
								variant="primary"
								onClick={() => createTab(getNextTabName())}
							>
								New Document
							</Button>
						</div>
					) : (
						<>
							<div className="wpgraphql-ide-tab-row">
								<DocumentTabs
									tabs={openTabs
										.map((tabId) =>
											allDocuments.find(
												(d) =>
													String(d.id) ===
													String(tabId)
											)
										)
										.filter(Boolean)
										.map((doc) => ({
											id: doc.id,
											title: doc.title || 'Untitled',
											dirty: !!doc.dirty,
										}))}
									activeId={activeDocument?.id}
									onSwitch={(id) => switchTab(id)}
									onClose={(id) => handleCloseTab(id)}
									onCreate={() => createTab(getNextTabName())}
									onRename={(id, title) => {
										saveDocument(id, { title });
										if (String(id).startsWith('temp-')) {
											setDocumentDirty(id, true);
										}
									}}
								/>
							</div>

							{activeTabType &&
							activeTabType !== 'query-editor' &&
							tabTypes[activeTabType] ? (
								<div className="wpgraphql-ide-workspace-tab-content">
									{React.createElement(
										tabTypes[activeTabType].content
									)}
								</div>
							) : (
								<div className="wpgraphql-ide-editors">
									<ResizableBox
										size={{
											width: queryPaneWidth,
											height: 'auto',
										}}
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
											{ComposerContent && (
												<Tooltip
													text={
														showQueryComposer
															? 'Hide Query Composer'
															: 'Show Query Composer'
													}
												>
													<Button
														onClick={
															toggleQueryComposer
														}
														aria-label={
															showQueryComposer
																? 'Hide Query Composer'
																: 'Show Query Composer'
														}
														size="compact"
														className={`wpgraphql-ide-toolbar-composer-btn${showQueryComposer ? ' is-active' : ''}`}
													>
														<Icon icon={listView} />
													</Button>
												</Tooltip>
											)}
											<span className="wpgraphql-ide-editor-label">
												Query
											</span>
											<DropdownMenu
												icon={moreVertical}
												label="Editor actions"
											>
												{({ onClose: closeMenu }) => (
													<>
														<MenuGroup>
															<EditorToolbar
																onClose={
																	closeMenu
																}
																onNotice={
																	addNotice
																}
															/>
														</MenuGroup>
														{isPublished && (
															<MenuGroup>
																<MenuItem
																	onClick={() => {
																		closeMenu();
																		createTab(
																			`${activeDocument?.title || 'Untitled'} (copy)`
																		).then(
																			() => {
																				setQuery(
																					query
																				);
																				setVariables(
																					variables
																				);
																				setHeaders(
																					headers
																				);
																				addNotice(
																					'Draft copy created'
																				);
																			}
																		);
																	}}
																>
																	Duplicate as
																	draft
																</MenuItem>
															</MenuGroup>
														)}
													</>
												)}
											</DropdownMenu>
											<div className="wpgraphql-ide-editor-toolbar-spacer" />
											{!isPublished && (
												<>
													<Button
														onClick={saveCurrentDoc}
														disabled={
															!activeDocument?.dirty
														}
														size="compact"
														className={`wpgraphql-ide-save-button${activeDocument?.dirty ? ' is-dirty' : ''}`}
													>
														Save draft
													</Button>
													{isSavedDraft && (
														<Button
															onClick={
																publishCurrentDoc
															}
															size="compact"
															variant="primary"
															className="wpgraphql-ide-publish-button"
														>
															Publish
														</Button>
													)}
												</>
											)}
										</div>
										<ResizableBox
											size={{
												width: '100%',
												height: editorHeight,
											}}
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
											className={`wpgraphql-ide-editor-resizable wpgraphql-ide-resizable-split${showQueryComposer && ComposerContent ? ' has-composer' : ''}`}
										>
											{ComposerContent &&
												showQueryComposer && (
													<div className="wpgraphql-ide-query-composer-inline">
														<ComposerContent />
													</div>
												)}
											<GraphQLEditor
												key={
													activeDocument?.id ||
													'empty'
												}
												value={query}
												onChange={handleQueryChange}
												schema={schema}
												readOnly={isPublished}
												extraKeys={
													editorKeyBindings.current
												}
												onShowInDocs={handleShowInDocs}
											/>
											<div className="wpgraphql-ide-execution-pill">
												<div
													className="wpgraphql-ide-response-mode-toggle"
													role="group"
													aria-label="HTTP method"
												>
													{['GET', 'POST'].map(
														(m) => (
															<button
																key={m}
																type="button"
																aria-pressed={
																	httpMethod ===
																	m
																}
																className={`wpgraphql-ide-response-mode-btn${httpMethod === m ? ' is-active' : ''}`}
																onClick={() =>
																	setHttpMethod(
																		m
																	)
																}
															>
																{m}
															</button>
														)
													)}
												</div>
												<Tooltip
													text={
														isAuthenticated
															? 'Authenticated (click to switch)'
															: 'Public (click to switch)'
													}
												>
													<button
														type="button"
														onClick={
															toggleAuthentication
														}
														className={`wpgraphql-ide-auth-avatar ${!isAuthenticated ? authStyles.authAvatarPublic : ''}`}
														aria-label={
															isAuthenticated
																? 'Switch to public'
																: 'Switch to authenticated'
														}
													>
														<span
															className={
																authStyles.authAvatar
															}
															style={{
																backgroundImage: `url(${window.WPGRAPHQL_IDE_DATA?.context?.avatarUrl || ''})`,
															}}
														>
															<span
																className={
																	authStyles.authBadge
																}
															/>
														</span>
													</button>
												</Tooltip>
												<Tooltip
													text={
														isFetching
															? 'Stop (Cmd+Enter)'
															: 'Execute (Cmd+Enter)'
													}
												>
													<Button
														variant="primary"
														onClick={executeQuery}
														disabled={
															isSchemaLoading
														}
														className="wpgraphql-ide-send-button"
														size="compact"
														aria-label={
															isFetching
																? 'Stop execution'
																: 'Execute query'
														}
													>
														{isFetching ? (
															<svg
																viewBox="0 0 24 24"
																width="16"
																height="16"
																fill="currentColor"
															>
																<rect
																	x="6"
																	y="6"
																	width="12"
																	height="12"
																	rx="1"
																/>
															</svg>
														) : (
															<svg
																viewBox="0 0 24 24"
																width="16"
																height="16"
																fill="currentColor"
															>
																<path d="M8 5v14l11-7z" />
															</svg>
														)}
													</Button>
												</Tooltip>
											</div>
										</ResizableBox>
										<TabPanel
											className="wpgraphql-ide-editor-tools"
											tabs={[
												{
													name: 'variables',
													title: 'Variables',
												},
												{
													name: 'headers',
													title: 'Headers',
												},
											]}
										>
											{(tab) =>
												tab.name === 'variables' ? (
													<JSONEditor
														key="variables"
														value={variables}
														onChange={
															handleVariablesChange
														}
														placeholder="Variables (JSON)"
													/>
												) : (
													<JSONEditor
														key="headers"
														value={headers}
														onChange={
															handleHeadersChange
														}
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
											<DropdownMenu
												icon={moreVertical}
												label="Response options"
											>
												{({ onClose: closeMenu }) => (
													<MenuGroup>
														<MenuItem
															onClick={() => {
																setResponseDataScope(
																	'data'
																);
																closeMenu();
															}}
															isSelected={
																responseDataScope ===
																'data'
															}
														>
															Show data only
														</MenuItem>
														<MenuItem
															onClick={() => {
																setResponseDataScope(
																	'full'
																);
																closeMenu();
															}}
															isSelected={
																responseDataScope ===
																'full'
															}
														>
															Show full response
														</MenuItem>
													</MenuGroup>
												)}
											</DropdownMenu>
											<div className="wpgraphql-ide-editor-toolbar-spacer" />
											{isFetching && <Spinner />}
											{!isFetching &&
												responseStatus !== null && (
													<span className="wpgraphql-ide-response-meta">
														<span
															className={`wpgraphql-ide-response-status wpgraphql-ide-response-status--${responseStatus >= 200 && responseStatus < 300 ? 'success' : 'error'}`}
														>
															{responseStatus}
														</span>
														{responseDuration !==
															null && (
															<span className="wpgraphql-ide-response-duration">
																{responseDuration >=
																1000
																	? `${(responseDuration / 1000).toFixed(1)}s`
																	: `${responseDuration}ms`}
															</span>
														)}
														{responseSize !==
															null && (
															<span className="wpgraphql-ide-response-size">
																{responseSize >=
																1024
																	? `${(responseSize / 1024).toFixed(1)}KB`
																	: `${responseSize}B`}
															</span>
														)}
													</span>
												)}
											<div
												className="wpgraphql-ide-response-mode-toggle"
												role="group"
												aria-label="View format"
											>
												{[
													{
														value: 'formatted',
														label: 'JSON',
													},
													{
														value: 'table',
														label: 'Table',
													},
												].map((opt) => (
													<button
														key={opt.value}
														type="button"
														aria-pressed={
															responseViewMode ===
															opt.value
														}
														className={`wpgraphql-ide-response-mode-btn${responseViewMode === opt.value ? ' is-active' : ''}`}
														onClick={() => {
															setResponseViewMode(
																opt.value
															);
															window.localStorage.setItem(
																'wpgraphql_ide_response_mode',
																opt.value
															);
														}}
													>
														{opt.label}
													</button>
												))}
											</div>
										</div>
										<ResponseContent
											response={response}
											responseViewMode={responseViewMode}
											responseDataScope={
												responseDataScope
											}
											responseHeaders={responseHeaders}
											extensionTabs={extensionTabs}
											responseViewerHeight={
												responseViewerHeight
											}
											onResponseViewerResize={(h) => {
												setResponseViewerHeight(h);
												window.localStorage.setItem(
													'wpgraphql_ide_response_viewer_height',
													String(h)
												);
											}}
										/>
									</div>
								</div>
							)}
						</>
					)}
				</div>
				{/* end .wpgraphql-ide-editor-area */}
			</div>
			{/* end .wpgraphql-ide-main */}
			{notices.length > 0 && (
				<SnackbarList
					notices={notices}
					onRemove={removeNotice}
					className="wpgraphql-ide-snackbar-list"
				/>
			)}
		</div>
	);
}
