import { CopyIcon } from '@graphiql/react';
import { select } from '@wordpress/data';

import { useCopyToClipboard } from '../../hooks/useCopyToClipboard';

export const copyQueryButton = () => {
	const [ copyToClipboard ] = useCopyToClipboard();

	return {
		label: 'Copy query (Shift-Ctrl-C)',
		children: (
			<CopyIcon className="graphiql-toolbar-icon" aria-hidden="true" />
		),
		onClick: async () => {
			const query = select( 'wpgraphql-ide/app' ).getQuery();
			await copyToClipboard( query );
		},
	};
};
