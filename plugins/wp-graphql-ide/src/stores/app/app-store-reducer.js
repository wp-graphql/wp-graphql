import { parse } from 'graphql';

/**
 * The initial state of the app.
 * @type {Object}
 */
const initialState = {
	isDrawerOpen: false,
	shouldRenderStandalone: false,
	isInitialStateLoaded: false,
	registeredPlugins: {},
	query: null,
	schema: undefined, // undefined is necessary to trigger the initial fetch
	isAuthenticated: true,
};

/**
 * Set the query in the state as long as it is a valid GraphQL query and not the same as the current query.
 *
 * @param {Object} state  The current state of the store.
 * @param {Object} action The action to be performed.
 *
 * @return {Object} The new state after applying the action.
 */
const setQuery = (state, action) => {
	const editedQuery = action.query;
	const query = state.query;

	if (editedQuery === query) {
		return { ...state };
	}

	if (null === editedQuery || '' === editedQuery) {
		// allow clearing the query without parsing
	} else {
		try {
			parse(editedQuery);
		} catch (error) {
			return { ...state };
		}
	}

	return {
		...state,
		query: action.query,
	};
};

/**
 * The reducer for the app store.
 * @param {Object} state  The current state of the store.
 * @param {Object} action The action to be performed.
 * @return {Object} The new state after applying the action.
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
		case 'REGISTER_PLUGIN':
			return {
				...state,
				registeredPlugins: {
					...state.registeredPlugins,
					[action.name]: action.config,
				},
			};
		case 'TOGGLE_AUTHENTICATION':
			return {
				...state,
				isAuthenticated: !state.isAuthenticated,
			};
	}
	return state;
};

export default reducer;
