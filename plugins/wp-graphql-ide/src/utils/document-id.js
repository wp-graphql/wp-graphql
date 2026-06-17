/**
 * Single source of truth for the temp-document ID predicate.
 *
 * Newly created documents that haven't been saved to the server yet
 * carry a `temp-…` prefix on their client-side ID so the editor can
 * tell them apart from persisted docs (numeric/uuid IDs from the CPT).
 * The check is one line, but it gets called from three different
 * layers (the public selector, the action creators, and the unsaved-
 * tabs localStorage shim), so collapsing it here means the prefix is
 * defined exactly once.
 *
 * @since x-release-please-version
 *
 * @param {string|number} id Document ID.
 * @return {boolean} True if the ID is a client-side temp ID.
 */
export function isTempId(id) {
	return String(id).startsWith('temp-');
}
