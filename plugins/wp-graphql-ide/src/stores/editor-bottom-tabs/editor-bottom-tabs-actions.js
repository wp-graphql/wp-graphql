const actions = {
	registerEditorBottomTab: (name, config, priority) => ({
		type: 'REGISTER_EDITOR_BOTTOM_TAB',
		name,
		config,
		priority,
	}),
};

export default actions;
