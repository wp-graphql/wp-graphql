import React from 'react';
import { JSONEditor } from '../editors/JSONEditor';

/**
 * Built-in editor-bottom tab for HTTP request headers. Receives the
 * active doc's headers string and change handler from EditorPane.
 *
 * @param {Object}   props
 * @param {string}   props.headers
 * @param {Function} props.onHeadersChange
 *
 * @return {JSX.Element}
 */
export function HeadersEditorTab({ headers, onHeadersChange }) {
	return (
		<JSONEditor
			value={headers}
			onChange={onHeadersChange}
			placeholder="Headers (JSON)"
		/>
	);
}
