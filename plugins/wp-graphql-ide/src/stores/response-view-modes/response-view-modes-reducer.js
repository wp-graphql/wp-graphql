const initialState = {
	viewModes: {},
};

const reducer = (state = initialState, action) => {
	switch (action.type) {
		case 'REGISTER_RESPONSE_VIEW_MODE': {
			if (action.value in state.viewModes) {
				console.warn({
					message: `The "${action.value}" response view mode already exists. Value must be unique.`,
					existingMode: state.viewModes[action.value],
					duplicateMode: action.config,
				});
				return state;
			}

			if (typeof action.config.render !== 'function') {
				console.error(
					`Config for response view mode "${action.value}" requires a render callback.`
				);
				return state;
			}

			if (!('label' in action.config)) {
				console.error(
					`Config for response view mode "${action.value}" requires a label.`
				);
				return state;
			}

			const mode = {
				priority: action.priority || 10,
				...action.config,
			};

			return {
				...state,
				viewModes: {
					...state.viewModes,
					[action.value]: mode,
				},
			};
		}
		default:
			return state;
	}
};

export default reducer;
