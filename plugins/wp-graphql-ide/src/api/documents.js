import apiFetch from '@wordpress/api-fetch';

const ENDPOINT = '/wp/v2/graphql-ide-queries';

/**
 * Fetch all IDE query documents for the current user.
 *
 * @return {Promise<Array>} Array of document objects.
 */
export async function getDocuments() {
	const posts = await apiFetch({
		path: `${ENDPOINT}?per_page=100&status=publish,draft&context=edit&_fields=id,title,content,meta`,
	});

	return posts.map(normalizeDocument);
}

/**
 * Fetch a single document by ID.
 *
 * @param {number} id Post ID.
 * @return {Promise<Object>} Document object.
 */
export async function getDocument(id) {
	const post = await apiFetch({
		path: `${ENDPOINT}/${id}?context=edit&_fields=id,title,content,meta`,
	});

	return normalizeDocument(post);
}

/**
 * Create a new IDE query document.
 *
 * @param {Object} doc             Document data.
 * @param {string} doc.title       Tab/operation name.
 * @param {string} doc.query       GraphQL query string.
 * @param {string} [doc.variables] Variables JSON string.
 * @param {string} [doc.headers]   Headers JSON string.
 * @return {Promise<Object>} Created document.
 */
export async function createDocument(doc) {
	const post = await apiFetch({
		path: ENDPOINT,
		method: 'POST',
		data: {
			title: doc.title || 'Untitled',
			content: doc.query || '',
			status: 'publish',
			meta: {
				_graphql_ide_variables: doc.variables || '',
				_graphql_ide_headers: doc.headers || '',
			},
		},
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
	if (
		doc.variables !== undefined ||
		doc.headers !== undefined ||
		doc.history !== undefined
	) {
		data.meta = {};
		if (doc.variables !== undefined) {
			data.meta._graphql_ide_variables = doc.variables;
		}
		if (doc.headers !== undefined) {
			data.meta._graphql_ide_headers = doc.headers;
		}
		if (doc.history !== undefined) {
			data.meta._graphql_ide_history = doc.history;
		}
	}

	const post = await apiFetch({
		path: `${ENDPOINT}/${id}`,
		method: 'POST',
		data,
	});

	return normalizeDocument(post);
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
		title: post.title?.raw || post.title?.rendered || post.title || '',
		query:
			post.content?.raw || post.content?.rendered || post.content || '',
		variables: post.meta?._graphql_ide_variables || '',
		headers: post.meta?._graphql_ide_headers || '',
		history: post.meta?._graphql_ide_history || [],
	};
}
