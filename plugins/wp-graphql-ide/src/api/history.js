import apiFetch from '@wordpress/api-fetch';

const ENDPOINT = '/wp/v2/graphql-ide-history';
const MAX_ENTRIES = 50;

/**
 * Fetch execution history for the current user.
 *
 * @return {Promise<Array>} Array of history entry objects, newest first.
 */
export async function getHistory() {
	const posts = await apiFetch({
		path: `${ENDPOINT}?per_page=${MAX_ENTRIES}&status=publish&orderby=date&order=desc&_fields=id,date,meta`,
	});

	return posts.map(normalizeHistoryEntry);
}

/**
 * Create a new history entry.
 *
 * If the total count exceeds MAX_ENTRIES, the oldest entry is deleted.
 *
 * @param {Object}  entry                  History entry data.
 * @param {string}  entry.query            GraphQL query string.
 * @param {string}  entry.variables        Variables JSON string.
 * @param {string}  entry.headers          Headers JSON string.
 * @param {number}  entry.duration_ms      Execution duration in ms.
 * @param {string}  entry.status           'success' or 'error'.
 * @param {number}  entry.document_id      Source document ID.
 * @param {boolean} entry.is_authenticated Whether the request was authenticated.
 * @param {number}  [entry.oldestId]       ID of the oldest entry to prune.
 * @return {Promise<Object>} Created history entry.
 */
export async function createHistoryEntry(entry) {
	const post = await apiFetch({
		path: ENDPOINT,
		method: 'POST',
		data: {
			status: 'publish',
			meta: {
				_graphql_ide_query: entry.query ?? '',
				_graphql_ide_variables: entry.variables ?? '',
				_graphql_ide_headers: entry.headers ?? '',
				_graphql_ide_duration_ms: entry.duration_ms ?? 0,
				_graphql_ide_status: entry.status ?? '',
				_graphql_ide_document_id: entry.document_id ?? 0,
				_graphql_ide_is_authenticated: entry.is_authenticated ?? true,
			},
		},
	});

	// Prune oldest if we're over the limit.
	if (entry.oldestId) {
		deleteHistoryEntry(entry.oldestId).catch(() => {});
	}

	return normalizeHistoryEntry(post);
}

/**
 * Delete a single history entry.
 *
 * @param {number} id Post ID.
 * @return {Promise<Object>} Deletion response.
 */
export async function deleteHistoryEntry(id) {
	return apiFetch({
		path: `${ENDPOINT}/${id}?force=true`,
		method: 'DELETE',
	});
}

/**
 * Delete all history entries for the current user.
 *
 * @param {Array<number>} ids Array of history entry IDs to delete.
 * @return {Promise<void>}
 */
export async function clearHistory(ids) {
	await Promise.all(ids.map((id) => deleteHistoryEntry(id)));
}

/**
 * Normalize a REST API post response into a flat history entry.
 *
 * @param {Object} post WP REST API post object.
 * @return {Object} Normalized history entry.
 */
function normalizeHistoryEntry(post) {
	return {
		id: post.id,
		timestamp: post.date
			? Math.floor(new Date(post.date).getTime() / 1000)
			: Math.floor(Date.now() / 1000),
		query: post.meta?._graphql_ide_query ?? '',
		variables: post.meta?._graphql_ide_variables ?? '',
		headers: post.meta?._graphql_ide_headers ?? '',
		duration_ms: post.meta?._graphql_ide_duration_ms ?? 0,
		status: post.meta?._graphql_ide_status ?? '',
		document_id: post.meta?._graphql_ide_document_id ?? 0,
		is_authenticated: post.meta?._graphql_ide_is_authenticated ?? true,
	};
}
