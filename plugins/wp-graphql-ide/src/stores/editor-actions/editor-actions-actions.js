const actions = {
	registerEditorAction: (name, config, priority) => ({
		type: 'REGISTER_EDITOR_ACTION',
		name,
		config,
		priority,
	}),
};

export default actions;
