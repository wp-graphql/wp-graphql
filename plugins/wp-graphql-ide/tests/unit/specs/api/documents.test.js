/* eslint-env browser, jest */
import {
	getDocuments,
	createDocument,
	updateDocument,
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
