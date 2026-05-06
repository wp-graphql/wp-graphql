/**
 * Browser-local persistence for unsaved (temp) tabs.
 *
 * Saved documents live on the server, but in-memory drafts only exist
 * in the editor — without a mirror, a refresh wipes them. This module
 * keeps them around for the next session in localStorage. localStorage
 * is intentional: drafts are personal scratch buffers, not shared
 * state, and the per-browser scope matches user expectation.
 */

import { getStorageJSON, setStorageJSON } from '../../utils/storage';
import { isTempId } from '../../utils/document-id';

const STORAGE_KEY = 'wpgraphql-ide:unsaved-tabs:v1';

function readAll() {
	const data = getStorageJSON(STORAGE_KEY, []);
	return Array.isArray(data) ? data : [];
}

function writeAll(tabs) {
	setStorageJSON(STORAGE_KEY, tabs);
}

/**
 * @return {Array<Object>} Stored unsaved tabs, oldest first.
 */
export function getUnsavedTabs() {
	return readAll().filter((t) => t && isTempId(t.id));
}

/**
 * Upsert an unsaved tab. No-op for non-temp IDs so saved documents
 * never accidentally end up in localStorage.
 *
 * @param {Object} doc Document object with at least { id, title }.
 */
export function saveUnsavedTab(doc) {
	if (!doc || !isTempId(doc.id)) {
		return;
	}
	const tabs = readAll();
	const slim = {
		id: String(doc.id),
		title: doc.title || 'Untitled',
		query: doc.query || '',
		variables: doc.variables || '',
		headers: doc.headers || '',
		documentSettings:
			doc.documentSettings && typeof doc.documentSettings === 'object'
				? doc.documentSettings
				: {},
	};
	const idx = tabs.findIndex((t) => String(t.id) === slim.id);
	if (idx >= 0) {
		tabs[idx] = slim;
	} else {
		tabs.push(slim);
	}
	writeAll(tabs);
}

/**
 * Remove a single unsaved tab (e.g. on close, delete, or promotion
 * to a saved document via saveTab).
 *
 * @param {string} id Temp tab ID.
 */
export function removeUnsavedTab(id) {
	const tabs = readAll().filter((t) => String(t.id) !== String(id));
	writeAll(tabs);
}

/**
 * Wipe all unsaved tabs. Useful for tests/debug.
 */
export function clearUnsavedTabs() {
	writeAll([]);
}
