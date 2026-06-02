import { migrateLegacyHistory } from '../../../../src/api/legacy-graphiql-history-migration';
import { createHistoryEntry } from '../../../../src/api/history';

jest.mock('../../../../src/api/history', () => ({
	createHistoryEntry: jest.fn(),
}));

const LEGACY_KEY = 'graphiql:queries';
const FLAG_KEY = 'wpgraphql-ide:graphiql-history-migrated:v1';

describe('migrateLegacyHistory', () => {
	beforeEach(() => {
		window.localStorage.clear();
		createHistoryEntry.mockReset();
		createHistoryEntry.mockResolvedValue({ id: 1 });
	});

	it('runs for anonymous visitors too and hands entries to createHistoryEntry', async () => {
		// Asserts the migrator does not short-circuit on anonymous /
		// endpoint-mode visitors — every visitor gets to keep their 4.x
		// history when they upgrade.
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify([{ query: '{ posts { id } }' }])
		);

		const result = await migrateLegacyHistory();
		expect(result.migrated).toBe(true);
		expect(createHistoryEntry).toHaveBeenCalledTimes(1);
		expect(window.localStorage.getItem(LEGACY_KEY)).toBeNull();
		expect(window.localStorage.getItem(FLAG_KEY)).toBe('1');
	});

	it('omits is_authenticated so the local backend default applies', async () => {
		// The local backend defaults `is_authenticated` to false; the
		// migrator stays neutral so a signed-in admin who later runs a
		// new query records the accurate auth state at that point.
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify([{ query: '{ posts { id } }' }])
		);

		await migrateLegacyHistory();
		const payload = createHistoryEntry.mock.calls[0][0];
		expect(payload).not.toHaveProperty('is_authenticated');
	});

	it('marks complete and skips when no legacy key is present', async () => {
		const result = await migrateLegacyHistory();
		expect(result).toEqual({
			migrated: false,
			skipped: 'no-legacy-key',
		});
		expect(window.localStorage.getItem(FLAG_KEY)).toBe('1');
		expect(createHistoryEntry).not.toHaveBeenCalled();
	});

	it('short-circuits when the migration flag is already set', async () => {
		window.localStorage.setItem(FLAG_KEY, '1');
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify([{ query: '{ posts { id } }' }])
		);

		const result = await migrateLegacyHistory();
		expect(result).toEqual({ migrated: false, skipped: 'flag' });
		expect(window.localStorage.getItem(LEGACY_KEY)).not.toBeNull();
		expect(createHistoryEntry).not.toHaveBeenCalled();
	});

	it('cleans up a malformed JSON payload', async () => {
		window.localStorage.setItem(LEGACY_KEY, 'not-json-{');

		const result = await migrateLegacyHistory();
		expect(result).toEqual({ migrated: false, skipped: 'parse-error' });
		expect(window.localStorage.getItem(LEGACY_KEY)).toBeNull();
		expect(window.localStorage.getItem(FLAG_KEY)).toBe('1');
		expect(createHistoryEntry).not.toHaveBeenCalled();
	});

	it('cleans up an empty legacy array without server calls', async () => {
		window.localStorage.setItem(LEGACY_KEY, JSON.stringify([]));

		const result = await migrateLegacyHistory();
		expect(result).toEqual({ migrated: false, skipped: 'empty' });
		expect(window.localStorage.getItem(LEGACY_KEY)).toBeNull();
		expect(window.localStorage.getItem(FLAG_KEY)).toBe('1');
		expect(createHistoryEntry).not.toHaveBeenCalled();
	});

	it('skips entries without a usable query', async () => {
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify([
				{ query: '' },
				{ query: '   ' },
				{ query: null },
				{ notAQuery: true },
				null,
			])
		);

		const result = await migrateLegacyHistory();
		expect(result).toEqual({ migrated: false, skipped: 'empty' });
		expect(createHistoryEntry).not.toHaveBeenCalled();
	});

	it('migrates legacy entries into the local bucket with snake_case shape', async () => {
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify([
				{
					query: '{ posts { id } }',
					variables: '{"first": 5}',
					headers: '{"x":1}',
					operationName: 'GetPosts',
				},
				{ query: 'query Q { users { id } }' },
			])
		);

		const result = await migrateLegacyHistory();
		expect(result.migrated).toBe(true);
		expect(result.attempted).toBe(2);
		expect(result.succeeded).toBe(2);

		expect(createHistoryEntry).toHaveBeenCalledTimes(2);
		expect(createHistoryEntry).toHaveBeenNthCalledWith(1, {
			query: '{ posts { id } }',
			variables: '{"first": 5}',
			headers: '{"x":1}',
			duration_ms: 0,
			status: '',
			document_id: 0,
			http_method: 'POST',
		});
		expect(createHistoryEntry).toHaveBeenNthCalledWith(2, {
			query: 'query Q { users { id } }',
			variables: '',
			headers: '',
			duration_ms: 0,
			status: '',
			document_id: 0,
			http_method: 'POST',
		});

		expect(window.localStorage.getItem(LEGACY_KEY)).toBeNull();
		expect(window.localStorage.getItem(FLAG_KEY)).toBe('1');
	});

	it('caps migration at 100 entries (silently drops older overflow)', async () => {
		const oversized = Array.from({ length: 150 }, (_, i) => ({
			query: `{ q${i} }`,
		}));
		window.localStorage.setItem(LEGACY_KEY, JSON.stringify(oversized));

		const result = await migrateLegacyHistory();
		expect(result.migrated).toBe(true);
		expect(result.attempted).toBe(100);
		expect(createHistoryEntry).toHaveBeenCalledTimes(100);
	});

	it('keeps going after per-entry failures and reports succeeded count', async () => {
		createHistoryEntry
			.mockResolvedValueOnce({ id: 1 })
			.mockRejectedValueOnce(new Error('server 500'))
			.mockResolvedValueOnce({ id: 3 });

		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify([
				{ query: '{ a }' },
				{ query: '{ b }' },
				{ query: '{ c }' },
			])
		);

		const result = await migrateLegacyHistory();
		expect(result.migrated).toBe(true);
		expect(result.attempted).toBe(3);
		expect(result.succeeded).toBe(2);

		// Still marked complete — we don't want to retry and create
		// duplicates of the entries that did succeed.
		expect(window.localStorage.getItem(LEGACY_KEY)).toBeNull();
		expect(window.localStorage.getItem(FLAG_KEY)).toBe('1');
	});

	it('reports migrated: false when every entry fails, but still marks complete', async () => {
		createHistoryEntry.mockRejectedValue(new Error('offline'));
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify([{ query: '{ a }' }, { query: '{ b }' }])
		);

		const result = await migrateLegacyHistory();
		expect(result.migrated).toBe(false);
		expect(result.attempted).toBe(2);
		expect(result.succeeded).toBe(0);
		// Critical: marked complete despite total failure — repeating
		// would re-post any entries that succeed on a partial retry and
		// create duplicates next time.
		expect(window.localStorage.getItem(FLAG_KEY)).toBe('1');
	});

	it('is idempotent — a second call after migration is a no-op', async () => {
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify([{ query: '{ a }' }])
		);

		await migrateLegacyHistory();
		expect(createHistoryEntry).toHaveBeenCalledTimes(1);

		// Simulate a stray re-write of the legacy key after migration.
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify([{ query: '{ b }' }])
		);

		const second = await migrateLegacyHistory();
		expect(second).toEqual({ migrated: false, skipped: 'flag' });
		expect(createHistoryEntry).toHaveBeenCalledTimes(1);
	});
});
