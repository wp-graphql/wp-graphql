const initialState = {
	bottomTabs: {},
};

const reducer = (state = initialState, action) => {
	switch (action.type) {
		case 'REGISTER_EDITOR_BOTTOM_TAB': {
			if (action.name in state.bottomTabs) {
				console.warn({
					message: `The "${action.name}" editor bottom tab already exists. Name must be unique.`,
					existingTab: state.bottomTabs[action.name],
					duplicateTab: action.config,
				});
				return state;
			}

			if (typeof action.config.content !== 'function') {
				console.error(
					`Config for editor bottom tab "${action.name}" requires a content callback.`
				);
				return state;
			}

			if (!('title' in action.config)) {
				console.error(
					`Config for editor bottom tab "${action.name}" requires a title.`
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
				bottomTabs: {
					...state.bottomTabs,
					[action.name]: tab,
				},
			};
		}
		default:
			return state;
	}
};

export default reducer;
