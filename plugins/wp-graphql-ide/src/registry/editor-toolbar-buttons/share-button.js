import { useCopyToClipboard } from '../../hooks/useCopyToClipboard';
import { external, Icon } from '@wordpress/icons';
import { useSelect } from '@wordpress/data';
import LZString from 'lz-string';

export const shareButton = () => {
	const [ copyToClipboard ] = useCopyToClipboard();
	const { dedicatedIdeBaseUrl } = window.WPGRAPHQL_IDE_DATA;
	const query = useSelect( ( select ) =>
		select( 'wpgraphql-ide/app' ).getQuery()
	);

	const generateShareLink = async () => {
		const hashedQueryParamObject = getHashedQueryParams( { query } );
		const fullUrl = `${ dedicatedIdeBaseUrl }&wpgraphql_ide=${ hashedQueryParamObject }`;
		await copyToClipboard( fullUrl );

		// TODO: notify user that a shareable link is copied to clipboard
	};

	return {
		label: 'Share current document',
		// component: ShareDocumentButton
		children: (
			<Icon
				icon={ external }
				style={ {
					fill: 'hsla(var(--color-neutral), var(--alpha-tertiary))',
				} }
			/>
		),
		onClick: async () => {
			await generateShareLink();
		},
	};
};

/**
 * Compresses and encodes a query parameter object for use in a shareable URL.
 *
 * @param {Object} obj The object containing query parameters to be compressed and encoded.
 * @return {string} A compressed and encoded string representing the query parameters.
 */
export function getHashedQueryParams( obj ) {
	if ( typeof obj !== 'object' || obj === null ) {
		console.error( 'Input must be a non-null object' );
		return '';
	}
	try {
		const queryParamString = JSON.stringify( obj );
		return LZString.compressToEncodedURIComponent( queryParamString );
	} catch ( error ) {
		console.error( 'Failed to compress query parameter object:', error );
		return '';
	}
}
