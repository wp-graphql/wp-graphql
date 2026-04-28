/**
 * The initial state of the app.
 * @type {Object}
 */
const initialState = {
	isDrawerOpen: false,
	shouldRenderStandalone: false,
	isInitialStateLoaded: false,
	query: null,
	schema: undefined, // undefined is necessary to trigger the initial fetch
	isAuthenticated: true,
	variables: '',
	headers: '',
	response: '',
	responseHeaders: null,
	responseStatus: null,
	responseDuration: null,
	responseSize: null,
	isFetching: false,
	history: [],
	httpMethod: 'POST',
	// One-shot docs-nav request: { typeName, fieldName } | null. Set when
	// something outside the Docs panel (e.g. the editor's cmd-click handler)
	// wants the panel to navigate. The Docs panel reads this, pushes a frame
	// onto its own navigation stack, and dispatches a clear back to null.
	docsNavTarget: null,
};

/**
 * Set the query in the state as long as it is a valid GraphQL query and not the same as the current query.
 *
 * @param {Object} state  The current state of the store.
 * @param {Object} action The action to be performed.
 *
 * @return {Object}
 */
/**
 * Set the query in state. Validation is handled by CodeMirror's
 * cm6-graphql extension, not the reducer.
 *
 * @param {Object} state  Current state.
 * @param {Object} action Action with query string.
 * @return {Object} New state.
 */
const setQuery = (state, action) => {
	if (action.query === state.query) {
		return state;
	}
	return { ...state, query: action.query };
};

/**
 * The reducer for the app store.
 * @param {Object} state  The current state of the store.
 * @param {Object} action The action to be performed.
 * @return {Object}
 */
const reducer = (state = initialState, action) => {
	switch (action.type) {
		case 'SET_RENDER_STANDALONE':
			return {
				...state,
				shouldRenderStandalone: action.shouldRenderStandalone,
			};
		case 'SET_QUERY':
			return setQuery(state, action);
		case 'SET_SCHEMA':
			return {
				...state,
				schema: action.schema,
			};
		case 'SET_DRAWER_OPEN':
			return {
				...state,
				isDrawerOpen: action.isDrawerOpen,
			};
		case 'SET_INITIAL_STATE_LOADED':
			return {
				...state,
				isInitialStateLoaded: true,
			};
		case 'TOGGLE_AUTHENTICATION':
			return {
				...state,
				isAuthenticated: !state.isAuthenticated,
			};
		case 'SET_VARIABLES':
			return {
				...state,
				variables: action.variables,
			};
		case 'SET_HEADERS':
			return {
				...state,
				headers: action.headers,
			};
		case 'SET_RESPONSE':
			return {
				...state,
				response: action.response,
			};
		case 'SET_RESPONSE_HEADERS':
			return {
				...state,
				responseHeaders: action.responseHeaders,
			};
		case 'SET_RESPONSE_META':
			return {
				...state,
				responseStatus: action.meta.status ?? null,
				responseDuration: action.meta.duration ?? null,
				responseSize: action.meta.size ?? null,
			};
		case 'SET_IS_FETCHING':
			return {
				...state,
				isFetching: action.isFetching,
			};
		case 'SET_DOCS_NAV_TARGET':
			return {
				...state,
				docsNavTarget: action.target || null,
			};
		case 'SET_HISTORY':
			return {
				...state,
				history: action.history,
			};
		case 'ADD_HISTORY_ENTRY':
			return {
				...state,
				history: [action.entry, ...state.history],
			};
		case 'CLEAR_HISTORY':
			return {
				...state,
				history: [],
			};
		case 'SET_HTTP_METHOD':
			return {
				...state,
				httpMethod: action.method,
			};
	}
	return state;
};

export default reducer;
