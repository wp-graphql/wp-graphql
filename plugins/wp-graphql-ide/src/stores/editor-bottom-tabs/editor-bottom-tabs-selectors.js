import { createSelector } from '@wordpress/data';

const selectors = {
	bottomTabs: createSelector(
		(state) => {
			const tabs = Object.entries(state.bottomTabs).map(
				([name, tab]) => ({
					name,
					...tab,
				})
			);
			return tabs.sort((a, b) => a.priority - b.priority);
		},
		(state) => [state.bottomTabs]
	),
};

export default selectors;
