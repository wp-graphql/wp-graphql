import { parse, print } from 'graphql';
import { mergeAst } from '@graphiql/toolkit';
import { select } from '@wordpress/data';

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
};

export default actions;
