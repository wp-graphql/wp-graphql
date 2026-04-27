import {
	getDocuments,
	createDocument,
	updateDocument,
	deleteDocument,
	publishDocument,
} from '../../api/documents';
import { getPreferences, savePreference } from '../../api/preferences';

/**
 * Check whether a document ID is a temporary (unsaved) client-side ID.
 *
 * @param {string|number} id Document ID.
 * @return {boolean} True if the ID is temporary.
 */
export function isTempId(id) {
	return String(id).startsWith('temp-');
}

const actions = {
	registerButton: (name, config, priority) => ({
		type: 'REGISTER_BUTTON',
		name,
		config,
		priority,
	}),

	/**
	 * Register a workspace tab type with a content renderer.
	 *
	 * @param {string} name   Unique tab type identifier.
	 * @param {Object} config Tab type config (title, content component, optional icon).
	 */
	registerTabType: (name, config) => ({
		type: 'REGISTER_TAB_TYPE',
		name,
		config,
	}),

	/**
	 * Open a workspace tab of a given type. Creates a virtual tab entry
	 * (not backed by a document) and makes it active.
	 *
	 * @param {string} name  Tab type name (must be registered).
	 * @param {string} tabId Unique tab ID.
	 * @param {string} title Display title for the tab.
	 */
	openWorkspaceTab:
		(name, tabId, title) =>
		async ({ dispatch, select }) => {
			// If already open, just switch to it.
			if (select.getOpenTabs().includes(tabId)) {
				dispatch({ type: 'SET_ACTIVE_TAB', tabId });
				return;
			}
			dispatch({
				type: 'OPEN_TAB',
				tabId,
				tabType: name,
			});
			dispatch({ type: 'SET_ACTIVE_TAB', tabId });
			// Store a minimal virtual document for the tab title.
			dispatch({
				type: 'UPDATE_DOCUMENT',
				document: { id: tabId, title: title || name },
			});
		},

	setDocumentDirty: (id, dirty) => ({
		type: 'SET_DOCUMENT_DIRTY',
		id,
		dirty,
	}),

	setDocumentResponse: (id, response) => ({
		type: 'SET_DOCUMENT_RESPONSE',
		id,
		response,
	}),

	/**
	 * Load saved documents and preferences from the server.
	 */
	loadDocuments:
		() =>
		async ({ dispatch }) => {
			try {
				const [docs, prefs] = await Promise.all([
					getDocuments(),
					getPreferences(),
				]);

				dispatch({ type: 'SET_DOCUMENTS', documents: docs });

				const openTabs = prefs.open_tabs || [];
				const activeTab = prefs.active_tab || '';

				if (docs.length > 0) {
					// Restore open tabs, filtering to docs that still exist.
					const docIds = docs.map((d) => String(d.id));
					const validTabs = openTabs.filter((id) =>
						docIds.includes(String(id))
					);

					if (validTabs.length > 0) {
						dispatch({
							type: 'SET_OPEN_TABS',
							tabIds: validTabs,
						});
						const validActive = validTabs.includes(
							String(activeTab)
						)
							? String(activeTab)
							: validTabs[0];
						dispatch({
							type: 'SET_ACTIVE_TAB',
							tabId: validActive,
						});
					} else {
						// No saved tabs — open the first document.
						dispatch({
							type: 'SET_OPEN_TABS',
							tabIds: [String(docs[0].id)],
						});
						dispatch({
							type: 'SET_ACTIVE_TAB',
							tabId: String(docs[0].id),
						});
					}
				}
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to load IDE documents:', error);
			}
		},

	/**
	 * Create a new in-memory tab. No server call — the document only
	 * persists when the user explicitly saves (via saveTab).
	 *
	 * @param {string} title Tab title.
	 */
	createTab: (title = 'Untitled') => ({
		type: 'CREATE_IN_MEMORY_TAB',
		title,
		tempId: `temp-${Date.now()}`,
	}),

	/**
	 * Save the active document to the server.
	 *
	 * If the document has a temporary ID (never saved), creates a new
	 * CPT post and swaps the temp ID for the real server ID. If it has
	 * a real ID, updates the existing post.
	 *
	 * @param {string|number} id   Document ID (may be temp).
	 * @param {Object}        data Document fields to save.
	 * @return {Promise<Object|null>} Saved document or null on error.
	 */
	saveTab:
		(id, data) =>
		async ({ dispatch, select }) => {
			try {
				const doc = select
					.getDocuments()
					.find((d) => String(d.id) === String(id));
				if (!doc) {
					return null;
				}

				const payload = {
					title: data.title ?? doc.title ?? 'Untitled',
					query: data.query ?? doc.query ?? '',
					variables: data.variables ?? doc.variables ?? '',
					headers: data.headers ?? doc.headers ?? '',
				};

				let saved;
				if (isTempId(id)) {
					// First save — create on server.
					saved = await createDocument(payload);
					dispatch({
						type: 'UPDATE_DOCUMENT_ID',
						oldId: String(id),
						newId: String(saved.id),
						document: saved,
					});
				} else {
					// Subsequent save — update existing.
					saved = await updateDocument(id, payload);
					dispatch({ type: 'UPDATE_DOCUMENT', document: saved });
				}

				dispatch({
					type: 'SET_DOCUMENT_DIRTY',
					id: String(saved.id),
					dirty: false,
				});

				await dispatch.persistTabState();
				return saved;
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to save document:', error);
				throw error;
			}
		},

	/**
	 * Publish a saved document (change status from draft to publish).
	 *
	 * @param {string|number} id Document ID.
	 * @return {Promise<Object|null>} Published document or null on error.
	 */
	/**
	 * Publish a draft document via the server-side hash endpoint.
	 *
	 * The server normalizes the query, computes its SHA-256 hash,
	 * sets it as the slug (queryId), and changes status to publish.
	 * If the query already exists as a published document, the
	 * server returns the existing document.
	 *
	 * @param {string|number} id Document ID (must be a saved draft).
	 * @return {Promise<Object|null>} Publish result or null.
	 */
	publishTab:
		(id) =>
		async ({ dispatch }) => {
			if (isTempId(id)) {
				return null;
			}
			try {
				const result = await publishDocument(id);
				dispatch({
					type: 'UPDATE_DOCUMENT',
					document: {
						id: result.id,
						status: 'publish',
						queryHash: result.query_hash,
					},
				});
				return result;
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to publish document:', error);
				throw error;
			}
		},

	/**
	 * Close a tab. If it's the active tab, switch to an adjacent one.
	 *
	 * @param {string} tabId Tab ID to close.
	 */
	closeTab:
		(tabId) =>
		async ({ dispatch, select }) => {
			dispatch({ type: 'CLOSE_TAB', tabId: String(tabId) });

			// If the closed doc was temp (unsaved), remove it from state.
			if (isTempId(tabId)) {
				dispatch({ type: 'REMOVE_DOCUMENT', id: tabId });
			}

			const openTabs = select.getOpenTabs();
			const activeTab = select.getActiveTab();

			// If we closed the active tab, switch to first remaining.
			if (String(tabId) === activeTab && openTabs.length > 0) {
				dispatch({
					type: 'SET_ACTIVE_TAB',
					tabId: openTabs[0],
				});
			}

			await dispatch.persistTabState();
		},

	/**
	 * Switch to a tab, opening it first if it isn't already in the tab bar.
	 *
	 * @param {string} tabId   Tab ID to switch to.
	 * @param {string} tabType Optional tab type (defaults to 'query-editor').
	 */
	switchTab:
		(tabId, tabType) =>
		async ({ dispatch }) => {
			dispatch({
				type: 'OPEN_TAB',
				tabId: String(tabId),
				tabType,
			});
			dispatch({ type: 'SET_ACTIVE_TAB', tabId: String(tabId) });
			await dispatch.persistTabState();
		},

	/**
	 * Save document content to the server (legacy — use saveTab for
	 * the explicit save flow).
	 *
	 * @param {number} id   Post ID.
	 * @param {Object} data Fields to update.
	 */
	saveDocument:
		(id, data) =>
		async ({ dispatch }) => {
			if (isTempId(id)) {
				// In-memory doc — update local state only.
				dispatch({
					type: 'UPDATE_DOCUMENT',
					document: { id, ...data },
				});
				return;
			}
			try {
				const doc = await updateDocument(id, data);
				dispatch({ type: 'UPDATE_DOCUMENT', document: doc });
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to save document:', error);
			}
		},

	/**
	 * Delete a document permanently.
	 *
	 * @param {number} id Post ID.
	 */
	removeDocument:
		(id) =>
		async ({ dispatch }) => {
			try {
				dispatch({ type: 'CLOSE_TAB', tabId: String(id) });
				dispatch({ type: 'REMOVE_DOCUMENT', id });
				if (!isTempId(id)) {
					await deleteDocument(id, true);
				}
				await dispatch.persistTabState();
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to delete document:', error);
			}
		},

	/**
	 * Persist open tabs and active tab to user meta.
	 * Only includes saved (non-temp) document IDs.
	 */
	persistTabState:
		() =>
		async ({ select }) => {
			const openTabs = select.getOpenTabs().filter((id) => !isTempId(id));
			const activeTab = select.getActiveTab();
			const persistedActive = isTempId(activeTab) ? '' : activeTab;

			try {
				await Promise.all([
					savePreference('open_tabs', openTabs),
					savePreference('active_tab', persistedActive),
				]);
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to persist tab state:', error);
			}
		},
};

export default actions;
