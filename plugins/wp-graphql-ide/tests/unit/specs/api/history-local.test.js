import {
	getLocalHistory,
	createLocalHistoryEntry,
	deleteLocalHistoryEntry,
	clearLocalHistory,
} from '../../../../src/api/history-local';

const STORAGE_KEY = 'wpgraphql-ide:local-history:v1';

function readBucket() {
	const raw = window.localStorage.getItem(STORAGE_KEY);
	return raw ? JSON.parse(raw) : null;
}

describe('history-local', () => {
	beforeEach(() => {
		window.localStorage.clear();
	});

	describe('getLocalHistory', () => {
		it('returns an empty array when nothing is stored', async () => {
			await expect(getLocalHistory()).resolves.toEqual([]);
		});

		it('returns stored entries newest-first as written', async () => {
			window.localStorage.setItem(
				STORAGE_KEY,
				JSON.stringify([
					{ id: 'a', query: '{ a }' },
					{ id: 'b', query: '{ b }' },
				])
			);
			const result = await getLocalHistory();
			expect(result.map((e) => e.id)).toEqual(['a', 'b']);
		});

		it('treats malformed JSON as empty', async () => {
			window.localStorage.setItem(STORAGE_KEY, 'not-json-{');
			await expect(getLocalHistory()).resolves.toEqual([]);
		});

		it('treats a non-array payload as empty', async () => {
			window.localStorage.setItem(STORAGE_KEY, JSON.stringify({ x: 1 }));
			await expect(getLocalHistory()).resolves.toEqual([]);
		});
	});

	describe('createLocalHistoryEntry', () => {
		it('prepends an entry and returns the adapted shape', async () => {
			const adapted = await createLocalHistoryEntry({
				query: '{ posts { id } }',
				variables: '{}',
				headers: '{"x":1}',
				duration_ms: 42,
				status: 'success',
				document_id: 0,
				is_authenticated: false,
				http_method: 'POST',
			});

			expect(adapted.id).toMatch(/^local-\d+-[a-z0-9]+$/);
			expect(adapted.globalId).toBeNull();
			expect(adapted.query).toBe('{ posts { id } }');
			expect(adapted.is_authenticated).toBe(false);
			expect(typeof adapted.timestamp).toBe('number');

			const bucket = readBucket();
			expect(bucket).toHaveLength(1);
			expect(bucket[0].id).toBe(adapted.id);
		});

		it('puts the newest entry at the front', async () => {
			const first = await createLocalHistoryEntry({ query: '{ a }' });
			const second = await createLocalHistoryEntry({ query: '{ b }' });
			const bucket = readBucket();
			expect(bucket[0].id).toBe(second.id);
			expect(bucket[1].id).toBe(first.id);
		});

		it('caps the bucket at 50 entries (oldest fall off the tail)', async () => {
			for (let i = 0; i < 60; i += 1) {
				// eslint-disable-next-line no-await-in-loop
				await createLocalHistoryEntry({ query: `{ q${i} }` });
			}
			const bucket = readBucket();
			expect(bucket).toHaveLength(50);
			// Newest is q59, oldest kept is q10.
			expect(bucket[0].query).toBe('{ q59 }');
			expect(bucket[49].query).toBe('{ q10 }');
		});

		it('applies safe defaults for missing fields', async () => {
			const adapted = await createLocalHistoryEntry({ query: '{ x }' });
			expect(adapted.variables).toBe('');
			expect(adapted.headers).toBe('');
			expect(adapted.duration_ms).toBe(0);
			expect(adapted.status).toBe('');
			expect(adapted.document_id).toBe(0);
			expect(adapted.http_method).toBe('POST');
			expect(adapted.is_authenticated).toBe(false);
		});

		it('coerces a non-numeric document_id to 0', async () => {
			const adapted = await createLocalHistoryEntry({
				query: '{ x }',
				document_id: 'temp-abc',
			});
			expect(adapted.document_id).toBe(0);
		});

		it('preserves is_authenticated when the caller passes true', async () => {
			// Anonymous public-endpoint visitors are the typical case, but
			// the server-mirroring shape lets a caller force-record auth
			// state if needed (e.g. an extension that knows the visitor
			// just signed in mid-session).
			const adapted = await createLocalHistoryEntry({
				query: '{ x }',
				is_authenticated: true,
			});
			expect(adapted.is_authenticated).toBe(true);
		});
	});

	describe('deleteLocalHistoryEntry', () => {
		it('removes the entry with the matching id and returns the deleted id', async () => {
			const first = await createLocalHistoryEntry({ query: '{ a }' });
			const second = await createLocalHistoryEntry({ query: '{ b }' });

			const result = await deleteLocalHistoryEntry(first.id);
			expect(result).toEqual({ deletedId: first.id });

			const bucket = readBucket();
			expect(bucket).toHaveLength(1);
			expect(bucket[0].id).toBe(second.id);
		});

		it('is a no-op for unknown ids (still returns the requested id)', async () => {
			await createLocalHistoryEntry({ query: '{ a }' });
			const result = await deleteLocalHistoryEntry(
				'local-does-not-exist'
			);
			expect(result).toEqual({ deletedId: 'local-does-not-exist' });
			expect(readBucket()).toHaveLength(1);
		});
	});

	describe('clearLocalHistory', () => {
		it('wipes the bucket', async () => {
			await createLocalHistoryEntry({ query: '{ a }' });
			await createLocalHistoryEntry({ query: '{ b }' });
			await clearLocalHistory();
			expect(readBucket()).toEqual([]);
		});

		it('ignores the ids parameter (signature parity with the server backend)', async () => {
			await createLocalHistoryEntry({ query: '{ a }' });
			await clearLocalHistory(['some', 'other', 'ids']);
			expect(readBucket()).toEqual([]);
		});
	});
});
