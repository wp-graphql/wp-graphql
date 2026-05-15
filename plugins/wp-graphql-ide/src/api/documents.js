import apiFetch from '@wordpress/api-fetch';

// As of IDE 5.0 the IDE no longer owns its own document post type —
// Smart Cache's `graphql_document` is the canonical owner. The IDE's
// SmartCacheBridge (PHP) filters Smart Cache's registrations to add
// `show_in_rest` so the WP REST API serves the post type and its
// taxonomies under these default routes.
//
// Without Smart Cache active, these routes don't exist and every call
// here will 404. The JS layer gates document features on the
// `hasSmartCache` bootstrap flag (see src/registry/index.js) so these
// functions only get invoked when the routes are real.
const ENDPOINT = '/wp/v2/graphql_document';
const COLLECTIONS_ENDPOINT = '/wp/v2/graphql_document_group';

// Default taxonomy field name in the WP REST post response. WP REST
// auto-includes a field per registered taxonomy on the post type;
// since `graphql_document_group` is registered without a `rest_base`,
// the field name on the post object matches the taxonomy slug.
const COLLECTIONS_FIELD = 'graphql_document_group';

/**
 * Fetch all saved-query documents visible to the current user.
 *
 * Per-user scoping is enforced on the server side by Access.php's
 * `scope_rest_queries` filter — this client doesn't pass an author
 * arg explicitly.
 *
 * @return {Promise<Array>} Array of document objects.
 */
export async function getDocuments() {
	const fields = [
		'id',
		'title',
		'content',
		'status',
		'meta',
		'menu_order',
		'modified',
		COLLECTIONS_FIELD,
		'documentSettings',
	].join(',');

	const posts = await apiFetch({
		path: `${ENDPOINT}?per_page=100&status=publish,draft&context=edit&orderby=menu_order&order=asc&_fields=${fields}`,
	});

	return posts.map(normalizeDocument);
}

/**
 * Create a new saved-query document.
 *
 * Smart Cache's `validate_and_pre_save_cb` runs on every non-admin
 * insert and AST-validates the content. Non-empty content that isn't
 * valid GraphQL will be rejected by the server with a `RequestError`.
 * Empty content is allowed (drafts).
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
		data[COLLECTIONS_FIELD] = doc.collections;
	}
	if (doc.documentSettings && typeof doc.documentSettings === 'object') {
		data.documentSettings = doc.documentSettings;
	}

	const post = await apiFetch({
		path: ENDPOINT,
		method: 'POST',
		data,
	});

	return normalizeDocument(post);
}

/**
 * Update an existing saved-query document.
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
		data[COLLECTIONS_FIELD] = doc.collections;
	}
	if (doc.documentSettings && typeof doc.documentSettings === 'object') {
		data.documentSettings = doc.documentSettings;
	}

	const post = await apiFetch({
		path: `${ENDPOINT}/${id}`,
		method: 'POST',
		data,
	});

	return normalizeDocument(post);
}

/**
 * Delete a saved-query document.
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
		collections: post[COLLECTIONS_FIELD] ?? [],
		documentSettings:
			post.documentSettings && typeof post.documentSettings === 'object'
				? post.documentSettings
				: {},
		modified: post.modified ?? null,
	};
}

/**
 * Fetch all collections (Smart Cache's `graphql_document_group` taxonomy terms).
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
 * Documents previously assigned to the term lose their assignment;
 * they aren't deleted. The old cascade-delete REST route was removed
 * in 5.0 — callers that want to delete documents along with the
 * collection do it explicitly.
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
 * Persist a new menu_order for the given document IDs.
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
