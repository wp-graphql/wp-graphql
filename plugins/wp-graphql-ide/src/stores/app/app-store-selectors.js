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
	getHistory: (state) => {
		return state.history;
	},
	getDocsNavTarget: (state) => {
		return state.docsNavTarget;
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
};

export default selectors;
