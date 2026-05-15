import React, { useState, useCallback, useEffect, useRef } from 'react';
import { parse as parseGraphQL, validate as validateGraphQL } from 'graphql';
import { __, sprintf } from '@wordpress/i18n';
import { SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect, select as wpSelect } from '@wordpress/data';
import { ShareDialog } from './dialogs/ShareDialog';
import { SaveDialog } from './dialogs/SaveDialog';
import { DocumentTabs } from './DocumentTabs';
import ActivityPanel from './ActivityPanel';
import { useDialog } from './dialogs/DialogProvider';
import { EditorPane } from './ide-layout/EditorPane';
import { IDEActivityBar } from './ide-layout/IDEActivityBar';
import { IDETopbar } from './ide-layout/IDETopbar';
import { ResponsePane } from './ide-layout/ResponsePane';
import { WorkspaceEmpty } from './ide-layout/WorkspaceEmpty';
import { useSchema } from '../hooks/useSchema';
import { useExecution } from '../hooks/useExecution';
import { useAutoSave } from '../hooks/useAutoSave';
import { useDocumentDirty } from '../hooks/useDocumentDirty';
import { useNotices } from '../hooks/useNotices';
import { useLeftPanel } from '../hooks/useLeftPanel';
import { useParsedQuery } from '../hooks/useParsedQuery';
import { usePanelOrder } from '../hooks/usePanelOrder';
import { usePersistedSize } from '../hooks/usePersistedSize';
import { readDevicePreference, setPreference } from '../api/preferences';
import {
	endpointMode,
	isUserLoggedIn,
	loginUrl,
	allowEndpointSignIn,
} from '../bootstrap';
import { getWorkspacePersistence } from './workspace-persistence';
import {
	deriveDocTitle,
	displayDocTitle,
	isAutoTitle,
} from '../utils/derive-doc-title';
import { WELCOME_QUERY } from '../stores/document-editor/welcome-query';

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
	const { choose } = useDialog();
	const [shareDialogOpen, setShareDialogOpen] = useState(false);
	const [saveDialogOpen, setSaveDialogOpen] = useState(false);
	// Reused for both first-save (temp doc → server doc) and rename
	// (existing doc → new title/collections). Mode controls the
	// dialog's labels and which onSave path runs.
	const [saveDialogMode, setSaveDialogMode] = useState('save');
	// Pending edits in the document-settings view. Reset to the active
	// document's saved values whenever the active tab changes.
	const [docSettingsValues, setDocSettingsValues] = useState({});
	// Which document id the live `query`/`variables`/`headers` state
	// currently mirrors. The sync useEffect lags one render behind a tab
	// switch, so anything that compares live editor state with the active
	// doc (notably the dirty check) must guard against the in-between
	// frame where they don't match — otherwise a saved tab transiently
	// appears dirty during the swap and flickers a dot + italic in/out,
	// which reflows the tab strip.
	const [editorSyncedDocId, setEditorSyncedDocId] = useState(null);
	const docSettingsConfig =
		(typeof window !== 'undefined' &&
			window.WPGRAPHQL_IDE_DATA?.documentSettings) ||
		{};
	const docSettingsFields = docSettingsConfig.fields || [];
	const docSettingsGlobalGrant =
		docSettingsConfig.globalGrantMode || 'public';
	// `endpointMode` rides through the bootstrap module so the
	// `=== true` truthy gotcha lives in one place. See
	// `src/bootstrap.js` for the rest of the public-endpoint flags.
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
	const isTempId = useSelect(
		(select) => select('wpgraphql-ide/document-editor').isTempId,
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
	const editorBottomTabs = useSelect(
		(select) => select('wpgraphql-ide/editor-bottom-tabs').bottomTabs(),
		[]
	);
	const httpMethod = useSelect(
		(select) => select('wpgraphql-ide/app').getHttpMethod(),
		[]
	);
	const collectionsList = useSelect(
		(select) => select('wpgraphql-ide/app').getCollections(),
		[]
	);
	const personalCollectionsList = useSelect(
		(select) => select('wpgraphql-ide/app').getPersonalCollections(),
		[]
	);
	const editorJumpRequest = useSelect(
		(select) => select('wpgraphql-ide/app').getEditorJumpRequest(),
		[]
	);

	const {
		setQuery,
		setVariables,
		setHeaders,
		setResponse,
		setResponseHeaders,
		toggleAuthentication,
		setHttpMethod,
		setLastExecutedOperation,
		loadHistory,
		addHistoryEntry,
		setDocsNavTarget,
		setCursorOffset,
		setEditorJumpRequest,
		loadCollections,
		togglePersonalCollectionMembership,
	} = useDispatch('wpgraphql-ide/app');

	const {
		loadDocuments,
		saveTab,
		publishTab,
		saveDocument,
		createTab,
		switchTab,
		closeTab,
		reorderTabs,
	} = useDispatch('wpgraphql-ide/document-editor');

	const { schema, isLoading: isSchemaLoading, refetch } = useSchema(fetcher);

	const activeDocRef = useRef(null);
	activeDocRef.current = activeDocument;

	// Mirror of openTabs for after-async checks. The mount effect's
	// hydrate-then-spawn-fresh logic needs to read post-hydration tab
	// count without re-subscribing the effect to `openTabs` changes.
	const openTabsRef = useRef(openTabs);
	openTabsRef.current = openTabs;

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

	const [queryPaneWidth, setQueryPaneWidth] = usePersistedSize(
		'wpgraphql_ide_query_width',
		'50%'
	);
	const [editorHeight, setEditorHeight] = usePersistedSize(
		'wpgraphql_ide_editor_height',
		'70%',
		{ minPx: 220 }
	);
	const [responseViewerHeight, setResponseViewerHeight] = usePersistedSize(
		'wpgraphql_ide_response_viewer_height',
		'50%'
	);
	const [responseDataScope, setResponseDataScope] = useState('data');
	// Response view mode (JSON / Table) persists per-user via REST so
	// the choice rides across browsers. Pane-size ergonomics above stay
	// in localStorage per the design rule (sizes are session-scoped UI,
	// settings are user-scoped). Read from the bootstrap on first paint;
	// fire-and-forget write on change. Falls back to localStorage one
	// last time during the migration window so users coming from the
	// previous build don't lose their selection.
	const [responseViewMode, setResponseViewModeState] = useState(() => {
		const fromDevice = readDevicePreference('response_view_mode');
		if (fromDevice === 'formatted' || fromDevice === 'table') {
			return fromDevice;
		}
		const fromBootstrap = window.WPGRAPHQL_IDE_DATA?.responseViewMode;
		if (fromBootstrap === 'formatted' || fromBootstrap === 'table') {
			return fromBootstrap;
		}
		try {
			const legacy = window.localStorage.getItem(
				'wpgraphql_ide_response_mode'
			);
			if (legacy === 'formatted' || legacy === 'table') {
				window.localStorage.removeItem('wpgraphql_ide_response_mode');
				setPreference('response_view_mode', legacy).catch(() => {});
				return legacy;
			}
		} catch {
			// ignore
		}
		return 'formatted';
	});
	const setResponseViewMode = useCallback((next) => {
		setResponseViewModeState(next);
		setPreference('response_view_mode', next).catch(() => {});
	}, []);

	// Collapse state for the editor's bottom Variables/Headers strip and
	// the response pane's bottom Smart Cache / Errors / Debug / Headers /
	// Tracing strip. Both default to expanded so existing users see no
	// change; both persist per-device alongside the other UI-chrome prefs.
	const [editorBottomCollapsed, setEditorBottomCollapsedState] = useState(
		() => readDevicePreference('editor_bottom_collapsed') === true
	);
	const setEditorBottomCollapsed = useCallback((next) => {
		setEditorBottomCollapsedState(next);
		setPreference('editor_bottom_collapsed', next).catch(() => {});
	}, []);
	const [editorBottomActiveTab, setEditorBottomActiveTabState] = useState(
		() => readDevicePreference('editor_bottom_active_tab') || null
	);
	const setEditorBottomActiveTab = useCallback((next) => {
		setEditorBottomActiveTabState(next);
		setPreference('editor_bottom_active_tab', next).catch(() => {});
	}, []);
	const [responseBottomCollapsed, setResponseBottomCollapsedState] = useState(
		() => readDevicePreference('response_bottom_collapsed') === true
	);
	const setResponseBottomCollapsed = useCallback((next) => {
		setResponseBottomCollapsedState(next);
		setPreference('response_bottom_collapsed', next).catch(() => {});
	}, []);
	const [responseBottomActiveTab, setResponseBottomActiveTabState] = useState(
		() => readDevicePreference('response_bottom_active_tab') || null
	);
	const setResponseBottomActiveTab = useCallback((next) => {
		setResponseBottomActiveTabState(next);
		setPreference('response_bottom_active_tab', next).catch(() => {});
	}, []);
	const { notices, addNotice, removeNotice } = useNotices();

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

	// Load documents, history, and collections after mount. Collections
	// power the SaveDialog's collection picker so we need them ready
	// before the user hits Cmd+S, even if they haven't opened the
	// Documents panel yet.
	useEffect(() => {
		// Endpoint mode (anonymous / non-IDE-capable visitors via the
		// public endpoint) skips per-user fetches — they'd 401 anyway
		// since the routes require manage_graphql_ide. Spawn a fresh
		// `loadDocuments` is resilient to REST 401s — REST and
		// localStorage are tried independently inside it, so anonymous
		// endpoint-mode visitors still get their unsaved drafts
		// hydrated from per-user-scoped localStorage. If hydration
		// produces no open tabs, spawn a fresh one so the editor
		// surface is mounted and ready.
		const hydrate = async () => {
			await loadDocuments();
			if (endpointMode) {
				return;
			}
			loadHistory();
			loadCollections();
		};
		hydrate().then(() => {
			if (
				endpointMode &&
				openTabsRef.current &&
				openTabsRef.current.length === 0
			) {
				createTab('', WELCOME_QUERY);
			}
		});
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	// Notify the user about personal collections that have been newly
	// shared with them since their last visit. Compares the bootstrap's
	// `sharedCollections` (live aggregator output) against
	// `seenSharedCollections` (per-user meta of what they've already
	// been notified about). Persists the current id set so the same
	// collection doesn't notify twice.
	useEffect(() => {
		const data = window.WPGRAPHQL_IDE_DATA || {};
		const shared = Array.isArray(data.sharedCollections)
			? data.sharedCollections
			: [];
		const seen = new Set(
			Array.isArray(data.seenSharedCollections)
				? data.seenSharedCollections.map(String)
				: []
		);
		const unseen = shared.filter((sc) => !seen.has(String(sc.id)));
		if (unseen.length === 0) {
			return;
		}
		for (const sc of unseen) {
			const owner = sc.owner?.display_name || 'Another user';
			addNotice(`${owner} shared "${sc.name}" with you.`);
		}
		// Persist the full current id list — not just `unseen`, so that
		// if a collection is later unshared and then reshared, it'll
		// notify again. Fire-and-forget; failure just means we'll
		// re-notify next load, which is acceptable.
		setPreference(
			'seen_shared_collections',
			shared.map((sc) => String(sc.id))
		).catch(() => {});
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	// When active document changes, populate editors and restore response.
	// Cancel any pending auto-save from the previous document. When no doc
	// is active (last tab closed), clear editor + response state so the
	// next "New tab" starts blank instead of inheriting stale content.
	useEffect(() => {
		cancelAutoSave();
		if (!activeDocument) {
			setQuery('');
			setVariables('');
			setHeaders('');
			setResponse('');
			setResponseHeaders(null);
			setResponseDataScope('data');
			setDocSettingsValues({});
			setEditorSyncedDocId(null);
			return;
		}
		setQuery(activeDocument.query || '');
		setVariables(activeDocument.variables || '');
		setHeaders(activeDocument.headers || '');
		// `lastResponse` is now in its own slice; read once on tab
		// switch via the registry rather than threading another
		// useSelect dep through this effect.
		setResponse(
			wpSelect('wpgraphql-ide/document-editor').getDocumentResponse(
				activeDocument.id
			)
		);
		setDocSettingsValues(
			activeDocument.documentSettings &&
				typeof activeDocument.documentSettings === 'object'
				? { ...activeDocument.documentSettings }
				: {}
		);
		setEditorSyncedDocId(activeDocument.id);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [activeDocument?.id]);

	const {
		cancelAutoSave,
		scheduleAutoSave,
		handleQueryChange,
		handleVariablesChange,
		handleHeadersChange,
		handleDocumentSettingChange,
	} = useAutoSave({
		activeDocument,
		saveDocument,
		setQuery,
		setVariables,
		setHeaders,
		setDocSettingsValues,
	});

	useEffect(() => {
		if (!activeDocument) {
			return;
		}
		// Skip the tab-switch sync that re-sets `query` to the doc's persisted value.
		if (query === (activeDocument.query || '')) {
			return;
		}
		scheduleAutoSave('query', query);
	}, [query, activeDocument, scheduleAutoSave]);

	// Explicit save — Cmd+S / Save button. For a brand-new draft (temp
	// id), open the SaveDialog so the user can name it and pick a
	// collection in one step. Subsequent saves write straight through.
	const saveCurrentDoc = useCallback(async () => {
		if (!activeDocument) {
			return;
		}
		const isFirstSave = String(activeDocument.id).startsWith('temp-');
		if (isFirstSave) {
			setSaveDialogOpen(true);
			return;
		}
		try {
			await saveTab(activeDocument.id, {
				query,
				variables,
				headers,
				documentSettings: docSettingsValues,
			});
			addNotice('Document saved');
		} catch (error) {
			const message =
				error?.message && typeof error.message === 'string'
					? `Failed to save document: ${error.message}`
					: 'Failed to save document';
			addNotice(message, 'error');
		}
	}, [
		activeDocument,
		query,
		variables,
		headers,
		docSettingsValues,
		saveTab,
		addNotice,
	]);

	// Publish the current document (draft → published with hash).
	const publishCurrentDoc = useCallback(async () => {
		if (!activeDocument || String(activeDocument.id).startsWith('temp-')) {
			return;
		}

		// Validate the query is valid GraphQL before publishing.
		if (!query || !query.trim()) {
			addNotice('Cannot publish an empty document', 'error');
			return;
		}
		let doc;
		try {
			doc = parseGraphQL(query);
		} catch (syntaxError) {
			addNotice(`Invalid GraphQL: ${syntaxError.message}`, 'error');
			return;
		}
		// Schema-aware validation — catches empty selections, unknown fields, etc.
		if (schema) {
			const errors = validateGraphQL(schema, doc);
			if (errors.length > 0) {
				addNotice(`Invalid query: ${errors[0].message}`, 'error');
				return;
			}
		}

		// Save first to ensure content is persisted, then flip status to
		// publish. Smart Cache's save_document_cb hashes the normalized
		// query into post_name on the way through; if a duplicate exists,
		// WP's wp_unique_post_slug suffixes the slug so the two docs
		// coexist (5.0 dropped the old `already_exists` collision dialog).
		try {
			await saveTab(activeDocument.id, {
				query,
				variables,
				headers,
				documentSettings: docSettingsValues,
			});
			await publishTab(activeDocument.id);
			addNotice(__('Document published', 'wpgraphql-ide'));
		} catch (error) {
			const message =
				error?.message && typeof error.message === 'string'
					? sprintf(
							/* translators: %s: error message returned by the publish request */
							__(
								'Failed to publish document: %s',
								'wpgraphql-ide'
							),
							error.message
						)
					: __('Failed to publish document', 'wpgraphql-ide');
			addNotice(message, 'error');
		}
	}, [
		activeDocument,
		query,
		variables,
		headers,
		docSettingsValues,
		schema,
		saveTab,
		publishTab,
		addNotice,
	]);

	// Whether the active document is published (immutable query).
	const isPublished = activeDocument?.status === 'publish';

	// Spawn a fresh draft tab seeded with the current doc's query, variables,
	// and headers. Used by the "Duplicate as draft" kebab item and the
	// document-notice link on published docs — keep them in lockstep.
	const duplicateAsDraft = useCallback(async () => {
		if (!activeDocument) {
			return;
		}
		await createTab(`${displayDocTitle(activeDocument)} (copy)`, query);
		setVariables(variables);
		setHeaders(headers);
		addNotice('Draft copy created');
	}, [
		activeDocument,
		createTab,
		setVariables,
		setHeaders,
		query,
		variables,
		headers,
		addNotice,
	]);
	const isTempDoc = activeDocument
		? String(activeDocument.id).startsWith('temp-')
		: true;
	const isSavedDraft =
		activeDocument && !isTempDoc && activeDocument.status !== 'publish';

	const { isDocDirty, activeDocDirty } = useDocumentDirty({
		activeDocument,
		editorSyncedDocId,
		query,
		variables,
		headers,
		docSettingsValues,
	});

	// Close tab with a 3-way prompt for dirty documents:
	//   Save and close (primary)  → persist, then close
	//   Discard         (destructive) → drop changes, then close
	//   Cancel          (returns null) → leave the tab open as-is
	//
	// The previous binary confirm conflated "Cancel" with "Discard" —
	// hitting Esc or clicking the dismiss X tossed the user's work
	// silently. The choose dialog separates the two so accidental
	// dismissal is non-destructive.
	//
	// Workspace tabs (Settings, etc.) delegate save/discard to whatever
	// they registered via `registerWorkspacePersistence`; query docs
	// use saveTab.
	const handleCloseTab = useCallback(
		async (tabId) => {
			const doc = allDocuments.find(
				(d) => String(d.id) === String(tabId)
			);
			if (isDocDirty(doc)) {
				const persistence = doc.tabType
					? getWorkspacePersistence(doc.tabType)
					: null;
				const answer = await choose({
					title: 'Unsaved changes',
					message: `You have unsaved changes in "${
						doc.title || 'Untitled'
					}". What would you like to do?`,
					cancelLabel: 'Cancel',
					actions: [
						{
							key: 'discard',
							label: 'Discard',
							isDestructive: true,
						},
						{
							key: 'save',
							label: 'Save and close',
							variant: 'primary',
						},
					],
				});

				if (answer === null) {
					// Cancel — leave the tab open. No close, no save, no discard.
					return;
				}

				if (answer === 'save') {
					try {
						if (persistence?.save) {
							await persistence.save();
						} else if (!doc.tabType) {
							await saveTab(tabId, {
								query: doc.query || query,
								variables: doc.variables || variables,
								headers: doc.headers || headers,
							});
						}
					} catch (error) {
						addNotice(
							`Failed to save before closing: ${error.message}`,
							'error'
						);
						return;
					}
				} else if (answer === 'discard' && persistence?.discard) {
					persistence.discard();
				}
			}
			closeTab(tabId);
		},
		[
			allDocuments,
			closeTab,
			saveTab,
			addNotice,
			query,
			variables,
			headers,
			choose,
			isDocDirty,
		]
	);

	const { parsedQuery, operationNames, variableToType } = useParsedQuery(
		query,
		schema
	);

	// Exposed via ref so closures (keybinding, run-button click) always
	// see the latest parsed AST without re-creating the keymap.
	const parsedQueryRef = useRef(parsedQuery);
	parsedQueryRef.current = parsedQuery;

	// Resolve which named operation a character offset falls inside.
	// Returns undefined when there's no AST, no cursor, or the offset
	// sits between operations — callers fall back to the first op.
	const resolveOpAtOffset = (offset) => {
		const ast = parsedQueryRef.current?.ast;
		if (
			typeof offset !== 'number' ||
			!ast ||
			!Array.isArray(ast.definitions)
		) {
			return undefined;
		}
		const def = ast.definitions.find(
			(d) =>
				d.kind === 'OperationDefinition' &&
				d.name?.value &&
				d.loc &&
				offset >= d.loc.start &&
				offset <= d.loc.end
		);
		return def?.name?.value;
	};

	const executeQueryRef = useRef(null);
	executeQueryRef.current = (operationName) => {
		if (isFetching) {
			stop();
		} else {
			if (!schema) {
				refetch();
			}
			executingDocIdRef.current = activeDocument?.id || null;
			executingQueryRef.current = query;
			executingHeadersRef.current = headers;
			executingAuthRef.current = isAuthenticated;
			executingMethodRef.current = httpMethod;
			// Caller is responsible for resolving multi-op ambiguity —
			// the floating pill's picker dropdown forces an explicit
			// pick, and the Cmd+Enter keybind resolves the cursor. The
			// only no-op-name path that reaches here is single-op /
			// anonymous shorthand, where there's nothing to pick.
			let target = operationName;
			if (!target && operationNames.length === 1) {
				target = operationNames[0];
			}
			setLastExecutedOperation(target || null);
			run(target);
		}
	};

	const executeQuery = (operationName) =>
		executeQueryRef.current(operationName);

	// Cmd/Ctrl+Enter — universal "run query" chord. Resolves the op
	// under the cursor so multi-op docs run *this* op instead of
	// opening the picker dropdown. Cursor between ops or AST missing
	// → fall back to the first named op so the request stays spec-
	// compliant (GraphQL §6.1 requires `operationName` when the doc
	// has multiple operations).
	const editorKeyBindings = useRef([
		{
			key: 'Mod-Enter',
			run: (view) => {
				let opName;
				if (view) {
					opName = resolveOpAtOffset(view.state.selection.main.head);
				}
				if (!opName) {
					const ast = parsedQueryRef.current?.ast;
					const firstNamed = ast?.definitions?.find(
						(d) => d.kind === 'OperationDefinition' && d.name?.value
					);
					opName = firstNamed?.name?.value;
				}
				executeQueryRef.current(opName);
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
	// Saved Queries and History require authentication; when sign-in is
	// disabled on the public IDE there's nothing meaningful behind their
	// icons, so hide them rather than parade a locked door. Logged-in
	// visitors and the dedicated admin page always see all panels.
	const hideAuthGatedPanels = !isUserLoggedIn && !allowEndpointSignIn;
	const authGatedPanelNames = new Set(['saved-queries', 'history']);
	const unfilteredNavPanels = panels.filter((p) => {
		if (p.name === 'query-composer') {
			return false;
		}
		if (hideAuthGatedPanels && authGatedPanelNames.has(p.name)) {
			return false;
		}
		return true;
	});

	const {
		navPanels,
		dragOverPanel,
		onDragStart: onPanelDragStart,
		onDragOver: onPanelDragOver,
		onDragLeave: onPanelDragLeave,
		onDrop: onPanelDrop,
		onDragEnd: onPanelDragEnd,
	} = usePanelOrder(unfilteredNavPanels);

	// Activity-bar `visiblePanel` is store-backed and persists across
	// sessions. When the public-IDE sign-in setting flips off (or the
	// previously-logged-in user signs out), a saved-queries / history
	// panel that's still open from a prior session would render with
	// no close button in the bar. Force-close it on mount when the
	// visible panel sits on the auth-gated list and shouldn't be shown.
	useEffect(() => {
		if (
			hideAuthGatedPanels &&
			visiblePanel &&
			authGatedPanelNames.has(visiblePanel.name)
		) {
			setVisiblePanel(null);
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	// Query composer panel — rendered inline within the document/editor area.
	const queryComposerPanel = panels.find((p) => p.name === 'query-composer');
	const ComposerContent = queryComposerPanel?.content || null;

	const {
		setLeftPanel,
		showQueryComposer,
		showDocSettingsPanel,
		toggleQueryComposer,
		toggleDocSettingsPanel,
		composerWidth,
		setComposerWidth,
		docSettingsPanelWidth,
		setDocSettingsPanelWidth,
	} = useLeftPanel({ endpointMode });

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
			<IDETopbar
				visiblePanel={visiblePanel}
				onSidebarToggle={handleSidebarToggle}
				topbarCtx={{
					isSchemaLoading,
					// Returns the refetch result so the topbar action's onClick
					// (registry/index.js) can decide which user-facing notice
					// to fire. Centralising notice emission there lets the
					// schema-refresh-mash easter egg suppress the baseline
					// "Schema refreshed" snackbar in mash mode without this
					// callback having to know about the easter-egg counter.
					refetchSchema: () => refetch(),
				}}
				topbarActions={endpointMode ? [] : topbarActions}
				onClose={onClose}
			/>

			<div className="wpgraphql-ide-main">
				<IDEActivityBar
					navPanels={navPanels}
					visiblePanel={visiblePanel}
					dragOverPanel={dragOverPanel}
					onDragStart={onPanelDragStart}
					onDragOver={onPanelDragOver}
					onDragLeave={onPanelDragLeave}
					onDrop={onPanelDrop}
					onDragEnd={onPanelDragEnd}
					onPanelClick={toggleActivityPanelVisibility}
				/>

				{/* Collapsible side panel — global, shows active panel content */}
				<ActivityPanel />

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
									// Active doc derives from live `query` so the tab name doesn't lag the autosave debounce.
									title:
										doc.id === activeDocument?.id &&
										isAutoTitle(doc.title)
											? deriveDocTitle(query)
											: displayDocTitle(doc),
									dirty: isDocDirty(doc),
									// Italic title mirrors the saved-queries
									// "Unsaved" group — temp drafts aren't
									// on the server yet.
									temp:
										!doc.tabType &&
										String(doc.id).startsWith('temp-'),
								}))}
							activeId={activeDocument?.id}
							onSwitch={(id) => switchTab(id)}
							onClose={(id) => handleCloseTab(id)}
							onCreate={() => createTab('')}
							onRename={(id, title) => {
								saveDocument(id, { title });
							}}
							onReorder={(ids) => reorderTabs(ids)}
						/>
					</div>

					{openTabs.length === 0 ? (
						<WorkspaceEmpty />
					) : (
						<>
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
									<EditorPane
										endpointMode={endpointMode}
										queryPaneWidth={queryPaneWidth}
										onSetQueryPaneWidth={setQueryPaneWidth}
										editorHeight={editorHeight}
										onSetEditorHeight={setEditorHeight}
										activeDocument={activeDocument}
										activeDocDirty={activeDocDirty}
										isPublished={isPublished}
										isSavedDraft={isSavedDraft}
										query={query}
										onQueryChange={handleQueryChange}
										parsedQuery={parsedQuery}
										onSave={saveCurrentDoc}
										onPublish={publishCurrentDoc}
										onDuplicateAsDraft={duplicateAsDraft}
										onOpenShareDialog={() =>
											setShareDialogOpen(true)
										}
										onOpenRenameDialog={() => {
											setSaveDialogMode('rename');
											setSaveDialogOpen(true);
										}}
										addNotice={addNotice}
										isTempId={isTempId}
										schema={schema}
										editorKeyBindings={editorKeyBindings}
										onShowInDocs={handleShowInDocs}
										onCursorChange={setCursorOffset}
										jumpRequest={editorJumpRequest}
										onJumpApplied={() =>
											setEditorJumpRequest(null)
										}
										ComposerContent={ComposerContent}
										showQueryComposer={showQueryComposer}
										toggleQueryComposer={
											toggleQueryComposer
										}
										onCloseLeftPanel={() =>
											setLeftPanel(null)
										}
										composerWidth={composerWidth}
										onSetComposerWidth={setComposerWidth}
										docSettingsFields={docSettingsFields}
										docSettingsValues={docSettingsValues}
										docSettingsGlobalGrant={
											docSettingsGlobalGrant
										}
										onDocSettingChange={
											handleDocumentSettingChange
										}
										showDocSettingsPanel={
											showDocSettingsPanel
										}
										toggleDocSettingsPanel={
											toggleDocSettingsPanel
										}
										docSettingsPanelWidth={
											docSettingsPanelWidth
										}
										onSetDocSettingsPanelWidth={
											setDocSettingsPanelWidth
										}
										editorBottomTabs={editorBottomTabs}
										variables={variables}
										onVariablesChange={
											handleVariablesChange
										}
										variableToType={variableToType}
										headers={headers}
										onHeadersChange={handleHeadersChange}
										httpMethod={httpMethod}
										onSetHttpMethod={setHttpMethod}
										isAuthenticated={isAuthenticated}
										onToggleAuth={toggleAuthentication}
										avatarUrl={
											window.WPGRAPHQL_IDE_DATA?.context
												?.avatarUrl
										}
										operationNames={operationNames}
										isFetching={isFetching}
										isSchemaLoading={isSchemaLoading}
										onExecute={executeQuery}
										signInUrl={
											endpointMode &&
											!isUserLoggedIn &&
											allowEndpointSignIn
												? loginUrl
												: undefined
										}
										showAuthControl={
											isUserLoggedIn ||
											allowEndpointSignIn
										}
										bottomCollapsed={editorBottomCollapsed}
										onSetBottomCollapsed={
											setEditorBottomCollapsed
										}
										bottomActiveTab={editorBottomActiveTab}
										onSetBottomActiveTab={
											setEditorBottomActiveTab
										}
									/>
									<ResponsePane
										response={response}
										responseDataScope={responseDataScope}
										onSetDataScope={setResponseDataScope}
										responseViewMode={responseViewMode}
										onSetViewMode={setResponseViewMode}
										responseStatus={responseStatus}
										responseDuration={responseDuration}
										responseSize={responseSize}
										responseHeaders={responseHeaders}
										extensionTabs={extensionTabs}
										isFetching={isFetching}
										responseViewerHeight={
											responseViewerHeight
										}
										onResponseViewerResize={
											setResponseViewerHeight
										}
										bottomCollapsed={
											responseBottomCollapsed
										}
										onSetBottomCollapsed={
											setResponseBottomCollapsed
										}
										bottomActiveTab={
											responseBottomActiveTab
										}
										onSetBottomActiveTab={
											setResponseBottomActiveTab
										}
									/>
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
					notices={notices.map((n) => ({
						...n,
						className: n.type === 'error' ? 'is-error' : '',
					}))}
					onRemove={removeNotice}
					className="wpgraphql-ide-snackbar-list"
				/>
			)}
			{!endpointMode && shareDialogOpen && (
				<ShareDialog
					onClose={() => setShareDialogOpen(false)}
					onCopy={() => addNotice('Share link copied')}
				/>
			)}
			{!endpointMode && saveDialogOpen && activeDocument && (
				<SaveDialog
					mode={saveDialogMode}
					defaultTitle={
						saveDialogMode === 'rename'
							? activeDocument.title || ''
							: ''
					}
					defaultCollectionIds={
						saveDialogMode === 'rename' &&
						Array.isArray(activeDocument.collections)
							? activeDocument.collections
							: []
					}
					defaultPersonalCollectionIds={
						saveDialogMode === 'rename'
							? personalCollectionsList
									.filter((pc) =>
										(pc.document_ids || [])
											.map(Number)
											.includes(Number(activeDocument.id))
									)
									.map((pc) => pc.id)
							: []
					}
					collections={collectionsList}
					personalCollections={personalCollectionsList}
					onClose={() => {
						setSaveDialogOpen(false);
						setSaveDialogMode('save');
					}}
					onSave={async ({
						title,
						collectionIds,
						personalCollectionIds,
					}) => {
						const isRename = saveDialogMode === 'rename';
						// Rename only writes the user-visible bits (title +
						// collection memberships). Save also captures
						// in-flight editor content. Keeping the rename path
						// content-free means a draft with unsaved changes
						// stays dirty after a rename, which matches user
						// expectation.
						const saved = isRename
							? await saveTab(activeDocument.id, {
									title,
									collections: Array.isArray(collectionIds)
										? collectionIds
										: [],
								})
							: await saveTab(activeDocument.id, {
									title,
									query,
									variables,
									headers,
									collections: Array.isArray(collectionIds)
										? collectionIds
										: [],
									documentSettings: docSettingsValues,
								});
						const docId = saved?.id || activeDocument.id;
						const desired = new Set(
							Array.isArray(personalCollectionIds)
								? personalCollectionIds
								: []
						);
						if (isRename) {
							// Rename: diff current vs desired, toggle the
							// symmetric difference. (toggle flips state, so
							// toggling every changed id lands the right
							// final membership.)
							const current = new Set(
								personalCollectionsList
									.filter((pc) =>
										(pc.document_ids || [])
											.map(Number)
											.includes(Number(docId))
									)
									.map((pc) => pc.id)
							);
							const toAdd = [...desired].filter(
								(id) => !current.has(id)
							);
							const toRemove = [...current].filter(
								(id) => !desired.has(id)
							);
							for (const pcId of [...toAdd, ...toRemove]) {
								await togglePersonalCollectionMembership(
									pcId,
									docId
								);
							}
						} else if (desired.size > 0) {
							// First save: doc isn't in any personal collection
							// yet, so every desired id is a pure add.
							for (const pcId of desired) {
								await togglePersonalCollectionMembership(
									pcId,
									docId
								);
							}
						}
						loadDocuments();
						addNotice(
							isRename ? 'Document renamed' : 'Document saved'
						);
					}}
				/>
			)}
		</div>
	);
}
