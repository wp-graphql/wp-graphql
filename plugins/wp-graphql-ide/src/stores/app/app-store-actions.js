import { parse, print } from 'graphql';
import { mergeAst } from '@graphiql/toolkit';
import { select } from '@wordpress/data';
import {
	getHistory as fetchHistory,
	createHistoryEntry as postHistoryEntry,
	clearHistory as deleteAllHistory,
} from '../../api/history';

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
	registerPlugin: (name, config) => {
		return {
			type: 'REGISTER_PLUGIN',
			name,
			config,
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
	setIsFetching: (isFetching) => {
		return {
			type: 'SET_IS_FETCHING',
			isFetching,
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
};

export default actions;
