import React from 'react';
import { JSONEditor } from '../editors/JSONEditor';

/**
 * Built-in editor-bottom tab for GraphQL operation variables. Receives
 * the active doc's variables string and change handler from EditorPane,
 * plus `variableToType` derived from the current query for autocompletion.
 *
 * @param {Object}   props
 * @param {string}   props.variables
 * @param {Function} props.onVariablesChange
 * @param {Object}   props.variableToType
 * @param {Object}   [props.editorKeyBindings] - Shared CM keymap ref (Cmd+Enter etc.).
 *
 * @return {JSX.Element}
 */
export function VariablesEditorTab({
	variables,
	onVariablesChange,
	variableToType,
	editorKeyBindings,
}) {
	return (
		<JSONEditor
			value={variables}
			onChange={onVariablesChange}
			placeholder="Variables (JSON)"
			variableToType={variableToType}
			extraKeys={editorKeyBindings?.current}
		/>
	);
}
