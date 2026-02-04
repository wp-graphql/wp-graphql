import { useState } from '@wordpress/element';
import copy from 'copy-to-clipboard';

/**
 * Custom hook for copying text to the clipboard.
 *
 * This hook uses the copy-to-clipboard package to handle the copying process.
 * It manages a state to indicate whether the text has been successfully copied,
 * allowing for user feedback. After copying, it dispatches a WordPress admin notice
 * to inform the user.
 *
 * @return {Function[]} An array where the first element is the copyToClipboard function and
 * the second element is a boolean state indicating whether the text has been copied.
 */
export const useCopyToClipboard = () => {
	const [ isCopied, setIsCopied ] = useState( false );

	/**
	 * Copies the provided text to the clipboard and shows a WordPress admin notice.
	 *
	 * @param {string} text The text to be copied to the clipboard.
	 */
	const copyToClipboard = async ( text ) => {
		const wasCopied = copy( text );
		await setIsCopied( wasCopied );
		setTimeout( () => setIsCopied( false ), 2500 );
	};

	return [ copyToClipboard, isCopied ];
};
