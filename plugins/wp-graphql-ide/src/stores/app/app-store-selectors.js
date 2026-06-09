/**
 * Selectors for the app state.
 * @type {Object}
 */
const selectors = {
	// Named "getQuery" for public API consistency. Renaming would be a breaking change.
	getQuery: (state) => {
		return state.query;
	},
	schema: (state) => {
		return state.schema;
	},
	isDrawerOpen: (state) => {
		return state.isDrawerOpen;
	},
	shouldRenderStandalone: (state) => {
		return state.shouldRenderStandalone;
	},
	isInitialStateLoaded: (state) => {
		return state.isInitialStateLoaded;
	},
	isAuthenticated: (state) => {
		return state.isAuthenticated;
	},
	getVariables: (state) => {
		return state.variables;
	},
	getHeaders: (state) => {
		return state.headers;
	},
	getResponse: (state) => {
		return state.response;
	},
	getResponseHeaders: (state) => {
		return state.responseHeaders;
	},
	getResponseStatus: (state) => {
		return state.responseStatus;
	},
	getResponseDuration: (state) => {
		return state.responseDuration;
	},
	getResponseSize: (state) => {
		return state.responseSize;
	},
	getHttpMethod: (state) => {
		return state.httpMethod;
	},
	isFetching: (state) => {
		return state.isFetching;
	},
	getLastExecutedOperation: (state) => {
		return state.lastExecutedOperation || null;
	},
	getHistory: (state) => {
		return state.history;
	},
	// Groups raw history entries by their operation hash (computed at
	// write time). Each group surfaces the most-recent run's context
	// (query / variables / headers / document_id) so a click in the
	// activity-bar panel can re-execute or restore the operation in one
	// step. Entries without an `operationHash` (legacy / unparseable
	// queries) fall back to a per-id pseudo-group so they still appear
	// — just not deduped against anything else.
	//
	// Memoized on `state.history`: the same array reference returns the
	// same grouped array, so `useSelect` doesn't see a fresh reference
	// every read.
	getOperationHistory: (() => {
		let lastInput = null;
		let lastOutput = [];
		return (state) => {
			const entries = state.history;
			if (entries === lastInput) {
				return lastOutput;
			}
			lastInput = entries;
			const groups = new Map();
			for (const e of entries || []) {
				const key = e.operationHash
					? `hash:${e.operationHash}`
					: `id:${e.id}`;
				const prior = groups.get(key);
				if (!prior) {
					groups.set(key, {
						hash: e.operationHash || null,
						runCount: 1,
						latestRun: e.timestamp || 0,
						lastQuery: e.query || '',
						lastVariables: e.variables || '',
						lastHeaders: e.headers || '',
						latestDocId: e.document_id || 0,
						latestStatus: e.status || '',
						latestDurationMs: e.duration_ms || 0,
						latestIsAuthenticated: !!e.is_authenticated,
						latestHttpMethod: e.http_method || 'POST',
					});
				} else {
					prior.runCount += 1;
					if ((e.timestamp || 0) > prior.latestRun) {
						prior.latestRun = e.timestamp || 0;
						prior.lastQuery = e.query || '';
						prior.lastVariables = e.variables || '';
						prior.lastHeaders = e.headers || '';
						prior.latestDocId = e.document_id || 0;
						prior.latestStatus = e.status || '';
						prior.latestDurationMs = e.duration_ms || 0;
						prior.latestIsAuthenticated = !!e.is_authenticated;
						prior.latestHttpMethod = e.http_method || 'POST';
					}
				}
			}
			lastOutput = Array.from(groups.values()).sort(
				(a, b) => b.latestRun - a.latestRun
			);
			return lastOutput;
		};
	})(),
	getDocsNavTarget: (state) => {
		return state.docsNavTarget;
	},
	getCursorOffset: (state) => {
		return state.cursorOffset;
	},
	getEditorJumpRequest: (state) => {
		return state.editorJumpRequest;
	},
	getCollections: (state) => {
		return state.collections;
	},
	getActiveCollection: (state) => {
		return state.activeCollection;
	},
	getCollectionSortModes: (state) => {
		return state.collectionSortModes || {};
	},
	getCollectionSortMode: (state, key) => {
		const modes = state.collectionSortModes || {};
		return modes[key] || 'manual';
	},
	getPersonalCollections: (state) => {
		return state.personalCollections || [];
	},
	getSharedCollections: (state) => {
		return state.sharedCollections || [];
	},
};

export default selectors;
