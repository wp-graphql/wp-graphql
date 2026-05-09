import { useCallback } from 'react';

/**
 * Derive whether a document has unsaved changes vs. its last
 * server-saved snapshot. Returns a per-doc predicate plus the
 * pre-computed result for the active doc.
 *
 * Workspace tabs (Settings, etc.) carry their own `dirty` flag since
 * they don't have query/variables/headers. Temp drafts are dirty when
 * they hold any non-whitespace content (an empty New Tab isn't worth a
 * "save before closing?" prompt). For the active doc, compare against
 * the live editor state since pending edits haven't reached the store
 * yet between keystroke and autosave.
 *
 * `editorSyncedDocId` exists to defeat a one-frame flicker: live
 * `query`/`variables`/`headers` lag the active doc by one render after
 * a tab switch. If we trust them on that frame, a saved tab transiently
 * appears dirty during the swap and flickers the dot + italic in/out,
 * which reflows the tab strip. Falling back to `doc.*` until the live
 * state catches up keeps the dirty flag stable across the swap.
 *
 * @param {Object}      params
 * @param {Object|null} params.activeDocument
 * @param {string|null} params.editorSyncedDocId - Id of the doc the
 *                                               live editor state
 *                                               currently mirrors.
 * @param {string}      params.query
 * @param {string}      params.variables
 * @param {string}      params.headers
 * @param {Object}      params.docSettingsValues
 *
 * @return {{ isDocDirty: (doc: Object|null) => boolean, activeDocDirty: boolean }}
 */
export function useDocumentDirty({
	activeDocument,
	editorSyncedDocId,
	query,
	variables,
	headers,
	docSettingsValues,
}) {
	const isDocDirty = useCallback(
		(doc) => {
			if (!doc) {
				return false;
			}
			if (doc.tabType) {
				return !!doc.dirty;
			}
			const isActive = String(doc.id) === String(activeDocument?.id);
			const liveStateMatches =
				isActive && String(editorSyncedDocId) === String(doc.id);
			const currentQuery = liveStateMatches ? query : doc.query || '';
			const currentVars = liveStateMatches
				? variables
				: doc.variables || '';
			const currentHeaders = liveStateMatches
				? headers
				: doc.headers || '';
			// Temp drafts autopersist to localStorage on every keystroke,
			// so they can't be lost on close — never dirty.
			if (String(doc.id).startsWith('temp-')) {
				return false;
			}
			if (
				currentQuery !== (doc.lastSavedQuery || '') ||
				currentVars !== (doc.lastSavedVariables || '') ||
				currentHeaders !== (doc.lastSavedHeaders || '')
			) {
				return true;
			}
			if (liveStateMatches) {
				const savedSettings =
					(doc.documentSettings &&
						typeof doc.documentSettings === 'object' &&
						doc.documentSettings) ||
					{};
				try {
					return (
						JSON.stringify(docSettingsValues) !==
						JSON.stringify(savedSettings)
					);
				} catch {
					return false;
				}
			}
			return false;
		},
		[
			activeDocument?.id,
			editorSyncedDocId,
			query,
			variables,
			headers,
			docSettingsValues,
		]
	);

	const activeDocDirty = isDocDirty(activeDocument);

	return { isDocDirty, activeDocDirty };
}
