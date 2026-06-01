/**
 * localStorage-backed execution history. The sole backend for the
 * IDE's History panel.
 *
 * Storage model: one bucket per (WordPress user, IDE context), capped
 * at 50 entries, newest first. Scoping by user + context mirrors the
 * device-preferences pattern in `./preferences.js` so admins sharing a
 * browser don't see each other's history, and the admin IDE bucket
 * stays distinct from the public-endpoint bucket on the same site.
 * Anonymous public-endpoint visitors share a single `user-0:ctx-endpoint`
 * bucket (same model GraphiQL itself uses).
 *
 * Key format: `wpgraphql-ide:local-history:v1:user-{userId}:ctx-{context}`.
 * Computed at call time, not module load, so a late `WPGRAPHQL_IDE_DATA`
 * injection or sign-in mid-session is picked up without a reload.
 *
 * @since x-release-please-version
 */

import { getStorageJSON, setStorageJSON } from '../utils/storage';

const STORAGE_VERSION = 'v1';
const MAX_ENTRIES = 50;

/**
 * Compute the per-visitor storage key. Reads `WPGRAPHQL_IDE_DATA` each
 * call so sign-in/sign-out mid-session lands in the right bucket.
 *
 * @return {string}
 */
function storageKey() {
	const data =
		typeof window !== 'undefined' ? window.WPGRAPHQL_IDE_DATA : null;
	const id = (data && Number(data?.context?.currentUserId)) || 0;
	const ctx = data?.endpointMode ? 'endpoint' : 'admin';
	return `wpgraphql-ide:local-history:${STORAGE_VERSION}:user-${id}:ctx-${ctx}`;
}

function readAll() {
	const data = getStorageJSON(storageKey(), []);
	return Array.isArray(data) ? data : [];
}

function writeAll(entries) {
	setStorageJSON(storageKey(), entries);
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
 * Append a new local entry, pruning to the 50-entry cap.
 *
 * @param {Object} entry Snake_case shape: `{ query, variables, headers,
 *                       duration_ms, status, document_id, is_authenticated,
 *                       http_method }`. `oldestId` is accepted and ignored
 *                       for signature parity with older callers; pruning here
 *                       is implicit since we own the bucket.
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
 * Wipe all local history for the current bucket. Accepts an `ids`
 * argument for signature parity with older callers and ignores it —
 * we own the bucket and can drop it wholesale.
 *
 * @return {Promise<void>}
 */
export async function clearLocalHistory() {
	writeAll([]);
}
