import { Icon, code } from '@wordpress/icons';
import { select, dispatch } from '@wordpress/data';

export const prettifyButton = () => {
	return {
		label: 'Prettify query (Shift-Ctrl-P)',
		children: <Icon icon={code} aria-hidden="true" />,
		onClick: () => {
			const query = select('wpgraphql-ide/app').getQuery();
			dispatch('wpgraphql-ide/app').prettifyQuery(query);
		},
	};
};
