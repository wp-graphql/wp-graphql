import apiFetch from '@wordpress/api-fetch';
import { gql } from './graphql-client';

/**
 * Saved-document client.
 *
 * Phase 5 of the IDE rebuild moved the saved-document primitive onto
 * Smart Cache's `graphql_document` post type and exposed it through both
 * WP REST (via SmartCacheBridge) and the WPGraphQL schema (auto-generated
 * mutations + queries). As of this commit, CRUD on documents and
 * collections runs through GraphQL — REST stays for the three operations
 * with no GraphQL equivalent (import / export / reorder).
 *
 * Why the cutover:
 * - GraphQL gives schema-typed operations end-to-end; REST responses are
 *   loose JSON with no shape contract.
 * - The IDE already ships a `gql()` client (`./graphql-client.js`) used
 *   by ShareCollectionDialog; reuse it instead of a second wire protocol.
 * - The GraphQL schema is the canonical contract for downstream tooling
 *   (codegen, types, third-party plugins) — the IDE shouldn't have a
 *   private REST shape that diverges from it.
 *
 * The consumer-facing return shapes of every exported function are
 * preserved across the migration so callers (SavedQueriesPanel, stores,
 * dialogs) don't change. The adapter helper below maps between the
 * WPGraphQL node shape and the legacy REST-shaped object.
 *
 * Without Smart Cache active, the GraphQL types don't exist and every
 * call here throws via the gql client. The JS layer gates document
 * features on the `hasSmartCache` bootstrap flag (see
 * `src/registry/index.js`) so these functions only get invoked when the
 * schema is real.
 */

// ---------------------------------------------------------------------------
// Global ID helpers
//
// WPGraphQL mutation inputs accept Relay global IDs (`base64(type:db_id)`),
// while the IDE's consumer code historically traffics in database IDs from
// REST. We expose `databaseId` on the consumer shape (keeps existing UI
// intact) and encode to the global ID at the mutation boundary. Reads
// always request both so consumers never have to round-trip just to learn
// a global ID.
// ---------------------------------------------------------------------------

function encodeGlobalId(type, databaseId) {
	// WPGraphQL uses `base64(type:db_id)` for term + post global IDs.
	// Verified at runtime against the live schema.
	return typeof btoa === 'function'
		? btoa(`${type}:${databaseId}`)
		: Buffer.from(`${type}:${databaseId}`).toString('base64');
}

const encodePostId = (id) => encodeGlobalId('post', id);
const encodeTermId = (id) => encodeGlobalId('term', id);

// ---------------------------------------------------------------------------
// Document CRUD
// ---------------------------------------------------------------------------

// `content(format: RAW)` is critical — the default RENDERED runs through
// `the_content`, including `wpautop`, which wraps the GraphQL query in
// `<p>` and converts newlines to `<br />`. RAW returns the unfiltered
// post_content the IDE actually saved.
const DOCUMENT_FIELDS = `
	id
	databaseId
	title
	content(format: RAW)
	status
	slug
	menuOrder
	modified
	variables
	headers
	alias
	description
	maxAgeHeader
	grant
	graphqlDocumentGroups(first: 100) {
		nodes { id databaseId name }
	}
`;

// Compose the consumer-facing document shape from a `GraphqlDocument`
// node returned by the schema. Preserves the REST-era field names
// (`id` = databaseId, `query` = content, grouped `documentSettings`,
// etc.) so upstream callers don't have to change.
function adaptDocument(node) {
	if (!node) {
		return null;
	}
	const groupNodes = node.graphqlDocumentGroups?.nodes ?? [];
	return {
		id: node.databaseId,
		// `id` is kept as the legacy database ID consumers expect. The
		// global ID is preserved separately so mutation paths can pass
		// it back without re-encoding.
		globalId: node.id,
		title: node.title || '',
		query: node.content || '',
		variables: node.variables || '',
		headers: node.headers || '',
		status: node.status || 'draft',
		menuOrder: node.menuOrder ?? 0,
		modified: node.modified ?? null,
		collections: groupNodes.map((n) => n.databaseId),
		documentSettings: {
			description: node.description || '',
			aliases: node.alias || [],
			// Schema types maxAgeHeader as Int; REST exposed it as a
			// string for the same field. Coerce to string here so the
			// consumer shape is unchanged.
			maxAgeHeader:
				node.maxAgeHeader !== null && node.maxAgeHeader !== undefined
					? String(node.maxAgeHeader)
					: '',
			grant: node.grant || '',
		},
	};
}

// Build the mutation input from the IDE's doc-shaped argument. Returns
// only the keys the caller actually set so partial updates stay partial
// (don't overwrite description with "" when the caller just wanted to
// change the title).
function buildMutationInput(doc) {
	const input = {};

	if (doc.title !== undefined) {
		input.title = doc.title;
	}
	if (doc.query !== undefined) {
		input.content = doc.query;
	}
	if (doc.status !== undefined) {
		// `PostStatusEnum` is uppercase (DRAFT / PUBLISH / …); the IDE
		// stores the WordPress post_status string (lowercase) internally
		// to match what the read side returns. Upcase here at the wire.
		input.status = String(doc.status).toUpperCase();
	}
	if (doc.variables !== undefined) {
		input.variables = doc.variables;
	}
	if (doc.headers !== undefined) {
		input.headers = doc.headers;
	}
	if (doc.collections !== undefined) {
		input.graphqlDocumentGroups = {
			append: false,
			nodes: doc.collections.map((dbId) => ({ id: encodeTermId(dbId) })),
		};
	}
	if (doc.documentSettings && typeof doc.documentSettings === 'object') {
		const s = doc.documentSettings;
		if (s.description !== undefined) {
			input.description = s.description;
		}
		if (s.aliases !== undefined) {
			input.alias = s.aliases;
		}
		if (s.maxAgeHeader !== undefined && s.maxAgeHeader !== '') {
			// Schema is Int; consumer passes a string. Coerce; ignore
			// non-numeric strings so the mutation doesn't 400.
			const num = Number(s.maxAgeHeader);
			if (Number.isFinite(num)) {
				input.maxAgeHeader = num;
			}
		}
		if (s.grant !== undefined) {
			input.grant = s.grant;
		}
	}
	return input;
}

/**
 * Fetch all saved-query documents visible to the current user.
 *
 * Per-user scoping is enforced server-side by Smart Cache's auth model
 * + the IDE's connection filter — no author arg is needed.
 *
 * @return {Promise<Object[]>}
 */
export async function getDocuments() {
	const data = await gql(`
		query GetSavedDocuments {
			graphqlDocuments(
				first: 100
				where: { stati: [PUBLISH, DRAFT], orderby: { field: MENU_ORDER, order: ASC } }
			) {
				nodes { ${DOCUMENT_FIELDS} }
			}
		}
	`);
	return (data?.graphqlDocuments?.nodes ?? []).map(adaptDocument);
}

/**
 * Create a new saved-query document.
 *
 * Smart Cache validates the GraphQL document content server-side
 * (`validate_and_pre_save_cb`), so callers shouldn't try to
 * pre-validate. Empty content is allowed (drafts).
 *
 * @param {Object} doc Document data; see `DocumentRow` for the shape.
 * @return {Promise<Object>} Created document.
 */
export async function createDocument(doc) {
	const input = buildMutationInput({
		title: doc.title || 'Untitled',
		query: doc.query || '',
		status: doc.status || 'draft',
		variables: doc.variables,
		headers: doc.headers,
		collections: doc.collections,
		documentSettings: doc.documentSettings,
	});
	const data = await gql(
		`mutation CreateDocument($input: CreateGraphqlDocumentInput!) {
			createGraphqlDocument(input: $input) {
				graphqlDocument { ${DOCUMENT_FIELDS} }
			}
		}`,
		{ input }
	);
	return adaptDocument(data?.createGraphqlDocument?.graphqlDocument);
}

/**
 * Update an existing saved-query document.
 *
 * @param {number} id  Database ID.
 * @param {Object} doc Fields to update.
 * @return {Promise<Object>} Updated document.
 */
export async function updateDocument(id, doc) {
	const input = {
		id: encodePostId(id),
		...buildMutationInput(doc),
	};
	const data = await gql(
		`mutation UpdateDocument($input: UpdateGraphqlDocumentInput!) {
			updateGraphqlDocument(input: $input) {
				graphqlDocument { ${DOCUMENT_FIELDS} }
			}
		}`,
		{ input }
	);
	return adaptDocument(data?.updateGraphqlDocument?.graphqlDocument);
}

/**
 * Delete a saved-query document.
 *
 * The mutation always force-deletes — WPGraphQL's
 * `deleteGraphqlDocument` doesn't model the trash flow, and the REST
 * path's `force=true` was the only mode the IDE ever used.
 *
 * @param {number} id Database ID.
 * @return {Promise<{deletedId:number}>} Deletion response.
 */
export async function deleteDocument(id) {
	const data = await gql(
		`mutation DeleteDocument($input: DeleteGraphqlDocumentInput!) {
			deleteGraphqlDocument(input: $input) {
				deletedId
			}
		}`,
		{ input: { id: encodePostId(id) } }
	);
	return { deletedId: data?.deleteGraphqlDocument?.deletedId ?? id };
}

// ---------------------------------------------------------------------------
// Collections (graphql_document_group taxonomy)
// ---------------------------------------------------------------------------

// Compose the REST-era `{ id, name, count }` shape from a
// `GraphqlDocumentGroup` node. `id` is the database ID; `globalId`
// hangs onto the Relay global ID for mutation paths.
function adaptCollection(node) {
	return {
		id: node.databaseId,
		globalId: node.id,
		name: node.name || '',
		count: node.count ?? 0,
	};
}

/**
 * Fetch all collections.
 *
 * @return {Promise<Object[]>}
 */
export async function getCollections() {
	const data = await gql(`
		query GetCollections {
			graphqlDocumentGroups(first: 100) {
				nodes { id databaseId name count }
			}
		}
	`);
	return (data?.graphqlDocumentGroups?.nodes ?? []).map(adaptCollection);
}

/**
 * Create a new collection.
 *
 * @param {string} name Collection display name.
 */
export async function createCollection(name) {
	const data = await gql(
		`mutation CreateCollection($input: CreateGraphqlDocumentGroupInput!) {
			createGraphqlDocumentGroup(input: $input) {
				graphqlDocumentGroup { id databaseId name count }
			}
		}`,
		{ input: { name } }
	);
	return adaptCollection(
		data?.createGraphqlDocumentGroup?.graphqlDocumentGroup
	);
}

/**
 * Rename a collection.
 *
 * @param {number} id   Collection database ID.
 * @param {string} name New display name.
 */
export async function renameCollection(id, name) {
	const data = await gql(
		`mutation UpdateCollection($input: UpdateGraphqlDocumentGroupInput!) {
			updateGraphqlDocumentGroup(input: $input) {
				graphqlDocumentGroup { id databaseId name count }
			}
		}`,
		{ input: { id: encodeTermId(id), name } }
	);
	return adaptCollection(
		data?.updateGraphqlDocumentGroup?.graphqlDocumentGroup
	);
}

/**
 * Delete a collection.
 *
 * Documents assigned to the term lose the assignment; they aren't
 * deleted. The old cascade-delete REST route was removed in 5.0 —
 * callers that want to delete documents along with the collection do
 * it explicitly (see SavedQueriesPanel's client-side cascade).
 *
 * @param {number} id Collection database ID.
 */
export async function deleteCollection(id) {
	const data = await gql(
		`mutation DeleteCollection($input: DeleteGraphqlDocumentGroupInput!) {
			deleteGraphqlDocumentGroup(input: $input) {
				deletedId
			}
		}`,
		{ input: { id: encodeTermId(id) } }
	);
	return { deletedId: data?.deleteGraphqlDocumentGroup?.deletedId ?? id };
}

// ---------------------------------------------------------------------------
// Bulk / non-CRUD operations — stay on REST (no GraphQL equivalent)
//
// These four endpoints orchestrate batch / reorder work that doesn't map
// to standard CRUD. Custom GraphQL mutations could be added in the
// future (`importGraphqlDocuments`, `reorderGraphqlDocuments`, etc.) but
// the cost/benefit is low — REST is the right tool here.
// ---------------------------------------------------------------------------

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
 * @param {Array<number>} ids Document database IDs in their new order.
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
 * @param {Array<number>} ids Term database IDs in their new order.
 */
export async function reorderCollections(ids) {
	return apiFetch({
		path: '/wpgraphql-ide/v1/collections/reorder',
		method: 'POST',
		data: { order: ids },
	});
}
