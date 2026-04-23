import { createSelector } from '@wordpress/data';

/**
 * Selectors for the activity bar.
 *
 * @type {Object}
 */
const selectors = {
	activityPanels: createSelector(
		(state) => {
			const panels = Object.entries(state.activityPanels).map(
				([name, activityPanel]) => ({
					name,
					...activityPanel,
				})
			);

			return panels.sort((a, b) => a.priority - b.priority);
		},
		(state) => [state.activityPanels]
	),
	visiblePanel: createSelector(
		(state) => {
			if (!state.visiblePanel) {
				return null;
			}
			const panel = state.activityPanels[state.visiblePanel];
			return panel ? { name: state.visiblePanel, ...panel } : null;
		},
		(state) => [state.visiblePanel, state.activityPanels]
	),
};

export default selectors;
