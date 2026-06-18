const initialState = {
	actions: {},
};

const reducer = (state = initialState, action) => {
	switch (action.type) {
		case 'REGISTER_RESPONSE_ACTION': {
			if (action.name in state.actions) {
				console.warn({
					message: `The "${action.name}" response action already exists. Name must be unique.`,
					existingAction: state.actions[action.name],
					duplicateAction: action.config,
				});
				return state;
			}

			if (typeof action.config.onClick !== 'function') {
				console.error(
					`Config for response action "${action.name}" requires an onClick callback.`
				);
				return state;
			}

			if (!('label' in action.config)) {
				console.error(
					`Config for response action "${action.name}" requires a label.`
				);
				return state;
			}

			const item = {
				priority: action.priority || 10,
				...action.config,
			};

			return {
				...state,
				actions: {
					...state.actions,
					[action.name]: item,
				},
			};
		}
		default:
			return state;
	}
};

export default reducer;
