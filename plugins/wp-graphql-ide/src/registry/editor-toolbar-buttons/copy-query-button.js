import { select } from '@wordpress/data';
import copyToClipboard from 'copy-to-clipboard';
import hooks from '../../wordpress-hooks';

export const copyQueryButton = () => {
	return {
		label: 'Copy query (Shift-Ctrl-C)',
		children: 'Copy',
		onClick: () => {
			const query = select('wpgraphql-ide/app').getQuery();
			copyToClipboard(query);
			hooks.doAction('wpgraphql-ide.notice', 'Query copied to clipboard');
		},
	};
};
