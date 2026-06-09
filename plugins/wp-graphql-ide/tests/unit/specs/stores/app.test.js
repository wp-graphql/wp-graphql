import reducer from '../../../../src/stores/app/app-store-reducer';
import selectors from '../../../../src/stores/app/app-store-selectors';

const seed = (overrides = {}) => ({
	isDrawerOpen: false,
	shouldRenderStandalone: false,
	isInitialStateLoaded: false,
	query: null,
	schema: undefined,
	isAuthenticated: true,
	variables: '',
	headers: '',
	response: '',
	responseHeaders: null,
	responseStatus: null,
	responseDuration: null,
	responseSize: null,
	isFetching: false,
	history: [],
	httpMethod: 'POST',
	docsNavTarget: null,
	cursorOffset: null,
	collections: [],
	activeCollection: null,
	collectionSortModes: {},
	personalCollections: [],
	sharedCollections: [],
	...overrides,
});

describe('app reducer', () => {
	describe('SET_QUERY', () => {
		it('updates the query', () => {
			const after = reducer(seed(), {
				type: 'SET_QUERY',
				query: '{ posts { id } }',
			});
			expect(after.query).toBe('{ posts { id } }');
		});

		it('is a no-op when the query is unchanged (referential stability)', () => {
			const before = seed({ query: '{ x }' });
			const after = reducer(before, {
				type: 'SET_QUERY',
				query: '{ x }',
			});
			expect(after).toBe(before);
		});
	});

	describe('SET_DRAWER_OPEN', () => {
		it('sets the drawer open flag', () => {
			expect(
				reducer(seed(), { type: 'SET_DRAWER_OPEN', isDrawerOpen: true })
					.isDrawerOpen
			).toBe(true);
			expect(
				reducer(seed({ isDrawerOpen: true }), {
					type: 'SET_DRAWER_OPEN',
					isDrawerOpen: false,
				}).isDrawerOpen
			).toBe(false);
		});
	});

	describe('SET_VARIABLES / SET_HEADERS', () => {
		it('replaces the JSON blobs', () => {
			const a = reducer(seed(), {
				type: 'SET_VARIABLES',
				variables: '{"x":1}',
			});
			expect(a.variables).toBe('{"x":1}');

			const b = reducer(seed(), {
				type: 'SET_HEADERS',
				headers: '{"X-T":"1"}',
			});
			expect(b.headers).toBe('{"X-T":"1"}');
		});
	});

	describe('TOGGLE_AUTHENTICATION', () => {
		it('flips the isAuthenticated flag', () => {
			expect(
				reducer(seed({ isAuthenticated: true }), {
					type: 'TOGGLE_AUTHENTICATION',
				}).isAuthenticated
			).toBe(false);
			expect(
				reducer(seed({ isAuthenticated: false }), {
					type: 'TOGGLE_AUTHENTICATION',
				}).isAuthenticated
			).toBe(true);
		});
	});

	describe('SET_RESPONSE_META / SET_IS_FETCHING', () => {
		it('records execution metadata', () => {
			const after = reducer(seed(), {
				type: 'SET_RESPONSE_META',
				meta: { status: 200, duration: 142, size: 1024 },
			});
			expect(after.responseStatus).toBe(200);
			expect(after.responseDuration).toBe(142);
			expect(after.responseSize).toBe(1024);
		});

		it('toggles the fetching flag', () => {
			const after = reducer(seed(), {
				type: 'SET_IS_FETCHING',
				isFetching: true,
			});
			expect(after.isFetching).toBe(true);
		});
	});

	describe('history', () => {
		it('SET_HISTORY hydrates the list', () => {
			const after = reducer(seed(), {
				type: 'SET_HISTORY',
				history: [{ id: 1 }, { id: 2 }],
			});
			expect(after.history).toHaveLength(2);
		});

		it('ADD_HISTORY_ENTRY prepends', () => {
			const before = seed({ history: [{ id: 1 }] });
			const after = reducer(before, {
				type: 'ADD_HISTORY_ENTRY',
				entry: { id: 2 },
			});
			expect(after.history[0].id).toBe(2);
			expect(after.history[1].id).toBe(1);
		});

		it('CLEAR_HISTORY empties the list', () => {
			const after = reducer(seed({ history: [{ id: 1 }] }), {
				type: 'CLEAR_HISTORY',
			});
			expect(after.history).toEqual([]);
		});
	});

	describe('collection sort modes', () => {
		it('SET_COLLECTION_SORT_MODE sets one key', () => {
			const after = reducer(seed(), {
				type: 'SET_COLLECTION_SORT_MODE',
				key: '5',
				mode: 'title_asc',
			});
			expect(after.collectionSortModes['5']).toBe('title_asc');
		});

		it('SET_COLLECTION_SORT_MODES replaces the map', () => {
			const after = reducer(seed(), {
				type: 'SET_COLLECTION_SORT_MODES',
				modes: { _unsaved: 'modified_desc' },
			});
			expect(after.collectionSortModes).toEqual({
				_unsaved: 'modified_desc',
			});
		});
	});
});

describe('app selectors (smoke)', () => {
	it('exposes the documented selectors', () => {
		// Don't pin every selector value — just confirm the keys exist on
		// the contract object. wp-data warns at runtime when a known
		// selector is removed, so a missing key here is a regression worth
		// catching at unit-test time.
		const exposed = Object.keys(selectors);
		expect(exposed).toEqual(
			expect.arrayContaining([
				'getQuery',
				'getVariables',
				'getHeaders',
				'getResponse',
				'getResponseHeaders',
				'getCollections',
				'getPersonalCollections',
				'getSharedCollections',
				'isDrawerOpen',
				'isAuthenticated',
				'isInitialStateLoaded',
				'getHistory',
				'getOperationHistory',
			])
		);
	});

	it('getQuery returns the query string', () => {
		expect(selectors.getQuery(seed({ query: '{ x }' }))).toBe('{ x }');
	});

	it('isDrawerOpen reflects state', () => {
		expect(selectors.isDrawerOpen(seed({ isDrawerOpen: true }))).toBe(true);
	});
});

describe('getOperationHistory', () => {
	it('groups entries by operationHash and counts runs', () => {
		const state = seed({
			history: [
				{
					id: 3,
					timestamp: 300,
					operationHash: 'a'.repeat(64),
					query: 'latest',
					variables: 'vlatest',
					headers: 'hlatest',
					document_id: 9,
				},
				{
					id: 2,
					timestamp: 200,
					operationHash: 'a'.repeat(64),
					query: 'mid',
					variables: 'vmid',
					headers: 'hmid',
					document_id: 9,
				},
				{
					id: 1,
					timestamp: 100,
					operationHash: 'a'.repeat(64),
					query: 'first',
					variables: '',
					headers: '',
					document_id: 0,
				},
			],
		});
		const groups = selectors.getOperationHistory(state);
		expect(groups).toHaveLength(1);
		expect(groups[0].runCount).toBe(3);
		expect(groups[0].latestRun).toBe(300);
		expect(groups[0].lastQuery).toBe('latest');
		expect(groups[0].lastVariables).toBe('vlatest');
		expect(groups[0].lastHeaders).toBe('hlatest');
		expect(groups[0].latestDocId).toBe(9);
	});

	it('sorts groups newest-first by latestRun', () => {
		const state = seed({
			history: [
				{ id: 1, timestamp: 100, operationHash: 'a'.repeat(64) },
				{ id: 2, timestamp: 300, operationHash: 'b'.repeat(64) },
				{ id: 3, timestamp: 200, operationHash: 'c'.repeat(64) },
			],
		});
		const groups = selectors.getOperationHistory(state);
		expect(groups.map((g) => g.hash)).toEqual([
			'b'.repeat(64),
			'c'.repeat(64),
			'a'.repeat(64),
		]);
	});

	it('falls back to per-id grouping for legacy entries with no operationHash', () => {
		const state = seed({
			history: [
				{ id: 'legacy-1', timestamp: 100, query: '{ a }' },
				{ id: 'legacy-2', timestamp: 200, query: '{ a }' },
			],
		});
		const groups = selectors.getOperationHistory(state);
		// Two legacy entries with the same query but no hash stay
		// separate — we can't safely dedupe without confirming the
		// content normalized to the same identity.
		expect(groups).toHaveLength(2);
		expect(groups.every((g) => g.hash === null)).toBe(true);
	});

	it('returns the same array reference when history is unchanged (memoization)', () => {
		const history = [
			{ id: 1, timestamp: 100, operationHash: 'a'.repeat(64) },
		];
		const state = seed({ history });
		const first = selectors.getOperationHistory(state);
		const second = selectors.getOperationHistory(state);
		expect(first).toBe(second);
	});
});
