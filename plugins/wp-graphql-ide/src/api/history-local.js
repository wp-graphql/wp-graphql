/**
 * localStorage-backed execution history for anonymous visitors on the
 * public `/graphql` endpoint. Mirrors the function shape of
 * `./history.js` (server-backed, logged-in users) so the router in
 * that file can swap backends without touching callsites.
 *
 * Storage model: a single per-browser bucket capped at 50 entries,
 * newest first — same model GraphiQL itself uses. No per-user scoping
 * key (anonymous visitors have no user id; multiple anonymous users
 * sharing a browser will see each other's history, same as 4.x and
 * vanilla GraphiQL).
 *
 * @since x-release-please-version
 */

import { getStorageJSON, setStorageJSON } from '../utils/storage';

const STORAGE_KEY = 'wpgraphql-ide:local-history:v1';
const MAX_ENTRIES = 50;

function readAll() {
	const data = getStorageJSON(STORAGE_KEY, []);
	return Array.isArray(data) ? data : [];
}

function writeAll(entries) {
	setStorageJSON(STORAGE_KEY, entries);
}

/**
 * Mint a stable, sortable id for a new local entry. Timestamp-based so
 * entries naturally sort newest-first and ids never collide within a
 * single millisecond unless the caller is firing >1000 entries/sec
 * (won't happen — every entry corresponds to a manual query run).
 *
 * @return {string}
 */
function mintId() {
	const ts = Date.now();
	const suffix = Math.random().toString(36).slice(2, 6);
	return `local-${ts}-${suffix}`;
}

/**
 * Fetch the in-browser execution history. Newest first.
 *
 * @return {Promise<Object[]>}
 */
export async function getLocalHistory() {
	return readAll();
}

/**
 * Append a new local entry, pruning to the 50-entry cap. Returns the
 * adapted entry in the same shape the server backend returns so the
 * store reducer doesn't need to branch.
 *
 * @param {Object} entry Snake_case shape: `{ query, variables, headers,
 *                       duration_ms, status, document_id, is_authenticated,
 *                       http_method }`. `oldestId` is accepted and ignored
 *                       for parity with the server backend; pruning here is
 *                       implicit since we own the bucket.
 * @return {Promise<Object>}
 */
export async function createLocalHistoryEntry(entry) {
	const adapted = {
		id: mintId(),
		globalId: null,
		timestamp: Math.floor(Date.now() / 1000),
		query: entry.query ?? '',
		variables: entry.variables ?? '',
		headers: entry.headers ?? '',
		duration_ms: entry.duration_ms ?? 0,
		status: entry.status ?? '',
		document_id:
			Number(entry.document_id) > 0 ? Number(entry.document_id) : 0,
		is_authenticated: entry.is_authenticated ?? false,
		http_method: entry.http_method ?? 'POST',
	};

	const existing = readAll();
	// Newest first: prepend, then truncate the tail past the cap.
	const next = [adapted, ...existing].slice(0, MAX_ENTRIES);
	writeAll(next);

	return adapted;
}

/**
 * Delete a single local entry by id.
 *
 * @param {string} id
 * @return {Promise<{deletedId: string}>}
 */
export async function deleteLocalHistoryEntry(id) {
	const next = readAll().filter((e) => String(e.id) !== String(id));
	writeAll(next);
	return { deletedId: id };
}

/**
 * Wipe all local history. The router in `./history.js` calls this
 * with the same `ids` array the server backend needs for its per-entry
 * delete fan-out; we ignore it because we own the bucket and can drop
 * it wholesale.
 *
 * @return {Promise<void>}
 */
export async function clearLocalHistory() {
	writeAll([]);
}
