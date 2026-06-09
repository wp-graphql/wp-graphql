import React from 'react';
import { ErrorsPanel } from '../ErrorsPanel';

/**
 * Response-extension tab wrapper for ErrorsPanel. Receives the parsed
 * response.errors array as `data` from the response-extensions registry's
 * synthetic `errors` slot.
 *
 * @param {Object} props
 * @param {Array}  props.data Parsed errors array (or empty array).
 *
 * @return {JSX.Element}
 */
export function ErrorsExtensionTab({ data }) {
	return <ErrorsPanel errors={Array.isArray(data) ? data : []} />;
}
