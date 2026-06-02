/* eslint-env browser, jest */

// `displayShortcut` from `@wordpress/keycodes` resolves the platform
// each call by reading `navigator.platform`. We swap it per test and
// re-import `shortcut-labels` inside `jest.isolateModules` so the
// module-load-time constants reflect the platform under test.
function withPlatform(platform, fn) {
	const original = Object.getOwnPropertyDescriptor(
		window.navigator,
		'platform'
	);
	Object.defineProperty(window.navigator, 'platform', {
		value: platform,
		configurable: true,
	});
	try {
		jest.isolateModules(fn);
	} finally {
		if (original) {
			Object.defineProperty(window.navigator, 'platform', original);
		} else {
			delete window.navigator.platform;
		}
	}
}

describe('shortcut-labels', () => {
	it('renders mac modifier glyphs on Apple platforms', () => {
		withPlatform('MacIntel', () => {
			const labels = require('../../../../src/utils/shortcut-labels');
			expect(labels.RUN_QUERY_LABEL).toBe('⌘Enter');
			expect(labels.SAVE_LABEL).toBe('⌘S');
			expect(labels.PRETTIFY_LABEL).toBe('⇧⌘P');
			expect(labels.MERGE_LABEL).toBe('⇧⌘M');
		});
	});

	it('renders Ctrl-prefixed text on non-Apple platforms', () => {
		withPlatform('Win32', () => {
			const labels = require('../../../../src/utils/shortcut-labels');
			expect(labels.RUN_QUERY_LABEL).toBe('Ctrl+Enter');
			expect(labels.SAVE_LABEL).toBe('Ctrl+S');
			expect(labels.PRETTIFY_LABEL).toBe('Ctrl+Shift+P');
			expect(labels.MERGE_LABEL).toBe('Ctrl+Shift+M');
		});
	});
});
