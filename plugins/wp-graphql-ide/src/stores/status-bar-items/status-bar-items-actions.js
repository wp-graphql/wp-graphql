const actions = {
	registerStatusBarItem: (name, config, priority) => ({
		type: 'REGISTER_STATUS_BAR_ITEM',
		name,
		config,
		priority,
	}),
};

export default actions;
