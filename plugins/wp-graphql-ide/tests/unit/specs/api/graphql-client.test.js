import {
	gql,
	GraphQLClientError,
} from '../../../../src/api/graphql-client';

describe('graphql-client', () => {
	const ENDPOINT = 'http://example.test/graphql';

	beforeEach(() => {
		// Reset the bootstrap blob the client reads from.
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

	function mockResponse({
		ok = true,
		status = 200,
		json = { data: { ok: true } },
	} = {}) {
		global.fetch.mockResolvedValueOnce({
			ok,
			status,
			json: () => Promise.resolve(json),
		});
	}

	describe('happy path', () => {
		it('returns the data field on a successful response', async () => {
			mockResponse({ json: { data: { foo: 'bar' } } });
			const data = await gql('{ foo }');
			expect(data).toEqual({ foo: 'bar' });
		});

		it('sends the query, variables, nonce, and credentials', async () => {
			mockResponse({ json: { data: { ok: true } } });
			await gql('{ foo }', { x: 1 });

			expect(global.fetch).toHaveBeenCalledWith(
				ENDPOINT,
				expect.objectContaining({
					method: 'POST',
					credentials: 'include',
					headers: expect.objectContaining({
						'Content-Type': 'application/json',
						Accept: 'application/json',
						'X-WP-Nonce': 'test-nonce',
					}),
					body: JSON.stringify({ query: '{ foo }', variables: { x: 1 } }),
				})
			);
		});

		it('omits the nonce header when no nonce is configured', async () => {
			window.WPGRAPHQL_IDE_DATA.nonce = '';
			mockResponse({ json: { data: { ok: true } } });
			await gql('{ foo }');

			const headers = global.fetch.mock.calls[0][1].headers;
			expect(headers['X-WP-Nonce']).toBeUndefined();
		});

		it('forwards an AbortSignal to fetch', async () => {
			const controller = new AbortController();
			mockResponse({ json: { data: {} } });
			await gql('{ foo }', {}, { signal: controller.signal });

			expect(global.fetch).toHaveBeenCalledWith(
				ENDPOINT,
				expect.objectContaining({ signal: controller.signal })
			);
		});
	});

	describe('error paths', () => {
		it('throws GraphQLClientError when the endpoint is missing', async () => {
			delete window.WPGRAPHQL_IDE_DATA.graphqlEndpoint;
			await expect(gql('{ foo }')).rejects.toBeInstanceOf(
				GraphQLClientError
			);
		});

		it('throws with auth-distinct message and status on 401', async () => {
			mockResponse({ ok: false, status: 401 });
			try {
				await gql('{ foo }');
				throw new Error('expected gql to throw');
			} catch (e) {
				expect(e).toBeInstanceOf(GraphQLClientError);
				expect(e.status).toBe(401);
				expect(e.message).toMatch(/Authentication required/);
			}
		});

		it('throws with auth-distinct message and status on 403', async () => {
			mockResponse({ ok: false, status: 403 });
			try {
				await gql('{ foo }');
				throw new Error('expected gql to throw');
			} catch (e) {
				expect(e.status).toBe(403);
				expect(e.message).toMatch(/Authentication required/);
			}
		});

		it('throws with the HTTP status preserved on other failures', async () => {
			mockResponse({ ok: false, status: 500 });
			try {
				await gql('{ foo }');
				throw new Error('expected gql to throw');
			} catch (e) {
				expect(e.status).toBe(500);
				expect(e.message).toMatch(/500/);
			}
		});

		it('preserves the full errors[] array on a GraphQL-level error', async () => {
			mockResponse({
				json: {
					errors: [
						{ message: 'first error' },
						{ message: 'second error' },
					],
				},
			});
			try {
				await gql('{ foo }');
				throw new Error('expected gql to throw');
			} catch (e) {
				expect(e.errors).toHaveLength(2);
				expect(e.errors[1].message).toBe('second error');
				// `message` mirrors the first error for single-string consumers.
				expect(e.message).toBe('first error');
			}
		});

		it('throws when the response body is invalid JSON', async () => {
			global.fetch.mockResolvedValueOnce({
				ok: true,
				status: 200,
				json: () => Promise.reject(new Error('parse')),
			});
			await expect(gql('{ foo }')).rejects.toThrow(/parse/i);
		});

		it('does not treat an empty errors array as a failure', async () => {
			mockResponse({ json: { data: { ok: true }, errors: [] } });
			await expect(gql('{ foo }')).resolves.toEqual({ ok: true });
		});
	});

	describe('GraphQLClientError', () => {
		it('defaults status to 0 and errors to []', () => {
			const e = new GraphQLClientError('boom');
			expect(e.status).toBe(0);
			expect(e.errors).toEqual([]);
			expect(e.name).toBe('GraphQLClientError');
		});

		it('is an Error instance', () => {
			const e = new GraphQLClientError('boom');
			expect(e).toBeInstanceOf(Error);
		});
	});
});
