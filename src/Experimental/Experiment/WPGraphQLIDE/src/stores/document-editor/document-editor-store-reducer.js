const initialState = {
	buttons: {},
};

const reducer = ( state = initialState, action ) => {
	switch ( action.type ) {
		case 'REGISTER_BUTTON':
			// Ensure button name is unique
			if ( action.name in state.buttons ) {
				console.warn( {
					message: `The "${ action.name }" button already exists. Name must be unique.`,
					existingButton: state.buttons[ action.name ],
					duplicateButton: action.config,
				} );
				return state;
			}

			const button = {
				config: action.config,
				priority: action.priority || 10, // default priority to 10 if not provided
			};

			return {
				...state,
				buttons: {
					...state.buttons,
					[ action.name ]: button,
				},
			};
		default:
			return state;
	}
};

export default reducer;
