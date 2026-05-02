/**
 * The initial state of the activity bar.
 * @type {Object}
 */
// Restore last open panel from localStorage, defaulting to docs-explorer.
let savedPanel = null;
try {
	const stored = window.localStorage.getItem('wpgraphql_ide_visible_panel');
	if (stored !== null) {
		savedPanel = stored || null;
	}
} catch {
	// localStorage unavailable
}

const initialState = {
	activityPanels: {},
	visiblePanel: savedPanel,
};

/**
 * The reducer for the app store.
 * @param {Object} state  The current state of the store.
 * @param {Object} action The action to be performed.
 * @return {Object}
 */
const reducer = (state = initialState, action) => {
	switch (action.type) {
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

			// Ensure config has a content callback.
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

			if (!('title' in action.config)) {
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
		case 'TOGGLE_ACTIVITY_PANEL_VISIBILITY': {
			const nextPanel =
				state.visiblePanel === action.panel ? null : action.panel;
			try {
				if (nextPanel) {
					window.localStorage.setItem(
						'wpgraphql_ide_visible_panel',
						nextPanel
					);
				} else {
					window.localStorage.removeItem(
						'wpgraphql_ide_visible_panel'
					);
				}
			} catch {
				// localStorage unavailable
			}
			return {
				...state,
				visiblePanel: nextPanel,
			};
		}
		case 'SET_VISIBLE_PANEL': {
			try {
				window.localStorage.setItem(
					'wpgraphql_ide_visible_panel',
					action.panel
				);
			} catch {
				// localStorage unavailable
			}
			return { ...state, visiblePanel: action.panel };
		}
		default:
			return state;
	}
};

export default reducer;
