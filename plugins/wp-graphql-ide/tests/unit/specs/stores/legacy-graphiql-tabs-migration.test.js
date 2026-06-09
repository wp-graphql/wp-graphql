import { migrateLegacyTabs } from '../../../../src/stores/document-editor/legacy-graphiql-tabs-migration';

const LEGACY_KEY = 'graphiql:tabState';
const FLAG_KEY = 'wpgraphql-ide:graphiql-tabstate-migrated:v1';

function setUserContext({ userId = 7 } = {}) {
	window.WPGRAPHQL_IDE_DATA = {
		context: { currentUserId: userId },
	};
}

function unsavedTabsKey({ userId = 7 } = {}) {
	return `wpgraphql-ide:unsaved-tabs:v1:user-${userId}:ctx-admin`;
}

function prefsKey({ userId = 7 } = {}) {
	return `wpgraphql-ide:prefs:v1:user-${userId}:ctx-admin`;
}

function readJSON(key) {
	const raw = window.localStorage.getItem(key);
	return raw ? JSON.parse(raw) : null;
}

describe('migrateLegacyTabs', () => {
	beforeEach(() => {
		window.localStorage.clear();
		setUserContext();
	});

	afterEach(() => {
		delete window.WPGRAPHQL_IDE_DATA;
	});

	it('is a no-op and sets the flag when no legacy key is present', async () => {
		const result = await migrateLegacyTabs();
		expect(result).toEqual({ migrated: false });
		expect(window.localStorage.getItem(FLAG_KEY)).toBe('1');
	});

	it('returns immediately when the migration flag is already set', async () => {
		window.localStorage.setItem(FLAG_KEY, '1');
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify({
				activeTabIndex: 0,
				tabs: [{ query: '{ posts { id } }' }],
			})
		);

		const result = await migrateLegacyTabs();
		expect(result).toEqual({ migrated: false });

		// Legacy key untouched, since we never tried to run migration.
		expect(window.localStorage.getItem(LEGACY_KEY)).not.toBeNull();
		// No tabs created.
		expect(readJSON(unsavedTabsKey())).toBeNull();
	});

	it('cleans up an empty legacy payload without creating tabs', async () => {
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify({ activeTabIndex: 0, tabs: [] })
		);

		const result = await migrateLegacyTabs();
		expect(result).toEqual({ migrated: false });
		expect(window.localStorage.getItem(LEGACY_KEY)).toBeNull();
		expect(window.localStorage.getItem(FLAG_KEY)).toBe('1');
		expect(readJSON(unsavedTabsKey())).toBeNull();
	});

	it('migrates tabs into unsaved-tabs storage and writes open_tabs / active_tab', async () => {
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify({
				activeTabIndex: 1,
				tabs: [
					{
						title: 'First',
						query: '{ posts { id } }',
						variables: '{}',
						headers: '{"x":1}',
					},
					{
						title: 'Second',
						query: '{ users { id } }',
						variables: '',
						headers: '',
					},
				],
			})
		);

		const result = await migrateLegacyTabs();
		expect(result.migrated).toBe(true);
		expect(result.tabCount).toBe(2);
		expect(result.activeTabId).toMatch(/^temp-\d+-1$/);

		const stored = readJSON(unsavedTabsKey());
		expect(stored).toHaveLength(2);
		expect(stored[0]).toMatchObject({
			title: 'First',
			query: '{ posts { id } }',
			variables: '{}',
			headers: '{"x":1}',
		});
		expect(stored[1]).toMatchObject({
			title: 'Second',
			query: '{ users { id } }',
		});

		const prefs = readJSON(prefsKey());
		expect(prefs.open_tabs).toEqual(stored.map((t) => t.id));
		expect(prefs.active_tab).toBe(result.activeTabId);

		// Legacy key removed, flag set.
		expect(window.localStorage.getItem(LEGACY_KEY)).toBeNull();
		expect(window.localStorage.getItem(FLAG_KEY)).toBe('1');
	});

	it('defaults the active tab to the first tab when activeTabIndex is out of range', async () => {
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify({
				activeTabIndex: 99,
				tabs: [
					{ title: 'Only', query: '{ a }' },
					{ title: 'Other', query: '{ b }' },
				],
			})
		);

		const result = await migrateLegacyTabs();
		expect(result.migrated).toBe(true);
		expect(result.activeTabId).toBe(readJSON(prefsKey()).open_tabs[0]);
	});

	it('supplies safe defaults for tabs with missing fields', async () => {
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify({
				activeTabIndex: 0,
				tabs: [{}, { query: 42 }, { title: '   ' }],
			})
		);

		const result = await migrateLegacyTabs();
		expect(result.migrated).toBe(true);
		expect(result.tabCount).toBe(3);

		const stored = readJSON(unsavedTabsKey());
		expect(stored.map((t) => t.title)).toEqual([
			'Migrated tab 1',
			'Migrated tab 2',
			'Migrated tab 3',
		]);
		expect(stored.map((t) => t.query)).toEqual(['', '', '']);
	});

	it('marks complete and drops the legacy key on malformed JSON', async () => {
		window.localStorage.setItem(LEGACY_KEY, 'not-json-{');

		const result = await migrateLegacyTabs();
		expect(result).toEqual({ migrated: false });
		expect(window.localStorage.getItem(LEGACY_KEY)).toBeNull();
		expect(window.localStorage.getItem(FLAG_KEY)).toBe('1');
	});

	it('is idempotent — a second call after a successful migration is a no-op', async () => {
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify({
				activeTabIndex: 0,
				tabs: [{ title: 'A', query: '{ a }' }],
			})
		);

		const first = await migrateLegacyTabs();
		expect(first.migrated).toBe(true);

		// Simulate a stray re-write of the legacy key after migration —
		// the flag should keep us from touching it.
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify({
				activeTabIndex: 0,
				tabs: [{ query: '{ replay }' }],
			})
		);

		const second = await migrateLegacyTabs();
		expect(second).toEqual({ migrated: false });

		// Only the original migrated tab is in storage.
		const stored = readJSON(unsavedTabsKey());
		expect(stored).toHaveLength(1);
		expect(stored[0].query).toBe('{ a }');
	});

	it('scopes storage keys to the current user / context', async () => {
		setUserContext({ userId: 42 });
		window.localStorage.setItem(
			LEGACY_KEY,
			JSON.stringify({
				activeTabIndex: 0,
				tabs: [{ title: 'Scoped', query: '{ x }' }],
			})
		);

		const result = await migrateLegacyTabs();
		expect(result.migrated).toBe(true);

		expect(readJSON(unsavedTabsKey({ userId: 42 }))).toHaveLength(1);
		expect(readJSON(prefsKey({ userId: 42 }))).toMatchObject({
			open_tabs: expect.any(Array),
			active_tab: expect.any(String),
		});
	});
});
