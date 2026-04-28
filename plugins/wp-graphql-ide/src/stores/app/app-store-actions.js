import { parse, print } from 'graphql';
import { mergeAst } from '@graphiql/toolkit';
import { select } from '@wordpress/data';
import {
	getHistory as fetchHistory,
	createHistoryEntry as postHistoryEntry,
	clearHistory as deleteAllHistory,
} from '../../api/history';
import {
	getCollections as fetchCollections,
	createCollection as postCollection,
	renameCollection as patchCollection,
	deleteCollection as removeCollection,
} from '../../api/documents';

/**
 * The initial state of the app.
 *
 * @type {Object}
 */
const actions = {
	setQuery: (query) => {
		return {
			type: 'SET_QUERY',
			query,
		};
	},
	setSchema: (schema) => {
		return {
			type: 'SET_SCHEMA',
			schema,
		};
	},
	prettifyQuery: (query) => {
		let editedQuery = query;
		try {
			editedQuery = print(parse(editedQuery));
		} catch (error) {
			console.warn(error);
		}

		return {
			type: 'SET_QUERY',
			query: editedQuery,
		};
	},
	mergeQuery: (query) => {
		const documentAst = parse(query);
		const schema = select('wpgraphql-ide/app').schema();
		const merged = print(mergeAst(documentAst, schema));
		return {
			type: 'SET_QUERY',
			query: merged,
		};
	},
	setDrawerOpen: (isDrawerOpen) => {
		return {
			type: 'SET_DRAWER_OPEN',
			isDrawerOpen,
		};
	},
	setShouldRenderStandalone: (shouldRenderStandalone) => {
		return {
			type: 'SET_RENDER_STANDALONE',
			shouldRenderStandalone,
		};
	},
	setInitialStateLoaded: () => {
		return {
			type: 'SET_INITIAL_STATE_LOADED',
		};
	},
	toggleAuthentication: () => {
		return {
			type: 'TOGGLE_AUTHENTICATION',
		};
	},
	setVariables: (variables) => {
		return {
			type: 'SET_VARIABLES',
			variables,
		};
	},
	setHeaders: (headers) => {
		return {
			type: 'SET_HEADERS',
			headers,
		};
	},
	setResponse: (response) => {
		return {
			type: 'SET_RESPONSE',
			response,
		};
	},
	setResponseHeaders: (responseHeaders) => {
		return {
			type: 'SET_RESPONSE_HEADERS',
			responseHeaders,
		};
	},
	setResponseMeta: (meta) => {
		return {
			type: 'SET_RESPONSE_META',
			meta: meta || {},
		};
	},
	setHttpMethod: (method) => {
		return {
			type: 'SET_HTTP_METHOD',
			method,
		};
	},
	setIsFetching: (isFetching) => {
		return {
			type: 'SET_IS_FETCHING',
			isFetching,
		};
	},

	/**
	 * Request the Docs Explorer panel to navigate to a type/field. Pass null
	 * to clear the request once the panel has consumed it.
	 *
	 * @param {{ typeName: string, fieldName: ?string } | null} target
	 */
	setDocsNavTarget: (target) => {
		return {
			type: 'SET_DOCS_NAV_TARGET',
			target,
		};
	},

	/**
	 * Load global execution history from the server.
	 */
	loadHistory:
		() =>
		async ({ dispatch }) => {
			try {
				const entries = await fetchHistory();
				dispatch({ type: 'SET_HISTORY', history: entries });
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to load history:', error);
			}
		},

	/**
	 * Add a new history entry and persist it via the REST API.
	 * Prunes the oldest entry if the limit is exceeded.
	 *
	 * @param {Object} entry History entry data.
	 */
	addHistoryEntry:
		(entry) =>
		async ({ dispatch, select: sel }) => {
			try {
				const current = sel.getHistory();
				const oldestId =
					current.length >= 50
						? current[current.length - 1]?.id
						: undefined;

				const created = await postHistoryEntry({
					...entry,
					oldestId,
				});

				dispatch({ type: 'ADD_HISTORY_ENTRY', entry: created });

				// Remove the pruned entry from local state.
				if (oldestId) {
					const updated = sel
						.getHistory()
						.filter((e) => e.id !== oldestId);
					dispatch({ type: 'SET_HISTORY', history: updated });
				}
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to save history entry:', error);
			}
		},

	/**
	 * Clear all history entries.
	 */
	clearAllHistory:
		() =>
		async ({ dispatch, select: sel }) => {
			try {
				const ids = sel.getHistory().map((e) => e.id);
				dispatch({ type: 'CLEAR_HISTORY' });
				await deleteAllHistory(ids);
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to clear history:', error);
			}
		},

	/**
	 * Load collections from the server.
	 */
	loadCollections:
		() =>
		async ({ dispatch }) => {
			try {
				const collections = await fetchCollections();
				dispatch({ type: 'SET_COLLECTIONS', collections });
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to load collections:', error);
			}
		},

	/**
	 * Create a new collection.
	 *
	 * @param {string} name Collection name.
	 */
	addCollection:
		(name) =>
		async ({ dispatch }) => {
			try {
				const collection = await postCollection(name);
				dispatch({ type: 'ADD_COLLECTION', collection });
				return collection;
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to create collection:', error);
				return null;
			}
		},

	/**
	 * Delete a collection.
	 *
	 * @param {number} id Term ID.
	 */
	removeCollection:
		(id) =>
		async ({ dispatch }) => {
			try {
				dispatch({ type: 'REMOVE_COLLECTION', id });
				await removeCollection(id);
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to delete collection:', error);
			}
		},

	/**
	 * Set the active collection filter.
	 *
	 * @param {number|null} id Collection term ID, or null for all.
	 */
	setActiveCollection: (id) => ({
		type: 'SET_ACTIVE_COLLECTION',
		id,
	}),

	/**
	 * Rename a collection.
	 *
	 * @param {number} id   Term ID.
	 * @param {string} name New name.
	 */
	renameCollection:
		(id, name) =>
		async ({ dispatch }) => {
			try {
				const updated = await patchCollection(id, name);
				dispatch({ type: 'UPDATE_COLLECTION', collection: updated });
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to rename collection:', error);
			}
		},

	/**
	 * Move a collection up or down in the list.
	 *
	 * @param {number} id        Term ID.
	 * @param {string} direction 'up' or 'down'.
	 */
	moveCollection:
		(id, direction) =>
		({ dispatch, select: sel }) => {
			const list = [...sel.getCollections()];
			const idx = list.findIndex((c) => c.id === id);
			if (idx === -1) {
				return;
			}
			const swapIdx = direction === 'up' ? idx - 1 : idx + 1;
			if (swapIdx < 0 || swapIdx >= list.length) {
				return;
			}
			[list[idx], list[swapIdx]] = [list[swapIdx], list[idx]];
			dispatch({ type: 'SET_COLLECTIONS', collections: list });
		},
};

export default actions;
