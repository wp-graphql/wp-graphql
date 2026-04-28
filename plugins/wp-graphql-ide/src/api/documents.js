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
		path: `${ENDPOINT}?per_page=100&status=publish,draft&context=edit&_fields=id,title,content,status,meta,graphql-ide-collections`,
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
