/**
 * One-shot migration of 4.x GraphiQL query history into the 5.0
 * history backend.
 *
 * 4.x stored execution history under the GraphiQL default
 * `graphiql:queries` localStorage key (an array of `{ query,
 * variables, headers, operationName, favorite, label }` entries).
 * 5.0 persists history through `createHistoryEntry` in `./history.js`,
 * which writes to the per-(user, context) localStorage bucket in
 * `./history-local.js`.
 *
 * Migration semantics:
 *   - One-shot. Records a flag so repeat boots are no-ops.
 *   - Reads the legacy key, hands each entry to `createHistoryEntry`,
 *     then clears the legacy key.
 *   - Marks itself complete *regardless of per-entry success* so a
 *     transient hiccup doesn't cause repeat boots to duplicate
 *     the entries that did succeed. Worst-case impact of a failure is
 *     losing some legacy history — recoverable by re-running queries.
 *   - Caps at the same 50-entry limit the runtime enforces; older
 *     entries beyond the cap are silently dropped.
 *
 * @since x-release-please-version
 */

import { createHistoryEntry } from './history';

const LEGACY_HISTORY_KEY = 'graphiql:queries';
const MIGRATION_FLAG_KEY = 'wpgraphql-ide:graphiql-history-migrated:v1';
const MAX_ENTRIES = 50;

function hasLocalStorage() {
	return typeof window !== 'undefined' && !!window.localStorage;
}

function markComplete() {
	try {
		window.localStorage.setItem(MIGRATION_FLAG_KEY, '1');
	} catch {
		// Out of quota or storage disabled — nothing we can do.
	}
}

function removeLegacyKey() {
	try {
		window.localStorage.removeItem(LEGACY_HISTORY_KEY);
	} catch {
		// localStorage unavailable.
	}
}

/**
 * Coerce a legacy GraphiQL entry into the snake_case shape
 * `createHistoryEntry` expects. Returns `null` for entries with no
 * usable query — there's nothing meaningful to restore from those.
 *
 * @param {Object} entry
 * @return {Object|null}
 */
function normalizeEntry(entry) {
	if (!entry || typeof entry !== 'object') {
		return null;
	}
	const query = typeof entry.query === 'string' ? entry.query : '';
	if (query.trim() === '') {
		return null;
	}
	return {
		query,
		variables: typeof entry.variables === 'string' ? entry.variables : '',
		headers: typeof entry.headers === 'string' ? entry.headers : '',
		// 4.x didn't track these — fill in safe defaults. `is_authenticated`
		// is deliberately omitted so the local backend's default (false)
		// applies; a sign-in mid-session is recorded on subsequent runs.
		duration_ms: 0,
		status: '',
		document_id: 0,
		http_method: 'POST',
	};
}

/**
 * Run the history migration if it hasn't already run.
 *
 * @return {Promise<{ migrated: boolean, attempted?: number, succeeded?: number, skipped?: 'flag' | 'no-storage' | 'no-legacy-key' | 'empty' | 'parse-error' }>}
 */
export async function migrateLegacyHistory() {
	if (!hasLocalStorage()) {
		return { migrated: false, skipped: 'no-storage' };
	}

	try {
		if (window.localStorage.getItem(MIGRATION_FLAG_KEY)) {
			return { migrated: false, skipped: 'flag' };
		}

		const raw = window.localStorage.getItem(LEGACY_HISTORY_KEY);
		if (!raw) {
			markComplete();
			return { migrated: false, skipped: 'no-legacy-key' };
		}

		let parsed;
		try {
			parsed = JSON.parse(raw);
		} catch {
			removeLegacyKey();
			markComplete();
			return { migrated: false, skipped: 'parse-error' };
		}

		const entries = Array.isArray(parsed) ? parsed : [];
		const normalized = entries
			.map(normalizeEntry)
			.filter((e) => e !== null)
			.slice(0, MAX_ENTRIES);

		if (normalized.length === 0) {
			removeLegacyKey();
			markComplete();
			return { migrated: false, skipped: 'empty' };
		}

		// Post sequentially rather than in parallel so we don't hammer
		// the server with 50 simultaneous mutations on first boot, and
		// so a queue stall on one entry doesn't break the whole batch.
		// Per-entry failures are swallowed — we mark complete regardless
		// to prevent duplicate entries on retry. Failure surfaces only
		// in `succeeded` so callers can log.
		let succeeded = 0;
		for (const entry of normalized) {
			try {
				// eslint-disable-next-line no-await-in-loop
				await createHistoryEntry(entry);
				succeeded += 1;
			} catch {
				// One entry failed; keep going so a single bad query
				// doesn't strand the rest of the user's history.
			}
		}

		removeLegacyKey();
		markComplete();

		return {
			migrated: succeeded > 0,
			attempted: normalized.length,
			succeeded,
		};
	} catch (error) {
		// eslint-disable-next-line no-console
		console.warn('Legacy GraphiQL history migration failed:', error);
		markComplete();
		return { migrated: false, skipped: 'flag' };
	}
}
