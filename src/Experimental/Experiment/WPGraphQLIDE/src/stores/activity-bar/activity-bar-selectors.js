/**
 * Selectors for the activity bar.
 *
 * @type {Object}
 */
const selectors = {
	activityPanels: ( state ) => {
		const panels = Object.entries( state.activityPanels ).map(
			( [ name, activityPanel ] ) => ( {
				name,
				...activityPanel,
			} )
		);

		return panels.sort( ( a, b ) => a.priority - b.priority );
	},
	utilities: ( state ) => {
		const utilities = Object.entries( state.utilities ).map(
			( [ name, utility ] ) => ( {
				name,
				...utility,
			} )
		);

		return utilities.sort( ( a, b ) => a.priority - b.priority );
	},
};

export default selectors;
