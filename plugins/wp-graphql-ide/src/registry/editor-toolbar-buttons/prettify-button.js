import { select, dispatch } from '@wordpress/data';

export const prettifyButton = () => {
	return {
		label: 'Prettify query (Shift-Ctrl-P)',
		children: 'Prettify',
		onClick: () => {
			const query = select('wpgraphql-ide/app').getQuery();
			dispatch('wpgraphql-ide/app').prettifyQuery(query);
		},
	};
};
