const actions = {
	registerResponseViewMode: (value, config, priority) => ({
		type: 'REGISTER_RESPONSE_VIEW_MODE',
		value,
		config,
		priority,
	}),
};

export default actions;
