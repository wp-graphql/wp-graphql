import React, {
	useState,
	useCallback,
	useEffect,
	useMemo,
	useRef,
} from 'react';
import { parse as parseGraphQL, validate as validateGraphQL } from 'graphql';
import { SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
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
import { savePreference } from '../api/preferences';
import { getWorkspacePersistence } from './workspace-persistence';
import { displayDocTitle } from '../utils/derive-doc-title';

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

	const {
		setQuery,
		setVariables,
		setHeaders,
		setResponse,
		setResponseHeaders,
		toggleAuthentication,
		setHttpMethod,
		loadHistory,
		addHistoryEntry,
		setDocsNavTarget,
		setCursorOffset,
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
				savePreference('response_view_mode', legacy).catch(() => {});
				return legacy;
			}
		} catch {
			// ignore
		}
		return 'formatted';
	});
	const setResponseViewMode = useCallback((next) => {
		setResponseViewModeState(next);
		savePreference('response_view_mode', next).catch(() => {});
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
		loadDocuments();
		loadHistory();
		loadCollections();
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
		savePreference(
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
		setResponse(activeDocument.lastResponse || '');
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

		// Save first to ensure content is persisted.
		try {
			await saveTab(activeDocument.id, {
				query,
				variables,
				headers,
				documentSettings: docSettingsValues,
			});
			const result = await publishTab(activeDocument.id);
			if (result?.already_exists && result?.id) {
				// Server detected an identical published document. Don't
				// silently swap tabs — the draft tab would be left
				// orphaned. Ask the user how to resolve.
				const draftId = activeDocument.id;
				const existingId = String(result.id);
				const choice = await choose({
					title: 'This query is already published',
					message:
						'An existing published document has the same content. Open it, or keep editing this draft to make it different.',
					actions: [
						{
							key: 'open',
							label: 'Open existing',
							variant: 'primary',
						},
						{ key: 'keep', label: 'Keep editing' },
					],
				});
				if (choice === 'open') {
					// Switch first so closing the draft doesn't trigger
					// the active-tab fallback inside closeTab; then both
					// persistTabState writes land in deterministic order.
					await switchTab(existingId);
					await closeTab(String(draftId));
				}
				// 'keep' or null: stay on the draft, no tab change.
			} else {
				addNotice('Document published');
			}
		} catch (error) {
			const message =
				error?.message && typeof error.message === 'string'
					? `Failed to publish document: ${error.message}`
					: 'Failed to publish document';
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
		switchTab,
		closeTab,
		choose,
		addNotice,
	]);

	// Whether the active document is published (immutable query).
	const isPublished = activeDocument?.status === 'publish';

	// Whether the variables/headers JSON strings carry any meaningful
	// Variables + Headers belong to the *request*, not the immutable
	// document. A published doc's query body is locked, but the
	// variables and HTTP headers can change per request (auth tokens,
	// pagination cursors, etc.) so the panel stays editable on every
	// doc. Both tabs are always rendered so users can add content even
	// when the persisted doc shipped without it.
	const editorBottomTabs = useMemo(
		() => [
			{ name: 'variables', title: 'Variables' },
			{ name: 'headers', title: 'Headers' },
		],
		[]
	);

	// Spawn a fresh draft tab seeded with the current doc's query, variables,
	// and headers. Used by the "Duplicate as draft" kebab item and the
	// document-notice link on published docs — keep them in lockstep.
	const duplicateAsDraft = useCallback(async () => {
		if (!activeDocument) {
			return;
		}
		await createTab(`${displayDocTitle(activeDocument)} (copy)`);
		setQuery(query);
		setVariables(variables);
		setHeaders(headers);
		addNotice('Draft copy created');
	}, [
		activeDocument,
		createTab,
		setQuery,
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

	const executeQueryRef = useRef(null);
	executeQueryRef.current = (operationName) => {
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
			// When the doc has multiple named operations and the caller didn't
			// pick one (e.g. keyboard shortcut), default to the first.
			let target = operationName;
			if (!target && operationNames.length > 1) {
				target = operationNames[0];
			}
			run(target);
		}
	};

	const executeQuery = (operationName) =>
		executeQueryRef.current(operationName);

	// Cmd/Ctrl+Enter to run the query is the universal convention for
	// GraphQL clients (GraphiQL, Postman, Insomnia, Apollo Sandbox).
	// Keeping this single custom binding; everything else (undo/redo,
	// search, indent, comment, history) comes for free from CodeMirror.
	const editorKeyBindings = useRef([
		{
			key: 'Mod-Enter',
			run: () => {
				executeQueryRef.current();
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
	const unfilteredNavPanels = panels.filter(
		(p) => p.name !== 'query-composer'
	);

	const {
		navPanels,
		dragOverPanel,
		onDragStart: onPanelDragStart,
		onDragOver: onPanelDragOver,
		onDragLeave: onPanelDragLeave,
		onDrop: onPanelDrop,
		onDragEnd: onPanelDragEnd,
	} = usePanelOrder(unfilteredNavPanels);

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
	} = useLeftPanel();

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
				isSchemaLoading={isSchemaLoading}
				onRefetchSchema={async () => {
					const result = await refetch();
					if (result?.ok) {
						addNotice('Schema refreshed');
					} else {
						addNotice(
							`Failed to refresh schema: ${
								result?.error?.message ?? 'Unknown error'
							}`,
							'error'
						);
					}
				}}
				topbarActions={topbarActions}
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

				{/* Editor area: tabs + editors are document-scoped */}
				<div className="wpgraphql-ide-editor-area">
					{openTabs.length === 0 ? (
						<WorkspaceEmpty onCreate={() => createTab('')} />
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
											title: displayDocTitle(doc),
											// Temp docs are visually marked
											// dirty even when empty so the
											// "unsaved" affordance is on from
											// the moment a tab is created.
											// Close-confirmation still uses
											// `isDocDirty`, which gates on
											// actual content.
											dirty:
												isDocDirty(doc) ||
												(!doc.tabType &&
													String(doc.id).startsWith(
														'temp-'
													)),
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
			{shareDialogOpen && (
				<ShareDialog
					onClose={() => setShareDialogOpen(false)}
					onCopy={() => addNotice('Share link copied')}
				/>
			)}
			{saveDialogOpen && activeDocument && (
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
