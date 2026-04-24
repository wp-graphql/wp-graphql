import React, { useState, useCallback, useEffect, useRef } from 'react';
import {
	Button,
	DropdownMenu,
	MenuGroup,
	ResizableBox,
	SnackbarList,
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
	listView,
	moreVertical,
	close,
	search,
	settings,
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
import { useSchema } from '../hooks/useSchema';
import { useExecution } from '../hooks/useExecution';

// eslint-disable-next-line jsdoc/require-param
function ResponseContent({
	response,
	responseViewMode,
	responseHeaders,
	extensionTabs,
	responseViewerHeight,
	onResponseViewerResize,
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

	return (
		<div className="wpgraphql-ide-response-formatted">
			<ResizableBox
				size={{ width: '100%', height: responseViewerHeight }}
				minHeight={50}
				enable={{ bottom: true }}
				onResizeStop={(e, d, elt) => {
					onResponseViewerResize(elt.offsetHeight);
				}}
				className="wpgraphql-ide-response-data wpgraphql-ide-resizable-split"
			>
				<ResponseViewer value={dataStr || response} />
			</ResizableBox>
			<TabPanel className="wpgraphql-ide-response-tabs" tabs={bottomTabs}>
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

	const {
		loadDocuments,
		saveTab,
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
		250;
	const [queryPaneWidth, setQueryPaneWidth] = useState(savedQueryWidth);
	const [editorHeight, setEditorHeight] = useState(savedEditorHeight);
	const [responseViewerHeight, setResponseViewerHeight] = useState(
		Number(savedResponseViewerHeight)
	);
	const [isLoaded, setIsLoaded] = useState(false);
	const [responseViewMode, setResponseViewMode] = useState(
		() =>
			window.localStorage.getItem('wpgraphql_ide_response_mode') ||
			'formatted'
	);
	const [notices, setNotices] = useState([]);

	const addNotice = useCallback((content, type = 'default') => {
		const id = `notice-${Date.now()}`;
		setNotices((prev) => [...prev, { id, content, type }]);
	}, []);

	const removeNotice = useCallback((id) => {
		setNotices((prev) => prev.filter((n) => n.id !== id));
	}, []);

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

	const handleQueryChange = useCallback(
		(value) => {
			setQuery(value);
			if (activeDocument) {
				setDocumentDirty(activeDocument.id, true);
			}
		},
		[setQuery, activeDocument, setDocumentDirty]
	);

	const handleVariablesChange = useCallback(
		(value) => {
			setVariables(value);
			if (activeDocument) {
				setDocumentDirty(activeDocument.id, true);
			}
		},
		[setVariables, activeDocument, setDocumentDirty]
	);

	const handleHeadersChange = useCallback(
		(value) => {
			setHeaders(value);
			if (activeDocument) {
				setDocumentDirty(activeDocument.id, true);
			}
		},
		[setHeaders, activeDocument, setDocumentDirty]
	);

	// Explicit save — Cmd+S / Save button.
	const saveCurrentDoc = useCallback(async () => {
		if (!activeDocument) {
			return;
		}
		try {
			await saveTab(activeDocument.id, {
				query,
				variables,
				headers,
			});
			addNotice('Document saved');
		} catch {
			addNotice('Failed to save document', 'error');
		}
	}, [activeDocument, query, variables, headers, saveTab, addNotice]);

	const saveCurrentDocRef = useRef(null);
	saveCurrentDocRef.current = saveCurrentDoc;

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
	const navPanels = panels.filter(
		(p) => p.name !== 'documents' && p.name !== 'query-composer'
	);

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

	// Settings URL — links to WPGraphQL general settings in WP admin.
	const settingsUrl =
		typeof window !== 'undefined' && window.wpApiSettings?.root
			? window.location.origin +
				'/wp-admin/admin.php?page=graphql-settings'
			: '/wp-admin/admin.php?page=graphql-settings';

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
							onClick={refetch}
							disabled={isSchemaLoading}
							aria-label="Re-fetch schema"
							size="compact"
							className={`wpgraphql-ide-topbar-btn${isSchemaLoading ? ' is-loading' : ''}`}
						>
							<Icon icon={update} />
						</Button>
					</Tooltip>
					<Tooltip text="WPGraphQL Settings">
						<Button
							href={settingsUrl}
							aria-label="WPGraphQL Settings"
							size="compact"
							className="wpgraphql-ide-topbar-btn"
						>
							<Icon icon={settings} />
						</Button>
					</Tooltip>
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
								<Icon icon={PANEL_ICONS[panel.name] ?? edit} />
							</Button>
						</Tooltip>
					))}
				</div>

				{/* Collapsible side panel — global, shows active panel content */}
				<ActivityPanel />

				{/* Editor area: tabs + editors are document-scoped */}
				<div className="wpgraphql-ide-editor-area">
					<div className="wpgraphql-ide-tab-row">
						<DocumentTabs
							tabs={openTabs
								.map((tabId) =>
									allDocuments.find(
										(d) => String(d.id) === String(tabId)
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
							onRename={(id, title) =>
								saveDocument(id, { title })
							}
						/>
					</div>

					<div className="wpgraphql-ide-editors">
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
								{ComposerContent && (
									<Tooltip
										text={
											showQueryComposer
												? 'Hide Query Composer'
												: 'Show Query Composer'
										}
									>
										<Button
											onClick={toggleQueryComposer}
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
										<MenuGroup>
											<EditorToolbar
												onClose={closeMenu}
											/>
										</MenuGroup>
									)}
								</DropdownMenu>
								<div className="wpgraphql-ide-editor-toolbar-spacer" />
								<Tooltip text="Save (Cmd+S)">
									<Button
										onClick={saveCurrentDoc}
										disabled={!activeDocument?.dirty}
										size="compact"
										className={`wpgraphql-ide-save-button${activeDocument?.dirty ? ' is-dirty' : ''}`}
									>
										Save
									</Button>
								</Tooltip>
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
								{ComposerContent && showQueryComposer && (
									<div className="wpgraphql-ide-query-composer-inline">
										<ComposerContent />
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
									{
										name: 'variables',
										title: 'Variables',
									},
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
									{['formatted', 'table', 'raw'].map(
										(mode) => (
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
										)
									)}
								</div>
							</div>
							<ResponseContent
								response={response}
								responseViewMode={responseViewMode}
								responseHeaders={responseHeaders}
								extensionTabs={extensionTabs}
								responseViewerHeight={responseViewerHeight}
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
					{/* end .wpgraphql-ide-editors */}
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
