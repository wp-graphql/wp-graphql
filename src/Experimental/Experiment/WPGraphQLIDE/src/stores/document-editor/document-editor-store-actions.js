const actions = {
	registerButton: ( name, config, priority ) => ( {
		type: 'REGISTER_BUTTON',
		name,
		config,
		priority,
	} ),
};

export default actions;
