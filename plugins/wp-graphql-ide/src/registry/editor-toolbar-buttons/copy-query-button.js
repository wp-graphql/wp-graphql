import { Icon, copy } from '@wordpress/icons';
import { select } from '@wordpress/data';
import copyToClipboard from 'copy-to-clipboard';

export const copyQueryButton = () => {
	return {
		label: 'Copy query (Shift-Ctrl-C)',
		children: <Icon icon={copy} aria-hidden="true" />,
		onClick: () => {
			const query = select('wpgraphql-ide/app').getQuery();
			copyToClipboard(query);
		},
	};
};
