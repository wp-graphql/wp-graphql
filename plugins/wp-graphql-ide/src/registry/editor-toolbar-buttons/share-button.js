import { external, Icon } from '@wordpress/icons';
import { select } from '@wordpress/data';
import LZString from 'lz-string';
import copy from 'copy-to-clipboard';

export const shareButton = () => {
	return {
		label: 'Share current document',
		// component: ShareDocumentButton
		children: (
			<Icon
				icon={external}
				style={{
					fill: 'hsla(var(--color-neutral), var(--alpha-tertiary))',
				}}
			/>
		),
		onClick: () => {
			const { dedicatedIdeBaseUrl } = window.WPGRAPHQL_IDE_DATA;
			const query = select('wpgraphql-ide/app').getQuery();
			const hashedQueryParamObject = getHashedQueryParams({ query });
			const fullUrl = `${dedicatedIdeBaseUrl}&wpgraphql_ide=${hashedQueryParamObject}`;
			copy(fullUrl);

			// TODO: notify user that a shareable link is copied to clipboard
		},
	};
};

/**
 * Compresses and encodes a query parameter object for use in a shareable URL.
 *
 * @param {Object} obj The object containing query parameters to be compressed and encoded.
 * @return {string} A compressed and encoded string representing the query parameters.
 */
export function getHashedQueryParams(obj) {
	if (typeof obj !== 'object' || obj === null) {
		// eslint-disable-next-line no-console
		console.error('Input must be a non-null object');
		return '';
	}
	try {
		const queryParamString = JSON.stringify(obj);
		return LZString.compressToEncodedURIComponent(queryParamString);
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error('Failed to compress query parameter object:', error);
		return '';
	}
}
