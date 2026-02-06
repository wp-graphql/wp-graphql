import { CopyIcon } from '@graphiql/react';
import { select } from '@wordpress/data';
import copy from 'copy-to-clipboard';

export const copyQueryButton = () => {
	return {
		label: 'Copy query (Shift-Ctrl-C)',
		children: (
			<CopyIcon className="graphiql-toolbar-icon" aria-hidden="true" />
		),
		onClick: () => {
			const query = select('wpgraphql-ide/app').getQuery();
			copy(query);
		},
	};
};
