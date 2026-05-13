import { __ } from '@wordpress/i18n';
import { select, dispatch } from '@wordpress/data';

export const mergeFragmentsButton = () => {
	return {
		label: __('Merge fragments into query', 'wpgraphql-ide'),
		children: __('Merge', 'wpgraphql-ide'),
		mutates: true,
		onClick: () => {
			const query = select('wpgraphql-ide/app').getQuery();
			dispatch('wpgraphql-ide/app').mergeQuery(query);
		},
	};
};
