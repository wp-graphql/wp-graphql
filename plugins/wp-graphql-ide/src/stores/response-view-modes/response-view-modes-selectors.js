import { createSelector } from '@wordpress/data';

const selectors = {
	responseViewModes: createSelector(
		(state) => {
			const modes = Object.entries(state.viewModes).map(
				([value, mode]) => ({
					value,
					...mode,
				})
			);
			return modes.sort((a, b) => a.priority - b.priority);
		},
		(state) => [state.viewModes]
	),
};

export default selectors;
