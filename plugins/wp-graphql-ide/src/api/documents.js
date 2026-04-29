import apiFetch from '@wordpress/api-fetch';

const ENDPOINT = '/wp/v2/graphql-ide-queries';
const COLLECTIONS_ENDPOINT = '/wp/v2/graphql-ide-collections';

/**
 * Fetch all IDE query documents for the current user.
 *
 * @return {Promise<Array>} Array of document objects.
 */
export async function getDocuments() {
	const posts = await apiFetch({
		path: `${ENDPOINT}?per_page=100&status=publish,draft&context=edit&orderby=menu_order&order=asc&_fields=id,title,content,status,meta,menu_order,modified,graphql-ide-collections`,
	});

	return posts.map(normalizeDocument);
}

/**
 * Create a new IDE query document.
 *
 * @param {Object} doc             Document data.
 * @param {string} doc.title       Tab/operation name.
 * @param {string} doc.query       GraphQL query string.
 * @param {string} [doc.variables] Variables JSON string.
 * @param {string} [doc.headers]   Headers JSON string.
 * @param {string} [doc.status]    Post status (default: 'draft').
 * @return {Promise<Object>} Created document.
 */
export async function createDocument(doc) {
	const data = {
		title: doc.title || 'Untitled',
		content: doc.query || '',
		status: doc.status || 'draft',
		meta: {
			_graphql_ide_variables: doc.variables || '',
			_graphql_ide_headers: doc.headers || '',
		},
	};
	if (doc.collections) {
		data['graphql-ide-collections'] = doc.collections;
	}

	const post = await apiFetch({
		path: ENDPOINT,
		method: 'POST',
		data,
	});

	return normalizeDocument(post);
}

/**
 * Update an existing IDE query document.
 *
 * @param {number} id  Post ID.
 * @param {Object} doc Fields to update.
 * @return {Promise<Object>} Updated document.
 */
export async function updateDocument(id, doc) {
	const data = {};

	if (doc.title !== undefined) {
		data.title = doc.title;
	}
	if (doc.query !== undefined) {
		data.content = doc.query;
	}
	if (doc.status !== undefined) {
		data.status = doc.status;
	}
	if (doc.variables !== undefined || doc.headers !== undefined) {
		data.meta = {};
		if (doc.variables !== undefined) {
			data.meta._graphql_ide_variables = doc.variables;
		}
		if (doc.headers !== undefined) {
			data.meta._graphql_ide_headers = doc.headers;
		}
	}
	if (doc.collections !== undefined) {
		data['graphql-ide-collections'] = doc.collections;
	}

	const post = await apiFetch({
		path: `${ENDPOINT}/${id}`,
		method: 'POST',
		data,
	});

	return normalizeDocument(post);
}

/**
 * Publish a draft document.
 *
 * The server computes the SHA-256 hash of the AST-normalized query,
 * sets it as the post slug (queryId), and changes status to publish.
 * If the query is already published, returns the existing document.
 *
 * @param {number} id Post ID.
 * @return {Promise<Object>} Publish result with id, status, query_hash.
 */
export async function publishDocument(id) {
	return apiFetch({
		path: `/wpgraphql-ide/v1/documents/${id}/publish`,
		method: 'POST',
	});
}

/**
 * Delete an IDE query document.
 *
 * @param {number}  id    Post ID.
 * @param {boolean} force Whether to bypass trash (default false).
 * @return {Promise<Object>} Deletion response.
 */
export async function deleteDocument(id, force = false) {
	return apiFetch({
		path: `${ENDPOINT}/${id}?force=${force}`,
		method: 'DELETE',
	});
}

/**
 * Normalize a REST API post response into a flat document object.
 *
 * @param {Object} post WP REST API post object.
 * @return {Object} Normalized document.
 */
function normalizeDocument(post) {
	return {
		id: post.id,
		title: post.title?.raw ?? post.title?.rendered ?? '',
		query: post.content?.raw ?? post.content?.rendered ?? '',
		variables: post.meta?._graphql_ide_variables ?? '',
		headers: post.meta?._graphql_ide_headers ?? '',
		status: post.status ?? 'draft',
		collections: post['graphql-ide-collections'] ?? [],
		modified: post.modified ?? null,
	};
}

/**
 * Fetch all collections (taxonomy terms).
 *
 * @return {Promise<Array>} Array of { id, name, count } objects.
 */
export async function getCollections() {
	const terms = await apiFetch({
		path: `${COLLECTIONS_ENDPOINT}?per_page=100&_fields=id,name,count`,
	});
	return terms.map((t) => ({ id: t.id, name: t.name, count: t.count }));
}

/**
 * Create a new collection.
 *
 * @param {string} name Collection name.
 * @return {Promise<Object>} Created collection { id, name, count }.
 */
export async function createCollection(name) {
	const term = await apiFetch({
		path: COLLECTIONS_ENDPOINT,
		method: 'POST',
		data: { name },
	});
	return { id: term.id, name: term.name, count: term.count ?? 0 };
}

/**
 * Rename a collection.
 *
 * @param {number} id   Term ID.
 * @param {string} name New name.
 * @return {Promise<Object>} Updated collection { id, name, count }.
 */
export async function renameCollection(id, name) {
	const term = await apiFetch({
		path: `${COLLECTIONS_ENDPOINT}/${id}`,
		method: 'POST',
		data: { name },
	});
	return { id: term.id, name: term.name, count: term.count ?? 0 };
}

/**
 * Delete a collection.
 *
 * @param {number} id Term ID.
 * @return {Promise<Object>} Deletion response.
 */
export async function deleteCollection(id) {
	return apiFetch({
		path: `${COLLECTIONS_ENDPOINT}/${id}?force=true`,
		method: 'DELETE',
	});
}

/**
 * Delete a collection and every document in it owned by the current
 * user. Documents owned by other users are left intact.
 *
 * @param {number} id Term ID.
 * @return {Promise<Object>} Cascade response with deleted post IDs.
 */
export async function deleteCollectionWithContents(id) {
	return apiFetch({
		path: `/wpgraphql-ide/v1/collections/${id}/cascade`,
		method: 'DELETE',
	});
}

/**
 * Export the current user's documents grouped by collection.
 *
 * @return {Promise<Object>} JSON payload matching the seed schema.
 */
export async function exportDocuments() {
	return apiFetch({
		path: '/wpgraphql-ide/v1/documents/export',
	});
}

/**
 * Import a documents payload (same shape as the export and seed JSON).
 *
 * @param {Object} payload Object with a `collections` array.
 * @return {Promise<Object>} Counts of created/skipped documents.
 */
export async function importDocuments(payload) {
	return apiFetch({
		path: '/wpgraphql-ide/v1/documents/import',
		method: 'POST',
		data: payload,
	});
}

/**
 * Persist a new menu_order for the given document IDs (server stores
 * the position as `menu_order`; the documents endpoint sorts by it).
 *
 * @param {Array<number>} ids Document IDs in their new order.
 */
export async function reorderDocuments(ids) {
	return apiFetch({
		path: '/wpgraphql-ide/v1/documents/reorder',
		method: 'POST',
		data: { order: ids },
	});
}

/**
 * Persist a per-user collection order. Term order is user-scoped via
 * the `wpgraphql_ide_collection_order` user-meta.
 *
 * @param {Array<number>} ids Term IDs in their new order.
 */
export async function reorderCollections(ids) {
	return apiFetch({
		path: '/wpgraphql-ide/v1/collections/reorder',
		method: 'POST',
		data: { order: ids },
	});
}
