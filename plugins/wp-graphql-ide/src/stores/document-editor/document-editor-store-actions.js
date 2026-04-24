import {
	getDocuments,
	createDocument,
	updateDocument,
	deleteDocument,
} from '../../api/documents';
import { getPreferences, savePreference } from '../../api/preferences';

const actions = {
	registerButton: (name, config, priority) => ({
		type: 'REGISTER_BUTTON',
		name,
		config,
		priority,
	}),

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
	 * Load documents and preferences from the server.
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
	 * Create a new tab with an empty document.
	 * @param {string} title
	 */
	createTab:
		(title = 'Untitled') =>
		async ({ dispatch }) => {
			try {
				const doc = await createDocument({
					title,
					query: '',
				});

				dispatch({ type: 'ADD_DOCUMENT', document: doc });
				dispatch({
					type: 'OPEN_TAB',
					tabId: String(doc.id),
				});
				dispatch({
					type: 'SET_ACTIVE_TAB',
					tabId: String(doc.id),
				});

				await dispatch.persistTabState();
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to create tab:', error);
			}
		},

	/**
	 * Close a tab. If it's the active tab, switch to an adjacent one.
	 * @param {string} tabId
	 */
	closeTab:
		(tabId) =>
		async ({ dispatch, select }) => {
			dispatch({ type: 'CLOSE_TAB', tabId: String(tabId) });

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
	 * @param {string} tabId
	 */
	switchTab:
		(tabId) =>
		async ({ dispatch }) => {
			// OPEN_TAB is a no-op when the tab is already present.
			dispatch({ type: 'OPEN_TAB', tabId: String(tabId) });
			dispatch({ type: 'SET_ACTIVE_TAB', tabId: String(tabId) });
			await dispatch.persistTabState();
		},

	/**
	 * Save the current document content to the server.
	 * @param {number} id
	 * @param {Object} data
	 */
	saveDocument:
		(id, data) =>
		async ({ dispatch }) => {
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
	 * @param {number} id
	 */
	removeDocument:
		(id) =>
		async ({ dispatch }) => {
			try {
				dispatch({ type: 'CLOSE_TAB', tabId: String(id) });
				dispatch({ type: 'REMOVE_DOCUMENT', id });
				await deleteDocument(id, true);
				await dispatch.persistTabState();
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to delete document:', error);
			}
		},

	/**
	 * Persist open tabs and active tab to user meta.
	 */
	persistTabState:
		() =>
		async ({ select }) => {
			const openTabs = select.getOpenTabs();
			const activeTab = select.getActiveTab();

			try {
				await Promise.all([
					savePreference('open_tabs', openTabs),
					savePreference('active_tab', activeTab),
				]);
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to persist tab state:', error);
			}
		},
};

export default actions;
