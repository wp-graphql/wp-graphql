const selectors = {
	buttons: ( state ) => {
		const buttons = Object.entries( state.buttons ).map(
			( [ name, button ] ) => ( {
				name,
				...button,
			} )
		);

		return buttons.sort( ( a, b ) => a.priority - b.priority );
	},
};

export default selectors;
