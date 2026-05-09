import { readDevicePreference, setPreference } from '../../api/preferences';

/**
 * The initial state of the activity bar.
 * @type {Object}
 */
const initialPanel = (() => {
	const fromDevice = readDevicePreference('visible_panel');
	if (typeof fromDevice === 'string' && fromDevice !== '') {
		return fromDevice;
	}
	// Migrate the older single-key entry one time. The key was the
	// previous storage location before everything moved into the
	// scope-aware bucket.
	try {
		if (typeof window !== 'undefined') {
			const legacy = window.localStorage.getItem(
				'wpgraphql_ide_visible_panel'
			);
			if (legacy) {
				window.localStorage.removeItem('wpgraphql_ide_visible_panel');
				setPreference('visible_panel', legacy).catch(() => {});
				return legacy;
			}
		}
	} catch {
		// localStorage unavailable
	}
	return null;
})();

const initialState = {
	activityPanels: {},
	visiblePanel: initialPanel,
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
			setPreference('visible_panel', nextPanel || '').catch(() => {});
			return {
				...state,
				visiblePanel: nextPanel,
			};
		}
		case 'SET_VISIBLE_PANEL': {
			setPreference('visible_panel', action.panel || '').catch(() => {});
			return { ...state, visiblePanel: action.panel };
		}
		default:
			return state;
	}
};

export default reducer;
