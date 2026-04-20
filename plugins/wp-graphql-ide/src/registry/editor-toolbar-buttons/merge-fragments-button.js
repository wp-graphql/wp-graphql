import { Icon, shortcode } from '@wordpress/icons';
import { select, dispatch } from '@wordpress/data';

export const mergeFragmentsButton = () => {
	return {
		label: 'Merge fragments into query (Shift-Ctrl-M)',
		children: <Icon icon={shortcode} aria-hidden="true" />,
		onClick: () => {
			const query = select('wpgraphql-ide/app').getQuery();
			dispatch('wpgraphql-ide/app').mergeQuery(query);
		},
	};
};
