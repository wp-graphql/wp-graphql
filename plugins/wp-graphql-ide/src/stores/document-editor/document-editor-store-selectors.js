import { createSelector } from '@wordpress/data';

const selectors = {
	buttons: createSelector(
		(state) => {
			const buttons = Object.entries(state.buttons).map(
				([name, button]) => ({
					name,
					...button,
				})
			);

			return buttons.sort((a, b) => a.priority - b.priority);
		},
		(state) => [state.buttons]
	),

	getDocuments: createSelector(
		(state) => Object.values(state.documents),
		(state) => [state.documents]
	),

	getOpenTabs: (state) => state.openTabs.map((tab) => tab.id),

	getOpenTabObjects: (state) => state.openTabs,

	getActiveTab: (state) => state.activeTab,

	getActiveTabType: (state) =>
		state.openTabs.find((tab) => tab.id === state.activeTab)?.type || null,

	getActiveDocument: (state) => {
		if (!state.activeTab) {
			return null;
		}
		return state.documents[state.activeTab] || null;
	},

	getTabTypes: (state) => state.tabTypes,

	getTabType: (state, name) => state.tabTypes[name] || null,
};

export default selectors;
