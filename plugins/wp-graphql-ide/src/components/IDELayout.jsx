import React, {
	useState,
	useCallback,
	useEffect,
	useMemo,
	useRef,
} from 'react';
import { parse as parseGraphQL, validate as validateGraphQL } from 'graphql';
import {
	Button,
	Dropdown,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	NavigableMenu,
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
	cog,
} from '@wordpress/icons';
import { useDispatch, useSelect } from '@wordpress/data';
import { GraphQLEditor } from './editors/GraphQLEditor';
import { JSONEditor } from './editors/JSONEditor';
import { ResponseViewer } from './editors/ResponseViewer';
import { ErrorsPanel } from './ErrorsPanel';
import { ShareDialog } from './dialogs/ShareDialog';
import { SaveDialog } from './dialogs/SaveDialog';
import { HeadersPanel } from './HeadersPanel';
import { ResponseTableView } from './ResponseTableView';
import { EditorToolbar } from './EditorToolbar';
import { DocumentTabs } from './DocumentTabs';
import { DocumentNotices } from './DocumentNotices';
import ActivityPanel from './ActivityPanel';
import { useDialog } from './dialogs/DialogProvider';
import { DocumentSettingsDrawer } from './document-settings/DocumentSettingsDrawer';
import authStyles from '../../styles/ToggleAuthenticationButton.module.css';
import hooks from '../wordpress-hooks';
import { useSchema } from '../hooks/useSchema';
import { useExecution } from '../hooks/useExecution';
import { useDebouncedCallback } from '../hooks/useDebouncedCallback';
import { getWorkspacePersistence } from './workspace-persistence';
import {
	displayDocTitle,
	deriveStableDocTitle,
	isAutoTitle,
} from '../utils/derive-doc-title';

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

	// With no response, the panel is intentionally bare — no
	// Headers/Errors/Extensions tabs, no resizer, just an empty surface
	// that fills the full panel height.
	if (!response) {
		return (
			<div className="wpgraphql-ide-response-body wpgraphql-ide-response-body--empty">
				<div className="wpgraphql-ide-response-empty">
					<div className="wpgraphql-ide-response-empty-hint">
						<span
							className="wpgraphql-ide-response-empty-glyph"
							aria-hidden="true"
						>
							<svg
								width="20"
								height="20"
								viewBox="0 0 20 20"
								xmlns="http://www.w3.org/2000/svg"
							>
								<path
									d="M6 4.5v11l9-5.5-9-5.5z"
									fill="currentColor"
								/>
							</svg>
						</span>
						<span className="wpgraphql-ide-response-empty-text">
							Run the query to see the response
						</span>
					</div>
				</div>
			</div>
		);
	}

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

	const savedQueryWidth =
		window.localStorage.getItem('wpgraphql_ide_query_width') || '50%';
	// Clamp the persisted editor height so a previous tiny-drag (or stale
	// flex-mode height saved while the bottom strip was hidden) can't leave
	// the editor unreadable on the next visit. Percent strings pass through.
	const MIN_EDITOR_HEIGHT_PX = 220;
	const savedEditorHeight = (() => {
		const raw = window.localStorage.getItem('wpgraphql_ide_editor_height');
		if (!raw) {
			return '70%';
		}
		const asNumber = Number(raw);
		if (Number.isFinite(asNumber)) {
			return Math.max(MIN_EDITOR_HEIGHT_PX, asNumber);
		}
		return raw;
	})();
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
		import('../api/preferences').then(({ savePreference }) => {
			savePreference(
				'seen_shared_collections',
				shared.map((sc) => String(sc.id))
			).catch(() => {});
		});
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

	// Auto-save drafts after 2 seconds of inactivity.
	const [debouncedSave, cancelAutoSave] = useDebouncedCallback(
		(docId, payload) => {
			saveDocument(docId, payload);
		},
		2000
	);

	const scheduleAutoSave = useCallback(
		(field, value) => {
			if (!activeDocument) {
				return;
			}
			// Sticky-title persist: when the active doc's title is still in
			// the auto state and the query has a clearly-complete op name
			// (followed by `{` or `(`), freeze that name as the title. Once
			// persisted, the title stops following the query — even if the
			// user later edits or removes the op name. Mirrors WP's "slug
			// freezes after first publish" behavior.
			const payload = { [field]: value };
			if (field === 'query' && isAutoTitle(activeDocument.title)) {
				const stable = deriveStableDocTitle(value);
				if (stable) {
					payload.title = stable;
				}
			}
			// Temp drafts only live client-side, so just push edits
			// straight to the doc store + localStorage. No debounce —
			// `saveDocument` for a temp ID is a synchronous local
			// update and skips the network entirely.
			if (String(activeDocument.id).startsWith('temp-')) {
				saveDocument(activeDocument.id, payload);
				return;
			}
			debouncedSave(activeDocument.id, payload);
		},
		[activeDocument, saveDocument, debouncedSave]
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

	const handleDocumentSettingChange = useCallback(
		(name, value) => {
			setDocSettingsValues((prev) => {
				const next = { ...prev, [name]: value };
				scheduleAutoSave('documentSettings', next);
				return next;
			});
		},
		[scheduleAutoSave]
	);

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

	// Derive whether a document has unsaved changes vs. its last server-saved
	// snapshot. Workspace tabs (Settings, etc.) carry their own `dirty` flag
	// since they don't have query/variables/headers. Temp drafts are dirty
	// when they hold any non-whitespace content (an empty New Tab isn't
	// worth a "save before closing?" prompt). For the active doc, compare
	// against the live editor state since pending edits haven't reached
	// the store yet between keystroke and autosave.
	const isDocDirty = useCallback(
		(doc) => {
			if (!doc) {
				return false;
			}
			if (doc.tabType) {
				return !!doc.dirty;
			}
			const isActive = String(doc.id) === String(activeDocument?.id);
			// Trust the live editor state only when it's already been
			// synced to this doc — otherwise we're on the in-between frame
			// after a tab switch where `query`/etc still hold the previous
			// tab's content. Falling back to `doc.*` keeps the dirty flag
			// stable across the swap so the dot/italic don't flicker.
			const liveStateMatches =
				isActive && String(editorSyncedDocId) === String(doc.id);
			const currentQuery = liveStateMatches ? query : doc.query || '';
			const currentVars = liveStateMatches
				? variables
				: doc.variables || '';
			const currentHeaders = liveStateMatches
				? headers
				: doc.headers || '';
			if (String(doc.id).startsWith('temp-')) {
				return (
					currentQuery.trim() !== '' ||
					currentVars.trim() !== '' ||
					currentHeaders.trim() !== ''
				);
			}
			if (
				currentQuery !== (doc.lastSavedQuery || '') ||
				currentVars !== (doc.lastSavedVariables || '') ||
				currentHeaders !== (doc.lastSavedHeaders || '')
			) {
				return true;
			}
			if (liveStateMatches) {
				const savedSettings =
					(doc.documentSettings &&
						typeof doc.documentSettings === 'object' &&
						doc.documentSettings) ||
					{};
				try {
					return (
						JSON.stringify(docSettingsValues) !==
						JSON.stringify(savedSettings)
					);
				} catch {
					return false;
				}
			}
			return false;
		},
		[
			activeDocument?.id,
			editorSyncedDocId,
			query,
			variables,
			headers,
			docSettingsValues,
		]
	);

	const activeDocDirty = isDocDirty(activeDocument);

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

	// Parse the current query once; reused for op-picker, Publish guard, and
	// Composer state. parseable=false means the editor content is unparseable
	// GraphQL (or empty).
	const parsedQuery = useMemo(() => {
		if (!query || !query.trim()) {
			return { ast: null, parseable: false, empty: true };
		}
		try {
			return { ast: parseGraphQL(query), parseable: true, empty: false };
		} catch {
			return { ast: null, parseable: false, empty: false };
		}
	}, [query]);

	// Operation names declared in the current document. The Execute button
	// turns into an op-picker dropdown when there's more than one — graphql-php
	// returns an error if multiple operations are sent without a target.
	const operationNames = useMemo(() => {
		if (!parsedQuery.ast) {
			return [];
		}
		return parsedQuery.ast.definitions
			.filter(
				(d) =>
					d.kind === 'OperationDefinition' && d.name && d.name.value
			)
			.map((d) => d.name.value);
	}, [parsedQuery]);

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

	// Apply user-saved panel order (loaded from preferences).
	const [panelOrder, setPanelOrder] = useState([]);
	const [dragOverPanel, setDragOverPanel] = useState(null);
	const dragSrcPanel = useRef(null);

	// Load saved panel order from preferences on mount.
	useEffect(() => {
		const saved =
			typeof window !== 'undefined' &&
			window.WPGRAPHQL_IDE_DATA?.panelOrder;
		if (Array.isArray(saved) && saved.length > 0) {
			setPanelOrder(saved);
		}
	}, []);

	const navPanels = useMemo(() => {
		if (panelOrder.length === 0) {
			return unfilteredNavPanels;
		}
		const ordered = [];
		for (const name of panelOrder) {
			const panel = unfilteredNavPanels.find((p) => p.name === name);
			if (panel) {
				ordered.push(panel);
			}
		}
		// Append any panels not in the saved order.
		for (const panel of unfilteredNavPanels) {
			if (!panelOrder.includes(panel.name)) {
				ordered.push(panel);
			}
		}
		return ordered;
	}, [unfilteredNavPanels, panelOrder]);

	const handlePanelDrop = useCallback(
		(targetName, pos) => {
			const srcName = dragSrcPanel.current;
			if (!srcName || srcName === targetName) {
				setDragOverPanel(null);
				return;
			}
			const names = navPanels.map((p) => p.name);
			const srcIdx = names.indexOf(srcName);
			if (srcIdx === -1) {
				setDragOverPanel(null);
				return;
			}
			names.splice(srcIdx, 1);
			let tgtIdx = names.indexOf(targetName);
			if (tgtIdx === -1) {
				setDragOverPanel(null);
				return;
			}
			if (pos === 'after') {
				tgtIdx += 1;
			}
			names.splice(tgtIdx, 0, srcName);
			setPanelOrder(names);
			setDragOverPanel(null);

			// Persist to user meta.
			import('../api/preferences').then(({ savePreference }) => {
				savePreference('panel_order', names);
			});
		},
		[navPanels]
	);

	// Query composer panel — rendered inline within the document/editor area.
	const queryComposerPanel = panels.find((p) => p.name === 'query-composer');
	const ComposerContent = queryComposerPanel?.content || null;

	// Single slot to the left of the GraphQL editor that hosts either the
	// Query Composer or the Document Settings panel. Mutually exclusive —
	// only one (or none) is open at a time. Persists the user's last
	// choice, falling back to the legacy composer flag for users coming
	// from older versions.
	const [leftPanel, setLeftPanelState] = useState(() => {
		try {
			const stored = window.localStorage.getItem(
				'wpgraphql_ide_left_panel'
			);
			if (stored === 'composer' || stored === 'settings') {
				return stored;
			}
			// Legacy compatibility: a `true` value from the older composer
			// flag becomes a Composer-open default.
			if (
				window.localStorage.getItem(
					'wpgraphql_ide_show_query_composer'
				) === 'true'
			) {
				return 'composer';
			}
		} catch {
			// ignore
		}
		return null;
	});

	const setLeftPanel = useCallback((next) => {
		setLeftPanelState(next);
		try {
			if (next === null) {
				window.localStorage.removeItem('wpgraphql_ide_left_panel');
			} else {
				window.localStorage.setItem('wpgraphql_ide_left_panel', next);
			}
		} catch {
			// ignore
		}
	}, []);

	const showQueryComposer = leftPanel === 'composer';
	const showDocSettingsPanel = leftPanel === 'settings';

	const toggleQueryComposer = useCallback(() => {
		setLeftPanel(leftPanel === 'composer' ? null : 'composer');
	}, [leftPanel, setLeftPanel]);

	const toggleDocSettingsPanel = useCallback(() => {
		setLeftPanel(leftPanel === 'settings' ? null : 'settings');
	}, [leftPanel, setLeftPanel]);

	const [composerWidth, setComposerWidth] = useState(() => {
		try {
			const w = parseInt(
				window.localStorage.getItem('wpgraphql_ide_composer_width'),
				10
			);
			return w > 0 ? w : 280;
		} catch {
			return 280;
		}
	});

	const [docSettingsPanelWidth, setDocSettingsPanelWidth] = useState(() => {
		try {
			const w = parseInt(
				window.localStorage.getItem(
					'wpgraphql_ide_settings_panel_width'
				),
				10
			);
			return w > 0 ? w : 360;
		} catch {
			return 360;
		}
	});

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
						placement="right"
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
							onClick={async () => {
								const result = await refetch();
								if (result?.ok) {
									addNotice('Schema refreshed');
								} else {
									addNotice(
										`Failed to refresh schema: ${
											result?.error?.message ??
											'Unknown error'
										}`,
										'error'
									);
								}
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
						<Tooltip
							key={panel.name}
							text={panel.title}
							placement="right"
						>
							<Button
								draggable
								onDragStart={(e) => {
									dragSrcPanel.current = panel.name;
									e.dataTransfer.effectAllowed = 'move';
								}}
								onDragOver={(e) => {
									e.preventDefault();
									e.dataTransfer.dropEffect = 'move';
									const rect =
										e.currentTarget.getBoundingClientRect();
									const pos =
										e.clientY < rect.top + rect.height / 2
											? 'before'
											: 'after';
									setDragOverPanel({
										name: panel.name,
										pos,
									});
								}}
								onDragLeave={() => setDragOverPanel(null)}
								onDrop={(e) => {
									e.preventDefault();
									handlePanelDrop(
										panel.name,
										dragOverPanel?.pos || 'after'
									);
								}}
								onDragEnd={() => {
									dragSrcPanel.current = null;
									setDragOverPanel(null);
								}}
								onClick={() =>
									toggleActivityPanelVisibility(panel.name)
								}
								aria-label={panel.title}
								aria-pressed={visiblePanel?.name === panel.name}
								size="compact"
								className={`wpgraphql-ide-activity-btn${visiblePanel?.name === panel.name ? ' is-active' : ''}${dragOverPanel?.name === panel.name ? ` is-drag-${dragOverPanel.pos}` : ''}`}
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
							<svg
								className="wpgraphql-ide-empty-icon"
								viewBox="0 0 24 24"
								fill="none"
								xmlns="http://www.w3.org/2000/svg"
							>
								<path
									d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"
									fill="currentColor"
								/>
							</svg>
							<h3 className="wpgraphql-ide-empty-title">
								No open documents
							</h3>
							<p className="wpgraphql-ide-empty-description">
								Create a new document to start writing GraphQL
								queries, or open one from the sidebar.
							</p>
							<Button
								variant="primary"
								onClick={() => createTab('')}
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
									<ResizableBox
										size={{
											width: queryPaneWidth,
											height: 'auto',
										}}
										minWidth={
											showQueryComposer &&
											ComposerContent &&
											!isPublished
												? 480
												: 280
										}
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
											{ComposerContent &&
												!isPublished && (
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
															aria-pressed={
																showQueryComposer
															}
															size="compact"
															className={`wpgraphql-ide-toolbar-composer-btn${showQueryComposer ? ' is-active' : ''}`}
														>
															<Icon
																icon={listView}
															/>
														</Button>
													</Tooltip>
												)}
											{docSettingsFields.length > 0 && (
												<Tooltip
													text={
														showDocSettingsPanel
															? 'Hide Document Settings'
															: 'Show Document Settings'
													}
												>
													<Button
														onClick={
															toggleDocSettingsPanel
														}
														aria-label={
															showDocSettingsPanel
																? 'Hide Document Settings'
																: 'Show Document Settings'
														}
														aria-pressed={
															showDocSettingsPanel
														}
														size="compact"
														className={`wpgraphql-ide-toolbar-doc-settings-btn${showDocSettingsPanel ? ' is-active' : ''}`}
													>
														<Icon icon={cog} />
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
																hideMutating={
																	isPublished
																}
															/>
														</MenuGroup>
														<MenuGroup>
															<MenuItem
																onClick={() => {
																	closeMenu();
																	setShareDialogOpen(
																		true
																	);
																}}
																disabled={
																	!query?.trim()
																}
															>
																Share link…
															</MenuItem>
														</MenuGroup>
														{!!activeDocument?.id &&
															!isTempId(
																activeDocument.id
															) && (
																<MenuGroup>
																	<MenuItem
																		onClick={() => {
																			closeMenu();
																			setSaveDialogMode(
																				'rename'
																			);
																			setSaveDialogOpen(
																				true
																			);
																		}}
																	>
																		Rename
																		document
																	</MenuItem>
																</MenuGroup>
															)}
														{isPublished && (
															<MenuGroup>
																<MenuItem
																	onClick={() => {
																		closeMenu();
																		duplicateAsDraft();
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
															!activeDocDirty
														}
														size="compact"
														className={`wpgraphql-ide-save-button${activeDocDirty ? ' is-dirty' : ''}`}
													>
														Save draft
													</Button>
													{isSavedDraft &&
														query?.trim() && (
															<Tooltip
																text={
																	!parsedQuery.parseable
																		? 'Fix the syntax error to publish'
																		: ''
																}
															>
																<Button
																	onClick={
																		publishCurrentDoc
																	}
																	disabled={
																		!parsedQuery.parseable
																	}
																	size="compact"
																	variant="primary"
																	className="wpgraphql-ide-publish-button"
																>
																	Publish
																</Button>
															</Tooltip>
														)}
												</>
											)}
										</div>
										<ResizableBox
											size={{
												width: '100%',
												height: editorHeight,
											}}
											minHeight={MIN_EDITOR_HEIGHT_PX}
											enable={{ bottom: true }}
											onResizeStop={(e, d, elt) => {
												const h = elt.offsetHeight;
												setEditorHeight(h);
												window.localStorage.setItem(
													'wpgraphql_ide_editor_height',
													String(h)
												);
											}}
											className={`wpgraphql-ide-editor-resizable wpgraphql-ide-resizable-split${(showQueryComposer && ComposerContent && !isPublished) || showDocSettingsPanel ? ' has-left-panel' : ''}`}
										>
											{/* Mounted inside the ResizableBox so its height
											    is borrowed from the editor area, not added
											    above it — keeps the bottom Variables/Headers
											    panel anchored to the same position whether
											    or not the notice is showing. */}
											<DocumentNotices
												isPublished={isPublished}
												onDuplicate={duplicateAsDraft}
											/>
											<div className="wpgraphql-ide-editor-resizable-body">
												{ComposerContent &&
													showQueryComposer &&
													!isPublished && (
														<ResizableBox
															size={{
																width: composerWidth,
																height: '100%',
															}}
															minWidth={200}
															maxWidth={600}
															enable={{
																top: false,
																right: true,
																bottom: false,
																left: false,
															}}
															onResizeStop={(
																e,
																d,
																elt
															) => {
																const w =
																	elt.offsetWidth;
																setComposerWidth(
																	w
																);
																try {
																	window.localStorage.setItem(
																		'wpgraphql_ide_composer_width',
																		String(
																			w
																		)
																	);
																} catch {
																	// ignore
																}
															}}
															className="wpgraphql-ide-query-composer-inline"
														>
															<div className="wpgraphql-ide-panel-header">
																<span className="wpgraphql-ide-panel-title">
																	Query
																	Composer
																</span>
																<div className="wpgraphql-ide-panel-header-spacer" />
																<Button
																	className="wpgraphql-ide-panel-close"
																	onClick={() =>
																		setLeftPanel(
																			null
																		)
																	}
																	aria-label="Close Query Composer panel"
																	size="small"
																>
																	<Icon
																		icon={
																			close
																		}
																		size={
																			20
																		}
																	/>
																</Button>
															</div>
															<ComposerContent />
														</ResizableBox>
													)}
												{showDocSettingsPanel &&
													docSettingsFields.length >
														0 && (
														<ResizableBox
															size={{
																width: docSettingsPanelWidth,
																height: '100%',
															}}
															minWidth={240}
															maxWidth={600}
															enable={{
																top: false,
																right: true,
																bottom: false,
																left: false,
															}}
															onResizeStop={(
																e,
																d,
																elt
															) => {
																const w =
																	elt.offsetWidth;
																setDocSettingsPanelWidth(
																	w
																);
																try {
																	window.localStorage.setItem(
																		'wpgraphql_ide_settings_panel_width',
																		String(
																			w
																		)
																	);
																} catch {
																	// ignore
																}
															}}
															className="wpgraphql-ide-doc-settings-inline"
														>
															<div className="wpgraphql-ide-panel-header">
																<span className="wpgraphql-ide-panel-title">
																	Document
																	Settings
																</span>
																<div className="wpgraphql-ide-panel-header-spacer" />
																<Button
																	className="wpgraphql-ide-panel-close"
																	onClick={() =>
																		setLeftPanel(
																			null
																		)
																	}
																	aria-label="Close Document Settings panel"
																	size="small"
																>
																	<Icon
																		icon={
																			close
																		}
																		size={
																			20
																		}
																	/>
																</Button>
															</div>
															<DocumentSettingsDrawer
																fields={
																	docSettingsFields
																}
																values={
																	docSettingsValues
																}
																onChange={
																	handleDocumentSettingChange
																}
																globalGrantMode={
																	docSettingsGlobalGrant
																}
															/>
														</ResizableBox>
													)}
												{
													<GraphQLEditor
														key={
															activeDocument?.id ||
															'empty'
														}
														className={
															isPublished
																? 'is-readonly'
																: ''
														}
														value={query}
														onChange={
															handleQueryChange
														}
														schema={schema}
														readOnly={isPublished}
														extraKeys={
															editorKeyBindings.current
														}
														onShowInDocs={
															handleShowInDocs
														}
														onCursorChange={
															setCursorOffset
														}
													/>
												}
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
													{(() => {
														const PlayIcon = (
															<svg
																viewBox="0 0 24 24"
																width="16"
																height="16"
																fill="currentColor"
															>
																<path d="M8 5v14l11-7z" />
															</svg>
														);
														const StopIcon = (
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
														);
														if (
															!isFetching &&
															operationNames.length >
																1
														) {
															return (
																<Dropdown
																	popoverProps={{
																		placement:
																			'top-end',
																	}}
																	renderToggle={({
																		isOpen,
																		onToggle,
																	}) => (
																		<Tooltip text="Execute (pick operation)">
																			<Button
																				variant="primary"
																				onClick={
																					onToggle
																				}
																				aria-expanded={
																					isOpen
																				}
																				disabled={
																					isSchemaLoading
																				}
																				className="wpgraphql-ide-send-button"
																				size="compact"
																				aria-label="Execute query"
																			>
																				{
																					PlayIcon
																				}
																			</Button>
																		</Tooltip>
																	)}
																	renderContent={({
																		onClose:
																			closeMenu,
																	}) => (
																		<NavigableMenu>
																			<MenuGroup label="Run operation">
																				{operationNames.map(
																					(
																						name
																					) => (
																						<MenuItem
																							key={
																								name
																							}
																							onClick={() => {
																								closeMenu();
																								executeQuery(
																									name
																								);
																							}}
																						>
																							{
																								name
																							}
																						</MenuItem>
																					)
																				)}
																			</MenuGroup>
																		</NavigableMenu>
																	)}
																/>
															);
														}
														return (
															<Tooltip
																text={
																	isFetching
																		? 'Stop (Cmd+Enter)'
																		: 'Execute (Cmd+Enter)'
																}
															>
																<Button
																	variant="primary"
																	onClick={() =>
																		executeQuery()
																	}
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
																	{isFetching
																		? StopIcon
																		: PlayIcon}
																</Button>
															</Tooltip>
														);
													})()}
												</div>
											</div>
										</ResizableBox>
										<TabPanel
											className="wpgraphql-ide-editor-tools"
											tabs={editorBottomTabs}
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
