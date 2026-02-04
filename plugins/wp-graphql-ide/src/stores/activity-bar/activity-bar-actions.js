/**
 * The actions for the activity bar.
 *
 * @type {Object}
 */
const actions = {
	registerPanel: (name, config, priority) => ({
		type: 'REGISTER_PANEL',
		name,
		config,
		priority,
	}),
	registerUtility: (name, config, priority) => ({
		type: 'REGISTER_UTILITY',
		name,
		config,
		priority,
	}),
	toggleActivityPanelVisibility: (panel) => ({
		type: 'TOGGLE_ACTIVITY_PANEL_VISIBILITY',
		panel,
	}),
};

export default actions;
