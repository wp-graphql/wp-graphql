import reducer from '../../../../src/stores/activity-bar/activity-bar-reducer';
import selectors from '../../../../src/stores/activity-bar/activity-bar-selectors';

// We don't import the store index because that triggers
// `register_store('wpgraphql-ide/activity-bar')` which is a side
// effect we don't want in unit tests. Reducer + selectors can be
// exercised against plain state.

describe('activity-bar reducer', () => {
	beforeEach(() => {
		window.localStorage.clear();
	});

	it('starts with no visible panel by default', () => {
		const state = reducer(undefined, { type: '@@INIT' });
		expect(state.visiblePanel).toBeNull();
		expect(state.activityPanels).toEqual({});
	});

	it('registers a panel under its name', () => {
		const state = reducer(
			{ activityPanels: {}, visiblePanel: null },
			{
				type: 'REGISTER_PANEL',
				name: 'docs',
				config: {
					title: 'Docs',
					content: () => null,
					icon: () => null,
				},
			}
		);

		expect(state.activityPanels.docs).toEqual(
			expect.objectContaining({ title: 'Docs', priority: 10 })
		);
	});

	it('rejects panels with duplicate names', () => {
		const before = {
			activityPanels: {
				docs: { title: 'first' },
			},
			visiblePanel: null,
		};

		const after = reducer(before, {
			type: 'REGISTER_PANEL',
			name: 'docs',
			config: { title: 'second', content: () => null },
		});

		// Duplicate registration should be a no-op.
		expect(after.activityPanels.docs.title).toBe('first');
	});

	it('rejects panels missing a content callback', () => {
		const state = reducer(
			{ activityPanels: {}, visiblePanel: null },
			{
				type: 'REGISTER_PANEL',
				name: 'broken',
				config: { title: 'Broken' },
			}
		);
		expect(state.activityPanels.broken).toBeUndefined();
	});

	it('toggles the visible panel between the named panel and null', () => {
		const seed = { activityPanels: {}, visiblePanel: null };

		const opened = reducer(seed, {
			type: 'TOGGLE_ACTIVITY_PANEL_VISIBILITY',
			panel: 'docs',
		});
		expect(opened.visiblePanel).toBe('docs');

		const closed = reducer(opened, {
			type: 'TOGGLE_ACTIVITY_PANEL_VISIBILITY',
			panel: 'docs',
		});
		expect(closed.visiblePanel).toBeNull();
	});

	it('SET_VISIBLE_PANEL replaces the visible panel', () => {
		const next = reducer(
			{ activityPanels: {}, visiblePanel: 'docs' },
			{ type: 'SET_VISIBLE_PANEL', panel: 'history' }
		);
		expect(next.visiblePanel).toBe('history');
	});
});

describe('activity-bar selectors', () => {
	it('visiblePanel returns the active panel descriptor', () => {
		const state = {
			activityPanels: {
				docs: { title: 'Docs', priority: 10, content: () => null },
			},
			visiblePanel: 'docs',
		};
		expect(selectors.visiblePanel(state)).toEqual(
			expect.objectContaining({ name: 'docs', title: 'Docs' })
		);
	});

	it('visiblePanel returns null when nothing is visible', () => {
		const state = { activityPanels: {}, visiblePanel: null };
		expect(selectors.visiblePanel(state)).toBeNull();
	});

	it('activityPanels returns panels sorted by priority', () => {
		const state = {
			activityPanels: {
				a: { title: 'A', priority: 30, content: () => null },
				b: { title: 'B', priority: 10, content: () => null },
				c: { title: 'C', priority: 20, content: () => null },
			},
			visiblePanel: null,
		};

		const panels = selectors.activityPanels(state);
		expect(panels.map((p) => p.name)).toEqual(['b', 'c', 'a']);
	});
});
