const initialState = {
	extensionTabs: {},
};

const reducer = (state = initialState, action) => {
	switch (action.type) {
		case 'REGISTER_EXTENSION_TAB': {
			if (action.name in state.extensionTabs) {
				console.warn({
					message: `The "${action.name}" response extension tab already exists. Name must be unique.`,
					existingTab: state.extensionTabs[action.name],
					duplicateTab: action.config,
				});
				return state;
			}

			if (typeof action.config.content !== 'function') {
				console.error(
					`Config for response extension tab "${action.name}" requires a content callback.`
				);
				return state;
			}

			if (!('title' in action.config)) {
				console.error(
					`Config for response extension tab "${action.name}" requires a title.`
				);
				return state;
			}

			const tab = {
				title: action.config.title,
				priority: action.priority || 10,
				...action.config,
			};

			return {
				...state,
				extensionTabs: {
					...state.extensionTabs,
					[action.name]: tab,
				},
			};
		}
		default:
			return state;
	}
};

export default reducer;
