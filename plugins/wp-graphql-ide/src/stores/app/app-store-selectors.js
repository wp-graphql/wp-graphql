/**
 * Selectors for the app state.
 * @type {Object}
 */
const selectors = {
	// TODO: update "getQuery" to simply "query" since we are in the context of selectors.
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
	getPluginsArray: (state) => {
		const registeredPlugins = state.registeredPlugins;
		const pluginsArray = [];
		Object.entries(registeredPlugins).forEach(([, config]) => {
			const plugin = () => {
				return config;
			};
			pluginsArray.push(plugin());
		});
		return pluginsArray;
	},
	isAuthenticated: (state) => {
		return state.isAuthenticated;
	},
};

export default selectors;
