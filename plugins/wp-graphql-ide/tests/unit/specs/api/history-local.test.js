import {
	getLocalHistory,
	createLocalHistoryEntry,
	deleteLocalHistoryEntry,
	clearLocalHistory,
} from '../../../../src/api/history-local';

function keyFor(userId, ctx) {
	return `wpgraphql-ide:local-history:v1:user-${userId}:ctx-${ctx}`;
}

function setData({ currentUserId = 0, endpointMode = false } = {}) {
	window.WPGRAPHQL_IDE_DATA = {
		context: { currentUserId },
		endpointMode,
	};
}

function readBucket(userId = 0, ctx = 'endpoint') {
	const raw = window.localStorage.getItem(keyFor(userId, ctx));
	return raw ? JSON.parse(raw) : null;
}

describe('history-local', () => {
	beforeEach(() => {
		window.localStorage.clear();
		delete window.WPGRAPHQL_IDE_DATA;
	});

	describe('storage key scoping', () => {
		it('uses ctx=endpoint and user-0 for an anonymous endpoint visitor', async () => {
			setData({ currentUserId: 0, endpointMode: true });
			await createLocalHistoryEntry({ query: '{ a }' });
			expect(readBucket(0, 'endpoint')).toHaveLength(1);
			expect(readBucket(0, 'admin')).toBeNull();
		});

		it('uses ctx=admin and the WP user id for a signed-in admin', async () => {
			setData({ currentUserId: 7, endpointMode: false });
			await createLocalHistoryEntry({ query: '{ a }' });
			expect(readBucket(7, 'admin')).toHaveLength(1);
			expect(readBucket(0, 'admin')).toBeNull();
		});

		it('keeps two users on the same browser in separate buckets', async () => {
			setData({ currentUserId: 7, endpointMode: false });
			await createLocalHistoryEntry({ query: '{ user-7 }' });

			setData({ currentUserId: 9, endpointMode: false });
			await createLocalHistoryEntry({ query: '{ user-9 }' });

			expect(readBucket(7, 'admin')).toHaveLength(1);
			expect(readBucket(9, 'admin')).toHaveLength(1);
			expect(readBucket(7, 'admin')[0].query).toBe('{ user-7 }');
			expect(readBucket(9, 'admin')[0].query).toBe('{ user-9 }');
		});

		it('keeps the same user separate across admin and endpoint contexts', async () => {
			setData({ currentUserId: 7, endpointMode: false });
			await createLocalHistoryEntry({ query: '{ admin-side }' });

			setData({ currentUserId: 7, endpointMode: true });
			await createLocalHistoryEntry({ query: '{ endpoint-side }' });

			expect(readBucket(7, 'admin')).toHaveLength(1);
			expect(readBucket(7, 'endpoint')).toHaveLength(1);
			expect(readBucket(7, 'admin')[0].query).toBe('{ admin-side }');
			expect(readBucket(7, 'endpoint')[0].query).toBe(
				'{ endpoint-side }'
			);
		});

		it('falls back to user-0 ctx-admin when WPGRAPHQL_IDE_DATA is absent', async () => {
			await createLocalHistoryEntry({ query: '{ a }' });
			expect(readBucket(0, 'admin')).toHaveLength(1);
		});
	});

	describe('getLocalHistory', () => {
		beforeEach(() => setData({ currentUserId: 0, endpointMode: true }));

		it('returns an empty array when nothing is stored', async () => {
			await expect(getLocalHistory()).resolves.toEqual([]);
		});

		it('returns stored entries in the order they are written', async () => {
			window.localStorage.setItem(
				keyFor(0, 'endpoint'),
				JSON.stringify([
					{ id: 'a', query: '{ a }' },
					{ id: 'b', query: '{ b }' },
				])
			);
			const result = await getLocalHistory();
			expect(result.map((e) => e.id)).toEqual(['a', 'b']);
		});

		it('treats malformed JSON as empty', async () => {
			window.localStorage.setItem(keyFor(0, 'endpoint'), 'not-json-{');
			await expect(getLocalHistory()).resolves.toEqual([]);
		});

		it('treats a non-array payload as empty', async () => {
			window.localStorage.setItem(
				keyFor(0, 'endpoint'),
				JSON.stringify({ x: 1 })
			);
			await expect(getLocalHistory()).resolves.toEqual([]);
		});
	});

	describe('createLocalHistoryEntry', () => {
		beforeEach(() => setData({ currentUserId: 0, endpointMode: true }));

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

			const bucket = readBucket(0, 'endpoint');
			expect(bucket).toHaveLength(1);
			expect(bucket[0].id).toBe(adapted.id);
		});

		it('puts the newest entry at the front', async () => {
			const first = await createLocalHistoryEntry({ query: '{ a }' });
			const second = await createLocalHistoryEntry({ query: '{ b }' });
			const bucket = readBucket(0, 'endpoint');
			expect(bucket[0].id).toBe(second.id);
			expect(bucket[1].id).toBe(first.id);
		});

		it('caps the bucket at 50 entries (oldest fall off the tail)', async () => {
			for (let i = 0; i < 60; i += 1) {
				// eslint-disable-next-line no-await-in-loop
				await createLocalHistoryEntry({ query: `{ q${i} }` });
			}
			const bucket = readBucket(0, 'endpoint');
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
			const adapted = await createLocalHistoryEntry({
				query: '{ x }',
				is_authenticated: true,
			});
			expect(adapted.is_authenticated).toBe(true);
		});
	});

	describe('deleteLocalHistoryEntry', () => {
		beforeEach(() => setData({ currentUserId: 0, endpointMode: true }));

		it('removes the entry with the matching id and returns the deleted id', async () => {
			const first = await createLocalHistoryEntry({ query: '{ a }' });
			const second = await createLocalHistoryEntry({ query: '{ b }' });

			const result = await deleteLocalHistoryEntry(first.id);
			expect(result).toEqual({ deletedId: first.id });

			const bucket = readBucket(0, 'endpoint');
			expect(bucket).toHaveLength(1);
			expect(bucket[0].id).toBe(second.id);
		});

		it('is a no-op for unknown ids (still returns the requested id)', async () => {
			await createLocalHistoryEntry({ query: '{ a }' });
			const result = await deleteLocalHistoryEntry(
				'local-does-not-exist'
			);
			expect(result).toEqual({ deletedId: 'local-does-not-exist' });
			expect(readBucket(0, 'endpoint')).toHaveLength(1);
		});
	});

	describe('clearLocalHistory', () => {
		beforeEach(() => setData({ currentUserId: 0, endpointMode: true }));

		it('wipes the bucket', async () => {
			await createLocalHistoryEntry({ query: '{ a }' });
			await createLocalHistoryEntry({ query: '{ b }' });
			await clearLocalHistory();
			expect(readBucket(0, 'endpoint')).toEqual([]);
		});

		it('only wipes the current bucket, not other users', async () => {
			setData({ currentUserId: 7, endpointMode: false });
			await createLocalHistoryEntry({ query: '{ keep-me }' });

			setData({ currentUserId: 0, endpointMode: true });
			await createLocalHistoryEntry({ query: '{ clear-me }' });
			await clearLocalHistory();

			expect(readBucket(0, 'endpoint')).toEqual([]);
			expect(readBucket(7, 'admin')).toHaveLength(1);
		});

		it('ignores the ids parameter (signature parity with older callers)', async () => {
			await createLocalHistoryEntry({ query: '{ a }' });
			await clearLocalHistory(['some', 'other', 'ids']);
			expect(readBucket(0, 'endpoint')).toEqual([]);
		});
	});
});
