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
};

export default selectors;
