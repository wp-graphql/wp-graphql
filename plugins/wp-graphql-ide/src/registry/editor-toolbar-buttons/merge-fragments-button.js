import { select, dispatch } from '@wordpress/data';

export const mergeFragmentsButton = () => {
	return {
		label: 'Merge fragments into query (Shift-Ctrl-M)',
		children: 'Merge',
		mutates: true,
		onClick: () => {
			const query = select('wpgraphql-ide/app').getQuery();
			dispatch('wpgraphql-ide/app').mergeQuery(query);
		},
	};
};
