import reducer from '../../../../src/stores/document-editor/document-editor-store-reducer';
import selectors from '../../../../src/stores/document-editor/document-editor-store-selectors';

const seed = (overrides = {}) => ({
	buttons: {},
	documents: {},
	documentIds: [],
	openTabs: [],
	activeTab: null,
	tabTypes: {},
	topbarActions: {},
	...overrides,
});

describe('document-editor reducer', () => {
	it('returns the initial state by default', () => {
		const state = reducer(undefined, { type: '@@INIT' });
		expect(state).toEqual(
			expect.objectContaining({
				documents: {},
				documentIds: [],
				openTabs: [],
				activeTab: null,
			})
		);
	});

	describe('SET_DOCUMENTS', () => {
		it('hydrates documents and preserves order via documentIds', () => {
			const state = reducer(seed(), {
				type: 'SET_DOCUMENTS',
				documents: [
					{ id: 5, title: 'Five' },
					{ id: 2, title: 'Two' },
					{ id: 9, title: 'Nine' },
				],
			});

			expect(state.documentIds).toEqual(['5', '2', '9']);
			expect(state.documents['5'].title).toBe('Five');
		});
	});

	describe('CREATE_IN_MEMORY_TAB', () => {
		it('adds a temp doc, opens its tab, and makes it active', () => {
			const state = reducer(seed(), {
				type: 'CREATE_IN_MEMORY_TAB',
				tempId: 'temp-1',
				doc: { id: 'temp-1', title: 'New' },
			});

			expect(state.documents['temp-1']).toEqual(
				expect.objectContaining({ id: 'temp-1' })
			);
			expect(state.documentIds).toContain('temp-1');
			expect(state.openTabs).toContainEqual({
				id: 'temp-1',
				type: 'query-editor',
			});
			expect(state.activeTab).toBe('temp-1');
		});
	});

	describe('UPDATE_DOCUMENT_ID', () => {
		it('rewrites doc, ids list, open tabs, and active tab when a temp id becomes a real id', () => {
			const before = seed({
				documents: { 'temp-1': { id: 'temp-1' } },
				documentIds: ['temp-1'],
				openTabs: [{ id: 'temp-1', type: 'query-editor' }],
				activeTab: 'temp-1',
			});

			const after = reducer(before, {
				type: 'UPDATE_DOCUMENT_ID',
				oldId: 'temp-1',
				newId: 42,
				document: { id: 42, title: 'Saved' },
			});

			expect(after.documents['temp-1']).toBeUndefined();
			expect(after.documents['42']).toEqual({ id: 42, title: 'Saved' });
			expect(after.documentIds).toEqual(['42']);
			expect(after.openTabs[0].id).toBe('42');
			expect(after.activeTab).toBe('42');
		});
	});

	describe('UPDATE_DOCUMENT', () => {
		it('merges an update into an existing doc', () => {
			const before = seed({
				documents: { '1': { id: 1, title: 'old', query: 'x' } },
				documentIds: ['1'],
			});

			const after = reducer(before, {
				type: 'UPDATE_DOCUMENT',
				document: { id: 1, title: 'new' },
			});

			expect(after.documents['1']).toEqual({
				id: 1,
				title: 'new',
				query: 'x',
			});
		});

		it('inserts virtual workspace docs into documentIds', () => {
			const before = seed();
			const after = reducer(before, {
				type: 'UPDATE_DOCUMENT',
				document: { id: 'settings', tabType: 'settings' },
			});
			expect(after.documentIds).toContain('settings');
		});
	});

	describe('REMOVE_DOCUMENT', () => {
		it('removes the doc and drops it from documentIds', () => {
			const before = seed({
				documents: { '1': { id: 1 }, '2': { id: 2 } },
				documentIds: ['1', '2'],
			});
			const after = reducer(before, { type: 'REMOVE_DOCUMENT', id: 1 });
			expect(after.documents['1']).toBeUndefined();
			expect(after.documentIds).toEqual(['2']);
		});
	});

	describe('OPEN_TAB / CLOSE_TAB', () => {
		it('opens a tab once (idempotent)', () => {
			let state = seed();
			state = reducer(state, { type: 'OPEN_TAB', tabId: '1' });
			state = reducer(state, { type: 'OPEN_TAB', tabId: '1' });
			expect(state.openTabs).toHaveLength(1);
		});

		it('closes a tab', () => {
			const before = seed({
				openTabs: [
					{ id: '1', type: 'query-editor' },
					{ id: '2', type: 'query-editor' },
				],
			});
			const after = reducer(before, { type: 'CLOSE_TAB', tabId: '1' });
			expect(after.openTabs.map((t) => t.id)).toEqual(['2']);
		});
	});

	describe('SET_ACTIVE_TAB', () => {
		it('sets the active tab', () => {
			const after = reducer(seed(), {
				type: 'SET_ACTIVE_TAB',
				tabId: 'temp-1',
			});
			expect(after.activeTab).toBe('temp-1');
		});
	});
});

describe('document-editor selectors', () => {
	it('isTempId delegates to the shared util', () => {
		expect(selectors.isTempId({}, 'temp-1')).toBe(true);
		expect(selectors.isTempId({}, 42)).toBe(false);
	});

	it('getDocuments returns docs in documentIds order, missing ids dropped', () => {
		const state = seed({
			documents: { '1': { id: 1 }, '3': { id: 3 } },
			documentIds: ['1', '2', '3'],
		});
		expect(selectors.getDocuments(state)).toEqual([
			{ id: 1 },
			{ id: 3 },
		]);
	});

	it('getActiveTab returns the active tab id', () => {
		expect(selectors.getActiveTab(seed({ activeTab: '7' }))).toBe('7');
	});

	it('getActiveDocument returns the doc for the active tab', () => {
		const state = seed({
			documents: { '7': { id: 7, title: 'X' } },
			activeTab: '7',
		});
		expect(selectors.getActiveDocument(state)).toEqual({
			id: 7,
			title: 'X',
		});
	});

	it('getActiveDocument returns null when there is no active tab', () => {
		expect(selectors.getActiveDocument(seed())).toBeNull();
	});

	it('getOpenTabs returns just the ids', () => {
		const state = seed({
			openTabs: [
				{ id: 'a', type: 'query-editor' },
				{ id: 'b', type: 'settings' },
			],
		});
		expect(selectors.getOpenTabs(state)).toEqual(['a', 'b']);
	});

	it('getActiveTabType returns the type of the open active tab', () => {
		const state = seed({
			openTabs: [{ id: 'x', type: 'settings' }],
			activeTab: 'x',
		});
		expect(selectors.getActiveTabType(state)).toBe('settings');
	});
});
