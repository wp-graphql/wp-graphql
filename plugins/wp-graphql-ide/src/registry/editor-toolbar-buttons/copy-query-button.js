import { __ } from '@wordpress/i18n';
import { select } from '@wordpress/data';
import copyToClipboard from 'copy-to-clipboard';

export const copyQueryButton = () => {
	return {
		label: __('Copy query', 'wpgraphql-ide'),
		children: __('Copy', 'wpgraphql-ide'),
		onClick: () => {
			const query = select('wpgraphql-ide/app').getQuery();
			copyToClipboard(query);
		},
	};
};
