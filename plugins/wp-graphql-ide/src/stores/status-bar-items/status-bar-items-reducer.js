const initialState = {
	items: {},
};

const reducer = (state = initialState, action) => {
	switch (action.type) {
		case 'REGISTER_STATUS_BAR_ITEM': {
			if (action.name in state.items) {
				console.warn({
					message: `The "${action.name}" status bar item already exists. Name must be unique.`,
					existingItem: state.items[action.name],
					duplicateItem: action.config,
				});
				return state;
			}

			if (typeof action.config.render !== 'function') {
				console.error(
					`Config for status bar item "${action.name}" requires a render callback.`
				);
				return state;
			}

			const item = {
				priority: action.priority || 10,
				...action.config,
			};

			return {
				...state,
				items: {
					...state.items,
					[action.name]: item,
				},
			};
		}
		default:
			return state;
	}
};

export default reducer;
