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
	toggleActivityPanelVisibility: (panel) => ({
		type: 'TOGGLE_ACTIVITY_PANEL_VISIBILITY',
		panel,
	}),
	setVisiblePanel: (panel) => ({
		type: 'SET_VISIBLE_PANEL',
		panel,
	}),
};

export default actions;
