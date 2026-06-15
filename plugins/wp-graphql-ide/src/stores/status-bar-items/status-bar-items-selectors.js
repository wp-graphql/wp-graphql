import { createSelector } from '@wordpress/data';

const selectors = {
	statusBarItems: createSelector(
		(state) => {
			const items = Object.entries(state.items).map(([name, item]) => ({
				name,
				...item,
			}));
			return items.sort((a, b) => a.priority - b.priority);
		},
		(state) => [state.items]
	),
};

export default selectors;
