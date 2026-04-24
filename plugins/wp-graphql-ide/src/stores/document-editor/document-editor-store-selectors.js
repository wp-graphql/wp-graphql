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

	getOpenTabs: (state) => state.openTabs,

	getActiveTab: (state) => state.activeTab,

	getActiveDocument: (state) => {
		if (!state.activeTab) {
			return null;
		}
		return state.documents[state.activeTab] || null;
	},
};

export default selectors;
