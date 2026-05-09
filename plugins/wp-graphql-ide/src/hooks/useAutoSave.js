import { useCallback } from 'react';
import { useDebouncedCallback } from './useDebouncedCallback';
import { deriveStableDocTitle, isAutoTitle } from '../utils/derive-doc-title';

const AUTOSAVE_DELAY_MS = 2000;

/**
 * Auto-save the active document's editor fields after 2s of typing
 * inactivity, plus the live editor-state setters keyed off each field's
 * change handler. Returns ready-to-pass `onChange` callbacks for the
 * GraphQL editor, the variables JSON editor, the headers JSON editor,
 * and the document-settings panel.
 *
 * Behavior preserved exactly from the inline implementation:
 *  - Temp drafts (id starting with `temp-`) skip the debounce since
 *    `saveDocument` is a synchronous local update for those ids.
 *  - Sticky-title persist: when the doc's title is still in the auto
 *    state and the query has a clearly-complete op name, freeze that
 *    name on first save. Mirrors WP's "slug freezes after first publish."
 *  - `cancelAutoSave` cancels any pending fire (used by the active-doc
 *    sync effect to drop stale debounced writes when switching tabs).
 *
 * @param {Object}      params
 * @param {Object|null} params.activeDocument
 * @param {Function}    params.saveDocument         - `wpgraphql-ide/document-editor` action.
 * @param {Function}    params.setQuery             - Live editor-state setter for query.
 * @param {Function}    params.setVariables         - Live editor-state setter for variables.
 * @param {Function}    params.setHeaders           - Live editor-state setter for headers.
 * @param {Function}    params.setDocSettingsValues - React state setter for doc-settings.
 *
 * @return {{
 *   scheduleAutoSave: Function,
 *   cancelAutoSave: Function,
 *   handleQueryChange: Function,
 *   handleVariablesChange: Function,
 *   handleHeadersChange: Function,
 *   handleDocumentSettingChange: Function,
 * }}
 */
export function useAutoSave({
	activeDocument,
	saveDocument,
	setQuery,
	setVariables,
	setHeaders,
	setDocSettingsValues,
}) {
	const [debouncedSave, cancelAutoSave] = useDebouncedCallback(
		(docId, payload) => {
			saveDocument(docId, payload);
		},
		AUTOSAVE_DELAY_MS
	);

	const scheduleAutoSave = useCallback(
		(field, value) => {
			if (!activeDocument) {
				return;
			}
			const payload = { [field]: value };
			if (field === 'query' && isAutoTitle(activeDocument.title)) {
				const stable = deriveStableDocTitle(value);
				if (stable) {
					payload.title = stable;
				}
			}
			if (String(activeDocument.id).startsWith('temp-')) {
				saveDocument(activeDocument.id, payload);
				return;
			}
			debouncedSave(activeDocument.id, payload);
		},
		[activeDocument, saveDocument, debouncedSave]
	);

	const handleQueryChange = useCallback(
		(value) => {
			setQuery(value);
			scheduleAutoSave('query', value);
		},
		[setQuery, scheduleAutoSave]
	);

	const handleVariablesChange = useCallback(
		(value) => {
			setVariables(value);
			scheduleAutoSave('variables', value);
		},
		[setVariables, scheduleAutoSave]
	);

	const handleHeadersChange = useCallback(
		(value) => {
			setHeaders(value);
			scheduleAutoSave('headers', value);
		},
		[setHeaders, scheduleAutoSave]
	);

	const handleDocumentSettingChange = useCallback(
		(name, value) => {
			setDocSettingsValues((prev) => {
				const next = { ...prev, [name]: value };
				scheduleAutoSave('documentSettings', next);
				return next;
			});
		},
		[setDocSettingsValues, scheduleAutoSave]
	);

	return {
		scheduleAutoSave,
		cancelAutoSave,
		handleQueryChange,
		handleVariablesChange,
		handleHeadersChange,
		handleDocumentSettingChange,
	};
}
