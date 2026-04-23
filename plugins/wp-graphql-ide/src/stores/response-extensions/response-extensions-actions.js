const actions = {
	registerExtensionTab: (name, config, priority) => ({
		type: 'REGISTER_EXTENSION_TAB',
		name,
		config,
		priority,
	}),
};

export default actions;
