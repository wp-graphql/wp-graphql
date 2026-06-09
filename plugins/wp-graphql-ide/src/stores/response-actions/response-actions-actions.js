const actions = {
	registerResponseAction: (name, config, priority) => ({
		type: 'REGISTER_RESPONSE_ACTION',
		name,
		config,
		priority,
	}),
};

export default actions;
