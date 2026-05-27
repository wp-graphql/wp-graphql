import { gql } from './graphql-client';
import {
	getLocalHistory,
	createLocalHistoryEntry,
	deleteLocalHistoryEntry,
	clearLocalHistory,
} from './history-local';
import { HISTORY_ENTRY_FRAGMENT } from '../components/HistoryEntry';

/**
 * Execution-history client. Routes between two backends based on auth
 * state, picked up at call time from `window.WPGRAPHQL_IDE_DATA`:
 *
 *   - **Logged-in users** → server-backed `graphql_ide_history` CPT
 *     via GraphQL. The `IdeHistoryEntry` type exposes every meta field
 *     (queryString / variables / headers / durationMs / executionStatus
 *     / documentId / isAuthenticated / httpMethod), and the auto-
 *     generated mutations cover create + delete. Per-user scoping is
 *     enforced server-side via a connection filter; clients don't pass
 *     an author argument.
 *
 *   - **Anonymous public-endpoint visitors** → browser-local bucket
 *     via `./history-local.js`. Same model GraphiQL itself uses (per-
 *     browser, multi-anon-user shared). Lets non-authenticated devs
 *     working against `/graphql` still get the history affordance.
 *
 * The 50-entry cap is enforced client-side on both paths.
 */

function isLoggedIn() {
	return (
		typeof window !== 'undefined' &&
		!!window.WPGRAPHQL_IDE_DATA?.isUserLoggedIn
	);
}

const MAX_ENTRIES = 50;

function encodePostId(databaseId) {
	return typeof btoa === 'function'
		? btoa(`post:${databaseId}`)
		: Buffer.from(`post:${databaseId}`).toString('base64');
}

// Adapt the GraphQL `IdeHistoryEntry` node shape into the consumer's
// historical (REST-era) shape — snake_case field names, unix-seconds
// timestamp, database ID as `id` — so the rest of the IDE doesn't
// have to change.
function adaptHistoryEntry(node) {
	return {
		id: node.databaseId,
		globalId: node.id,
		timestamp: node.date
			? Math.floor(new Date(node.date).getTime() / 1000)
			: Math.floor(Date.now() / 1000),
		query: node.queryString || '',
		variables: node.variables || '',
		headers: node.headers || '',
		duration_ms: node.durationMs ?? 0,
		status: node.executionStatus || '',
		document_id: node.documentId ?? 0,
		is_authenticated:
			node.isAuthenticated === null || node.isAuthenticated === undefined
				? true
				: node.isAuthenticated,
		http_method: node.httpMethod || 'POST',
	};
}

/**
 * Fetch execution history for the current visitor. Routes to the
 * server CPT when logged in, the localStorage bucket when anonymous.
 *
 * @return {Promise<Object[]>} Newest-first.
 */
export async function getHistory() {
	if (!isLoggedIn()) {
		return getLocalHistory();
	}
	const data = await gql(
		`${HISTORY_ENTRY_FRAGMENT}
		query GetIdeHistory($first: Int!) {
			ideHistoryEntries(first: $first, where: { stati: [PUBLISH], orderby: { field: DATE, order: DESC } }) {
				nodes { ...IdeHistoryEntryFields }
			}
		}`,
		{ first: MAX_ENTRIES }
	);
	return (data?.ideHistoryEntries?.nodes ?? []).map(adaptHistoryEntry);
}

/**
 * Create a new history entry. Mirrors the REST contract (including
 * oldest-id pruning).
 *
 * The mutation accepts the meta fields directly. `_graphql_ide_*` meta
 * keys are registered on the `graphql_ide_history` post type with
 * matching GraphQL field aliases (see GraphQLSchema.php) so the input
 * names align with the output names.
 *
 * @param {Object} entry Same snake_case shape the REST writer accepted:
 *                       `{ query, variables, headers, duration_ms, status, document_id,
 *                       is_authenticated, http_method }`. Optional `oldestId` triggers
 *                       a fire-and-forget delete once we exceed `MAX_ENTRIES`.
 * @return {Promise<Object>} Adapted entry — see `getHistory` for shape.
 */
export async function createHistoryEntry(entry) {
	if (!isLoggedIn()) {
		return createLocalHistoryEntry(entry);
	}

	// `_graphql_ide_document_id` is type integer on the underlying meta.
	// Callers can hand us a temp-ID string (`temp-…`), undefined, or any
	// other non-numeric — coerce to a positive integer or 0 so the
	// mutation doesn't reject the input.
	const docIdNum = Number(entry.document_id);
	const docId = Number.isFinite(docIdNum) && docIdNum > 0 ? docIdNum : 0;

	const input = {
		status: 'PUBLISH',
		queryString: entry.query ?? '',
		variables: entry.variables ?? '',
		headers: entry.headers ?? '',
		durationMs: entry.duration_ms ?? 0,
		executionStatus: entry.status ?? '',
		documentId: docId,
		isAuthenticated: entry.is_authenticated ?? true,
		httpMethod: entry.http_method ?? 'POST',
	};

	const data = await gql(
		`${HISTORY_ENTRY_FRAGMENT}
		mutation CreateIdeHistoryEntry($input: CreateIdeHistoryEntryInput!) {
			createIdeHistoryEntry(input: $input) {
				ideHistoryEntry { ...IdeHistoryEntryFields }
			}
		}`,
		{ input }
	);

	// Prune oldest if we're over the limit. Fire-and-forget — same as
	// the REST implementation. The user doesn't block on pruning, and
	// a failed prune is recoverable on the next write.
	if (entry.oldestId) {
		deleteHistoryEntry(entry.oldestId).catch(() => {});
	}

	return adaptHistoryEntry(data?.createIdeHistoryEntry?.ideHistoryEntry);
}

/**
 * Delete a single history entry.
 *
 * @param {number} id Database ID.
 * @return {Promise<{deletedId:number}>}
 */
export async function deleteHistoryEntry(id) {
	if (!isLoggedIn()) {
		return deleteLocalHistoryEntry(id);
	}
	const data = await gql(
		`mutation DeleteIdeHistoryEntry($input: DeleteIdeHistoryEntryInput!) {
			deleteIdeHistoryEntry(input: $input) {
				deletedId
			}
		}`,
		{ input: { id: encodePostId(id) } }
	);
	return { deletedId: data?.deleteIdeHistoryEntry?.deletedId ?? id };
}

/**
 * Delete every history entry whose ID is in `ids`.
 *
 * WPGraphQL has no bulk-delete mutation, so this is a client-side
 * fan-out — same pattern the cascade-collection delete uses
 * (SavedQueriesPanel performDeleteCollection). All deletes run in
 * parallel; the caller awaits the combined result.
 *
 * @param {Array<number>} ids
 * @return {Promise<void>}
 */
export async function clearHistory(ids) {
	if (!isLoggedIn()) {
		return clearLocalHistory(ids);
	}
	await Promise.all(ids.map((id) => deleteHistoryEntry(id)));
}
