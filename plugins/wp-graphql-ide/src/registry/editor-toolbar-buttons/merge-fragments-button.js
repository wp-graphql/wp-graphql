import { select, dispatch } from '@wordpress/data';

export const mergeFragmentsButton = () => {
	return {
		label: 'Merge fragments into query (Shift-Ctrl-M)',
		children: 'Merge',
		onClick: () => {
			const query = select('wpgraphql-ide/app').getQuery();
			dispatch('wpgraphql-ide/app').mergeQuery(query);
		},
	};
};
