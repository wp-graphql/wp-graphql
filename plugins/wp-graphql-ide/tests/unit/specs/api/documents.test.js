/* eslint-env browser, jest */
import {
	getDocuments,
	createDocument,
	updateDocument,
	exportDocuments,
	importDocuments,
	reorderDocuments,
} from '../../../../src/api/documents';

// Each function builds a GraphQL query that selects DOCUMENT_FIELDS on a
// `GraphqlDocument` node. We intercept the outgoing `fetch` body so we
// can assert the wire-format the IDE is actually sending — in particular
// that `content` is fetched with `format: RAW` (without it the WPGraphQL
// default RENDERED runs the saved query through `the_content` → wpautop,
// turning a raw GraphQL document into `<p>{<br />…</p>`).
describe('documents api — content format', () => {
	const ENDPOINT = 'http://example.test/graphql';

	beforeEach(() => {
		window.WPGRAPHQL_IDE_DATA = {
			graphqlEndpoint: ENDPOINT,
			nonce: 'test-nonce',
		};
		global.fetch = jest.fn();
	});

	afterEach(() => {
		delete global.fetch;
		delete window.WPGRAPHQL_IDE_DATA;
	});

	function mockOk(json) {
		global.fetch.mockResolvedValueOnce({
			ok: true,
			status: 200,
			json: () => Promise.resolve(json),
		});
	}

	function lastSentQuery() {
		const lastCall = global.fetch.mock.calls.at(-1);
		const body = JSON.parse(lastCall[1].body);
		return body.query;
	}

	it('getDocuments fetches content as RAW', async () => {
		mockOk({ data: { graphqlDocuments: { nodes: [] } } });
		await getDocuments();
		expect(lastSentQuery()).toMatch(/content\(format:\s*RAW\)/);
	});

	it('createDocument fetches the returned content as RAW', async () => {
		mockOk({
			data: {
				createGraphqlDocument: {
					graphqlDocument: { id: 'g', databaseId: 1 },
				},
			},
		});
		await createDocument({ title: 't', query: '{ __typename }' });
		expect(lastSentQuery()).toMatch(/content\(format:\s*RAW\)/);
	});

	it('updateDocument fetches the returned content as RAW', async () => {
		mockOk({
			data: {
				updateGraphqlDocument: {
					graphqlDocument: { id: 'g', databaseId: 1 },
				},
			},
		});
		await updateDocument(1, { title: 't' });
		expect(lastSentQuery()).toMatch(/content\(format:\s*RAW\)/);
	});
});

// WPGraphQL's `PostStatusEnum` is uppercase (DRAFT / PUBLISH / …) — the
// IDE stores the post_status string lowercase internally to match what
// the read side returns. `buildMutationInput` upcases at the wire; this
// covers both create (which defaults to 'draft') and update (publish).
describe('documents api — status casing on the wire', () => {
	const ENDPOINT = 'http://example.test/graphql';

	beforeEach(() => {
		window.WPGRAPHQL_IDE_DATA = {
			graphqlEndpoint: ENDPOINT,
			nonce: 'test-nonce',
		};
		global.fetch = jest.fn();
	});

	afterEach(() => {
		delete global.fetch;
		delete window.WPGRAPHQL_IDE_DATA;
	});

	function mockOk(json) {
		global.fetch.mockResolvedValueOnce({
			ok: true,
			status: 200,
			json: () => Promise.resolve(json),
		});
	}

	function lastSentInput() {
		const lastCall = global.fetch.mock.calls.at(-1);
		const body = JSON.parse(lastCall[1].body);
		return body.variables?.input;
	}

	it('createDocument sends DRAFT (upper) for the default draft status', async () => {
		mockOk({
			data: {
				createGraphqlDocument: {
					graphqlDocument: { id: 'g', databaseId: 1 },
				},
			},
		});
		await createDocument({ title: 't' });
		expect(lastSentInput().status).toBe('DRAFT');
	});

	it('updateDocument sends PUBLISH (upper) when publishing', async () => {
		mockOk({
			data: {
				updateGraphqlDocument: {
					graphqlDocument: { id: 'g', databaseId: 1 },
				},
			},
		});
		await updateDocument(1, { status: 'publish' });
		expect(lastSentInput().status).toBe('PUBLISH');
	});
});

// Smart Cache stamps the sha256 of the normalized query as an alias
// taxonomy term on every save (its content-addressed identity). If the
// IDE round-trips that hash as a user alias on subsequent saves, the
// server rejects with "alias already in use by another query" for any
// other doc that shares the content. `adaptDocument` must strip the
// auto-hash so docSettingsValues / mutation inputs only carry user-set
// aliases.
describe('documents api — alias surface filters Smart Cache auto-hash', () => {
	const ENDPOINT = 'http://example.test/graphql';

	beforeEach(() => {
		window.WPGRAPHQL_IDE_DATA = {
			graphqlEndpoint: ENDPOINT,
			nonce: 'test-nonce',
		};
		global.fetch = jest.fn();
	});

	afterEach(() => {
		delete global.fetch;
		delete window.WPGRAPHQL_IDE_DATA;
	});

	function mockOk(json) {
		global.fetch.mockResolvedValueOnce({
			ok: true,
			status: 200,
			json: () => Promise.resolve(json),
		});
	}

	const SHA256 =
		'dbf44d5f37b20ab4c605bf196d5e0c1116446506969d790f66bd1780d4ff8ae2';

	it('strips a 64-char hex auto-hash from aliases on read', async () => {
		mockOk({
			data: {
				createGraphqlDocument: {
					graphqlDocument: {
						id: 'g',
						databaseId: 1,
						alias: [SHA256, 'GetPosts'],
					},
				},
			},
		});
		const doc = await createDocument({ title: 't', query: '{x}' });
		expect(doc.documentSettings.aliases).toEqual(['GetPosts']);
	});

	it('leaves an empty array when only the auto-hash is present', async () => {
		mockOk({
			data: {
				updateGraphqlDocument: {
					graphqlDocument: {
						id: 'g',
						databaseId: 1,
						alias: [SHA256],
					},
				},
			},
		});
		const doc = await updateDocument(1, { title: 't' });
		expect(doc.documentSettings.aliases).toEqual([]);
	});

	it('preserves user aliases that happen to share hex chars but are not 64 long', async () => {
		mockOk({
			data: {
				updateGraphqlDocument: {
					graphqlDocument: {
						id: 'g',
						databaseId: 1,
						alias: ['abc123', 'deadbeef'],
					},
				},
			},
		});
		const doc = await updateDocument(1, { title: 't' });
		expect(doc.documentSettings.aliases).toEqual(['abc123', 'deadbeef']);
	});
});

// The bulk-orchestration routes still go through REST (no good GraphQL
// equivalent). These wrappers are thin `apiFetch` calls but they're the
// only contract the import/export UI and the drag-reorder UI rely on,
// so the request shape (path, method, body) is worth locking down.
jest.mock('@wordpress/api-fetch');
// eslint-disable-next-line import/first
import apiFetch from '@wordpress/api-fetch';

describe('documents api — REST orchestration wrappers', () => {
	beforeEach(() => {
		apiFetch.mockReset();
	});

	it('exportDocuments issues a GET to the export route and returns the payload', async () => {
		const payload = { version: 1, collections: [] };
		apiFetch.mockResolvedValueOnce(payload);
		const result = await exportDocuments();
		expect(apiFetch).toHaveBeenCalledWith({
			path: '/wpgraphql-ide/v1/documents/export',
		});
		expect(result).toBe(payload);
	});

	it('importDocuments POSTs the payload body verbatim', async () => {
		apiFetch.mockResolvedValueOnce({ created: 2, skipped: 0 });
		const payload = {
			version: 1,
			collections: [
				{ name: 'Examples', documents: [{ query: '{ x }' }] },
			],
		};
		await importDocuments(payload);
		expect(apiFetch).toHaveBeenCalledWith({
			path: '/wpgraphql-ide/v1/documents/import',
			method: 'POST',
			data: payload,
		});
	});

	it('reorderDocuments POSTs the new order as `{ order: [...] }`', async () => {
		apiFetch.mockResolvedValueOnce({ ok: true });
		await reorderDocuments([3, 1, 2]);
		expect(apiFetch).toHaveBeenCalledWith({
			path: '/wpgraphql-ide/v1/documents/reorder',
			method: 'POST',
			data: { order: [3, 1, 2] },
		});
	});

	it('reorderDocuments handles an empty array (clears server-side order)', async () => {
		apiFetch.mockResolvedValueOnce({ ok: true });
		await reorderDocuments([]);
		expect(apiFetch).toHaveBeenCalledWith({
			path: '/wpgraphql-ide/v1/documents/reorder',
			method: 'POST',
			data: { order: [] },
		});
	});
});
