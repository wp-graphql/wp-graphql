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
	reorderCollections as persistCollectionOrder,
} from '../../api/documents';
import { getPreferences, savePreference } from '../../api/preferences';

const VALID_SORT_MODES = ['manual', 'title_asc', 'modified_desc', 'status'];

/**
 * Apply a user-saved order to a collections list. IDs not in the
 * preference fall through in their original order at the end so a
 * newly-created collection still shows up.
 *
 * @param {Array<Object>} collections Collection list as fetched.
 * @param {Array<number>} orderPref   Preferred order from user meta.
 * @return {Array<Object>} Sorted collection list.
 */
function sortCollectionsByPreference(collections, orderPref) {
	if (!orderPref || orderPref.length === 0) {
		return collections;
	}
	const byId = new Map(collections.map((c) => [c.id, c]));
	const ordered = [];
	for (const id of orderPref) {
		if (byId.has(id)) {
			ordered.push(byId.get(id));
			byId.delete(id);
		}
	}
	for (const c of byId.values()) {
		ordered.push(c);
	}
	return ordered;
}

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
				const [collections, prefs] = await Promise.all([
					fetchCollections(),
					getPreferences().catch(() => ({})),
				]);
				const orderPref = Array.isArray(prefs.collection_order)
					? prefs.collection_order.map(Number)
					: [];
				const sorted = sortCollectionsByPreference(
					collections,
					orderPref
				);
				dispatch({ type: 'SET_COLLECTIONS', collections: sorted });

				const rawModes =
					prefs.collection_sort_modes &&
					typeof prefs.collection_sort_modes === 'object'
						? prefs.collection_sort_modes
						: {};
				const modes = {};
				for (const [key, value] of Object.entries(rawModes)) {
					if (
						VALID_SORT_MODES.includes(value) &&
						value !== 'manual'
					) {
						modes[key] = value;
					}
				}
				dispatch({ type: 'SET_COLLECTION_SORT_MODES', modes });
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
	 * Reorder collections by an explicit ID list. Used by drag-and-drop.
	 * Optimistic local update + server persist.
	 *
	 * @param {Array<number>} ids Term IDs in their new order.
	 */
	reorderCollections:
		(ids) =>
		async ({ dispatch, select: sel }) => {
			const list = sel.getCollections();
			const sorted = sortCollectionsByPreference(list, ids);
			dispatch({ type: 'SET_COLLECTIONS', collections: sorted });
			try {
				await persistCollectionOrder(sorted.map((c) => c.id));
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to persist collection order:', error);
			}
		},

	/**
	 * Set the sort mode for a single collection (or virtual section
	 * key like '_documents' / '_unsaved') and persist the full map
	 * to user meta.
	 *
	 * @param {string|number} key  Collection ID or virtual section key.
	 * @param {string}        mode One of 'manual', 'title_asc', 'modified_desc', 'status'.
	 */
	setCollectionSortMode:
		(key, mode) =>
		async ({ dispatch, select: sel }) => {
			const next = VALID_SORT_MODES.includes(mode) ? mode : 'manual';
			const normalizedKey = String(key);
			dispatch({
				type: 'SET_COLLECTION_SORT_MODE',
				key: normalizedKey,
				mode: next,
			});
			const updated = { ...sel.getCollectionSortModes() };
			if (next === 'manual') {
				delete updated[normalizedKey];
			} else {
				updated[normalizedKey] = next;
			}
			try {
				await savePreference('collection_sort_modes', updated);
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Failed to persist collection sort mode:', error);
			}
		},
};

export default actions;
