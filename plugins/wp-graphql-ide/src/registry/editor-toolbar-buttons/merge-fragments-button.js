import { MergeIcon } from '@graphiql/react';
import { useSelect, useDispatch } from '@wordpress/data';

export const mergeFragmentsButton = () => {
	const query = useSelect( ( select ) =>
		select( 'wpgraphql-ide/app' ).getQuery()
	);
	const { mergeQuery } = useDispatch( 'wpgraphql-ide/app' );

	return {
		label: 'Merge fragments into query (Shift-Ctrl-M)',
		children: (
			<MergeIcon className="graphiql-toolbar-icon" aria-hidden="true" />
		),
		onClick: () => {
			mergeQuery( query );
		},
	};
};
