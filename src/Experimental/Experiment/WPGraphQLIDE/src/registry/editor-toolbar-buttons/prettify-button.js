import { PrettifyIcon } from '@graphiql/react';
import { useDispatch, useSelect } from '@wordpress/data';

export const prettifyButton = () => {
	const query = useSelect( ( select ) =>
		select( 'wpgraphql-ide/app' ).getQuery()
	);
	const { prettifyQuery } = useDispatch( 'wpgraphql-ide/app' );

	return {
		label: 'Prettify query (Shift-Ctrl-P)',
		children: (
			<PrettifyIcon
				className="graphiql-toolbar-icon"
				aria-hidden="true"
			/>
		),
		onClick: () => {
			prettifyQuery( query );
		},
	};
};
