import React from 'react';
import { JSONEditor } from '../editors/JSONEditor';

/**
 * Built-in editor-bottom tab for HTTP request headers. Receives the
 * active doc's headers string and change handler from EditorPane.
 *
 * @param {Object}   props
 * @param {string}   props.headers
 * @param {Function} props.onHeadersChange
 * @param {Object}   [props.editorKeyBindings] - Shared CM keymap ref (Cmd+Enter etc.).
 *
 * @return {JSX.Element}
 */
export function HeadersEditorTab({
	headers,
	onHeadersChange,
	editorKeyBindings,
}) {
	return (
		<JSONEditor
			value={headers}
			onChange={onHeadersChange}
			placeholder="Headers (JSON)"
			extraKeys={editorKeyBindings?.current}
		/>
	);
}
