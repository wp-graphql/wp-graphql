import { select, dispatch } from '@wordpress/data';
import hooks from '../../wordpress-hooks';

export const mergeFragmentsButton = () => {
	return {
		label: 'Merge fragments into query (Shift-Ctrl-M)',
		children: 'Merge',
		onClick: () => {
			const query = select('wpgraphql-ide/app').getQuery();
			dispatch('wpgraphql-ide/app').mergeQuery(query);
			hooks.doAction('wpgraphql-ide.notice', 'Fragments merged');
		},
	};
};
