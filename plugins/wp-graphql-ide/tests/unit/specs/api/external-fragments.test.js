import {
	parseFragmentName,
	injectExternalFragments,
	registerExternalFragmentInjector,
} from '../../../../src/api/external-fragments';

describe('parseFragmentName', () => {
	it('returns the fragment name for a valid fragment definition', () => {
		expect(
			parseFragmentName('fragment PostFields on Post { id title }')
		).toBe('PostFields');
	});

	it('returns null for non-fragment definitions', () => {
		expect(parseFragmentName('query GetPosts { posts { id } }')).toBeNull();
		expect(parseFragmentName('mutation X { foo }')).toBeNull();
	});

	it('returns null for unparseable input', () => {
		expect(parseFragmentName('fragment {')).toBeNull();
		expect(parseFragmentName('not graphql at all')).toBeNull();
	});

	it('returns null for empty / non-string input', () => {
		expect(parseFragmentName('')).toBeNull();
		expect(parseFragmentName('   ')).toBeNull();
		expect(parseFragmentName(null)).toBeNull();
		expect(parseFragmentName(undefined)).toBeNull();
	});
});

describe('injectExternalFragments', () => {
	const POST_FIELDS = 'fragment PostFields on Post { id title }';
	const USER_FIELDS = 'fragment UserFields on User { id name }';
	const POST_WITH_AUTHOR =
		'fragment PostWithAuthor on Post { ...PostFields author { ...UserFields } }';

	it('returns request unchanged when fragments array is empty', () => {
		const request = { query: '{ posts { ...PostFields } }' };
		expect(injectExternalFragments(request, [])).toBe(request);
	});

	it('returns request unchanged when query is empty', () => {
		const request = { query: '' };
		expect(injectExternalFragments(request, [POST_FIELDS])).toBe(request);
	});

	it('returns request unchanged when no spread references an external fragment', () => {
		const request = { query: '{ posts { nodes { id } } }' };
		expect(injectExternalFragments(request, [POST_FIELDS])).toBe(request);
	});

	it('prepends a referenced external fragment to the query', () => {
		const request = {
			query: 'query GetPosts { posts { nodes { ...PostFields } } }',
		};
		const result = injectExternalFragments(request, [POST_FIELDS]);
		expect(result).not.toBe(request);
		expect(result.query).toContain(POST_FIELDS);
		expect(result.query).toContain('query GetPosts');
		expect(result.query.indexOf(POST_FIELDS)).toBeLessThan(
			result.query.indexOf('query GetPosts')
		);
	});

	it('does not inject a fragment already defined in the query', () => {
		const inlineFragment = 'fragment PostFields on Post { id }';
		const request = {
			query: `${inlineFragment}\nquery Q { posts { nodes { ...PostFields } } }`,
		};
		const result = injectExternalFragments(request, [POST_FIELDS]);
		expect(result).toBe(request);
	});

	it('injects only referenced external fragments, not the rest', () => {
		const request = {
			query: 'query Q { posts { nodes { ...PostFields } } }',
		};
		const result = injectExternalFragments(request, [
			POST_FIELDS,
			USER_FIELDS,
		]);
		expect(result.query).toContain(POST_FIELDS);
		expect(result.query).not.toContain(USER_FIELDS);
	});

	it('resolves transitive references between external fragments', () => {
		// Query references PostWithAuthor, which references PostFields and UserFields.
		const request = {
			query: 'query Q { posts { nodes { ...PostWithAuthor } } }',
		};
		const result = injectExternalFragments(request, [
			POST_FIELDS,
			USER_FIELDS,
			POST_WITH_AUTHOR,
		]);
		expect(result.query).toContain(POST_WITH_AUTHOR);
		expect(result.query).toContain(POST_FIELDS);
		expect(result.query).toContain(USER_FIELDS);
	});

	it('returns request unchanged for unparseable queries', () => {
		const request = { query: '{ posts { ...PostFields' };
		expect(injectExternalFragments(request, [POST_FIELDS])).toBe(request);
	});

	it('ignores external entries that are not fragment definitions', () => {
		const request = {
			query: 'query Q { posts { nodes { ...PostFields } } }',
		};
		const result = injectExternalFragments(request, [
			'query NotAFragment { x }',
			'invalid graphql',
			POST_FIELDS,
		]);
		expect(result.query).toContain(POST_FIELDS);
	});

	it('preserves other request fields unchanged', () => {
		const request = {
			query: 'query Q { posts { nodes { ...PostFields } } }',
			variables: { first: 10 },
			operationName: 'Q',
			headers: { 'X-Foo': 'bar' },
			httpMethod: 'POST',
		};
		const result = injectExternalFragments(request, [POST_FIELDS]);
		expect(result.variables).toEqual(request.variables);
		expect(result.operationName).toBe(request.operationName);
		expect(result.headers).toEqual(request.headers);
		expect(result.httpMethod).toBe(request.httpMethod);
	});

	it('deduplicates name collisions across external fragments (first wins)', () => {
		const first = 'fragment PostFields on Post { id }';
		const second = 'fragment PostFields on Post { id title content }';
		const request = {
			query: 'query Q { posts { nodes { ...PostFields } } }',
		};
		const result = injectExternalFragments(request, [first, second]);
		expect(result.query).toContain(first);
		expect(result.query).not.toContain(second);
	});
});

describe('registerExternalFragmentInjector', () => {
	function makeHooks() {
		const filters = new Map();
		return {
			filters,
			addFilter(name, namespace, fn) {
				const list = filters.get(name) || [];
				list.push({ namespace, fn });
				filters.set(name, list);
			},
			removeFilter(name, namespace) {
				const list = filters.get(name) || [];
				filters.set(
					name,
					list.filter((entry) => entry.namespace !== namespace)
				);
			},
			applyFilters(name, value) {
				const list = filters.get(name) || [];
				return list.reduce((acc, entry) => entry.fn(acc), value);
			},
		};
	}

	afterEach(() => {
		delete window.WPGRAPHQL_IDE_DATA;
	});

	it('registers an executeRequest filter consumer', () => {
		const hooks = makeHooks();
		registerExternalFragmentInjector(hooks);
		expect(hooks.filters.get('wpgraphql-ide.executeRequest')).toHaveLength(
			1
		);
	});

	it('is idempotent — re-registering does not stack consumers', () => {
		const hooks = makeHooks();
		registerExternalFragmentInjector(hooks);
		registerExternalFragmentInjector(hooks);
		registerExternalFragmentInjector(hooks);
		expect(hooks.filters.get('wpgraphql-ide.executeRequest')).toHaveLength(
			1
		);
	});

	it('reads externalFragments from window.WPGRAPHQL_IDE_DATA at filter time', () => {
		const hooks = makeHooks();
		registerExternalFragmentInjector(hooks);

		// Empty initially — no injection.
		window.WPGRAPHQL_IDE_DATA = { context: { externalFragments: [] } };
		let result = hooks.applyFilters('wpgraphql-ide.executeRequest', {
			query: 'query Q { posts { nodes { ...PostFields } } }',
		});
		expect(result.query).not.toContain('fragment PostFields');

		// Later, fragments are populated — same registered filter now injects.
		window.WPGRAPHQL_IDE_DATA = {
			context: {
				externalFragments: ['fragment PostFields on Post { id title }'],
			},
		};
		result = hooks.applyFilters('wpgraphql-ide.executeRequest', {
			query: 'query Q { posts { nodes { ...PostFields } } }',
		});
		expect(result.query).toContain('fragment PostFields on Post');
	});
});
