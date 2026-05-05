import reducer from '../../../../src/stores/response-extensions/response-extensions-reducer';
import selectors from '../../../../src/stores/response-extensions/response-extensions-selectors';

const seed = (overrides = {}) => ({
	extensionTabs: {},
	...overrides,
});

describe('response-extensions reducer', () => {
	it('returns the initial state by default', () => {
		const state = reducer(undefined, { type: '@@INIT' });
		expect(state).toEqual({ extensionTabs: {} });
	});

	it('registers a tab keyed by name with default priority', () => {
		const after = reducer(seed(), {
			type: 'REGISTER_EXTENSION_TAB',
			name: 'debug',
			config: { title: 'Debug', content: () => null },
		});
		expect(after.extensionTabs.debug).toEqual(
			expect.objectContaining({ title: 'Debug', priority: 10 })
		);
	});

	it('honors an explicit priority', () => {
		const after = reducer(seed(), {
			type: 'REGISTER_EXTENSION_TAB',
			name: 'debug',
			config: { title: 'Debug', content: () => null },
			priority: 30,
		});
		expect(after.extensionTabs.debug.priority).toBe(30);
	});

	it('rejects duplicate tab names', () => {
		const before = seed({
			extensionTabs: { debug: { title: 'first', priority: 10 } },
		});
		const after = reducer(before, {
			type: 'REGISTER_EXTENSION_TAB',
			name: 'debug',
			config: { title: 'second', content: () => null },
		});
		expect(after.extensionTabs.debug.title).toBe('first');
	});

	it('rejects tabs missing a content callback', () => {
		const after = reducer(seed(), {
			type: 'REGISTER_EXTENSION_TAB',
			name: 'broken',
			config: { title: 'Broken' },
		});
		expect(after.extensionTabs.broken).toBeUndefined();
	});

	it('rejects tabs missing a title', () => {
		const after = reducer(seed(), {
			type: 'REGISTER_EXTENSION_TAB',
			name: 'broken',
			config: { content: () => null },
		});
		expect(after.extensionTabs.broken).toBeUndefined();
	});
});

describe('response-extensions selectors', () => {
	it('extensionTabs returns tabs sorted by priority', () => {
		const state = seed({
			extensionTabs: {
				a: { title: 'A', priority: 30, content: () => null },
				b: { title: 'B', priority: 10, content: () => null },
				c: { title: 'C', priority: 20, content: () => null },
			},
		});
		expect(selectors.extensionTabs(state).map((t) => t.name)).toEqual([
			'b',
			'c',
			'a',
		]);
	});
});
