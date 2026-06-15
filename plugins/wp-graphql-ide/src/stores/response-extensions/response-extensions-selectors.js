import { createSelector } from '@wordpress/data';

const selectors = {
	extensionTabs: createSelector(
		(state) => {
			const tabs = Object.entries(state.extensionTabs).map(
				([name, tab]) => ({
					name,
					...tab,
				})
			);
			return tabs.sort((a, b) => a.priority - b.priority);
		},
		(state) => [state.extensionTabs]
	),
};

export default selectors;
