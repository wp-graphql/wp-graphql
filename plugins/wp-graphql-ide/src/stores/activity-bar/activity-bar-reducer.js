/**
 * The initial state of the activity bar.
 * @type {Object}
 */
const initialState = {
	activityPanels: {},
	visiblePanel: null,
	utilities: {},
};

/**
 * The reducer for the app store.
 * @param {Object} state  The current state of the store.
 * @param {Object} action The action to be performed.
 * @return {Object}
 */
const reducer = (state = initialState, action) => {
	switch (action.type) {
		case 'REGISTER_UTILITY':
			// Ensure button name is unique
			if (action.name in state.utilities) {
				console.warn({
					message: `The "${action.name}" utility already exists. Name must be unique.`,
					existingUtility: state.utilities[action.name],
					duplicateUtility: action.config,
				});
				return state;
			}

			const utility = {
				config: action.config,
				priority: action.priority || 10, // default priority to 10 if not provided
			};

			return {
				...state,
				utilities: {
					...state.utilities,
					[action.name]: utility,
				},
			};
		case 'REGISTER_PANEL':
			// Ensure panel name is unique
			if (action.name in state.activityPanels) {
				console.warn({
					message: `The "${action.name}" panel already exists. Name must be unique.`,
					existingPanel: state.activityPanels[action.name],
					duplicatePanel: action.config,
				});
				return state;
			}

			// Ensure config is a function before calling it
			if (typeof action.config.content !== 'function') {
				console.error(
					`Config for panel "${action.name}" requires a content callback.`
				);
				return state;
			}

			if (
				'icon' in action.config &&
				typeof action.config.icon !== 'function'
			) {
				console.error(
					`Config for panel "${action.name}" requires an icon callback.`
				);
				return state;
			}

			if ((!'title') in action.config) {
				console.error(
					`Config for panel "${action.name}" requires a title.`
				);
				return state;
			}

			const panel = {
				title: action.name,
				priority: action.priority || 10, // default priority to 10 if not provided
				...action.config,
			};

			return {
				...state,
				activityPanels: {
					...state.activityPanels,
					[action.name]: panel,
				},
			};
		case 'TOGGLE_ACTIVITY_PANEL_VISIBILITY':
			console.log({
				message: `Toggling panel visibility.`,
				panel: action.panel,
			});
			return {
				...state,
				visiblePanel:
					state.visiblePanel === action.panel ? null : action.panel,
			};
		default:
			return state;
	}
};

export default reducer;
