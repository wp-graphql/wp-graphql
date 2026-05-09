import { createSelector } from '@wordpress/data';

const selectors = {
	documentTabActions: createSelector(
		(state) => {
			const items = Object.entries(state.actions).map(([name, item]) => ({
				name,
				...item,
			}));
			return items.sort((a, b) => a.priority - b.priority);
		},
		(state) => [state.actions]
	),
};

export default selectors;
