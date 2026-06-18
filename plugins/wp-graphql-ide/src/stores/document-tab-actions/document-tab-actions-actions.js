const actions = {
	registerDocumentTabAction: (name, config, priority) => ({
		type: 'REGISTER_DOCUMENT_TAB_ACTION',
		name,
		config,
		priority,
	}),
};

export default actions;
