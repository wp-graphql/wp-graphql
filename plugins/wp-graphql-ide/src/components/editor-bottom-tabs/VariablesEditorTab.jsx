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
 *
 * @return {JSX.Element}
 */
export function VariablesEditorTab({
	variables,
	onVariablesChange,
	variableToType,
}) {
	return (
		<JSONEditor
			value={variables}
			onChange={onVariablesChange}
			placeholder="Variables (JSON)"
			variableToType={variableToType}
		/>
	);
}
