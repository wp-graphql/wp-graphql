/**
 * Browser-local persistence for unsaved (temp) tabs.
 *
 * Saved documents live on the server, but in-memory drafts only exist
 * in the editor — without a mirror, a refresh wipes them. This module
 * keeps them around for the next session in localStorage. localStorage
 * is intentional: drafts are personal scratch buffers, not shared
 * state, and the per-browser scope matches user expectation.
 */

const STORAGE_KEY = 'wpgraphql-ide:unsaved-tabs:v1';

function safeParse(raw) {
	try {
		const parsed = JSON.parse(raw);
		return Array.isArray(parsed) ? parsed : [];
	} catch (e) {
		return [];
	}
}

function readAll() {
	try {
		const raw = window.localStorage.getItem(STORAGE_KEY);
		return raw ? safeParse(raw) : [];
	} catch (e) {
		return [];
	}
}

function writeAll(tabs) {
	try {
		window.localStorage.setItem(STORAGE_KEY, JSON.stringify(tabs));
	} catch (e) {
		// Quota exceeded or storage disabled — drafts won't survive
		// the next refresh, but the editor still works.
	}
}

function isTempIdLocal(id) {
	return String(id).startsWith('temp-');
}

/**
 * @return {Array<Object>} Stored unsaved tabs, oldest first.
 */
export function getUnsavedTabs() {
	return readAll().filter((t) => t && isTempIdLocal(t.id));
}

/**
 * Upsert an unsaved tab. No-op for non-temp IDs so saved documents
 * never accidentally end up in localStorage.
 *
 * @param {Object} doc Document object with at least { id, title }.
 */
export function saveUnsavedTab(doc) {
	if (!doc || !isTempIdLocal(doc.id)) {
		return;
	}
	const tabs = readAll();
	const slim = {
		id: String(doc.id),
		title: doc.title || 'Untitled',
		query: doc.query || '',
		variables: doc.variables || '',
		headers: doc.headers || '',
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
