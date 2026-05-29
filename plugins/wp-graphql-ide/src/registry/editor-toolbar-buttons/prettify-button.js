import { __ } from '@wordpress/i18n';
import { select, dispatch } from '@wordpress/data';
import { PRETTIFY_LABEL } from '../../utils/shortcut-labels';

export const prettifyButton = () => {
	return {
		label: __('Prettify query', 'wpgraphql-ide'),
		children: __('Prettify', 'wpgraphql-ide'),
		shortcut: PRETTIFY_LABEL,
		mutates: true,
		onClick: () => {
			const query = select('wpgraphql-ide/app').getQuery();
			dispatch('wpgraphql-ide/app').prettifyQuery(query);
		},
	};
};
