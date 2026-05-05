import {
	getStorageItem,
	setStorageItem,
	removeStorageItem,
	getStorageJSON,
	setStorageJSON,
} from '../../../../src/utils/storage';

describe('storage helpers', () => {
	beforeEach(() => {
		window.localStorage.clear();
	});

	describe('getStorageItem / setStorageItem', () => {
		it('round-trips values', () => {
			setStorageItem('k', 'v');
			expect(getStorageItem('k')).toBe('v');
		});

		it('returns the default for missing keys', () => {
			expect(getStorageItem('missing', 'fallback')).toBe('fallback');
		});

		it('coerces non-strings to strings on write', () => {
			setStorageItem('num', 42);
			expect(getStorageItem('num')).toBe('42');
		});

		it('survives a thrown localStorage', () => {
			const orig = window.localStorage.getItem;
			window.localStorage.getItem = () => {
				throw new Error('blocked');
			};
			expect(getStorageItem('k', 'safe')).toBe('safe');
			window.localStorage.getItem = orig;
		});
	});

	describe('removeStorageItem', () => {
		it('removes the value', () => {
			setStorageItem('k', 'v');
			removeStorageItem('k');
			expect(getStorageItem('k')).toBeNull();
		});
	});

	describe('getStorageJSON / setStorageJSON', () => {
		it('round-trips JSON', () => {
			setStorageJSON('k', { a: 1, b: [2, 3] });
			expect(getStorageJSON('k', null)).toEqual({ a: 1, b: [2, 3] });
		});

		it('returns the default for missing keys', () => {
			expect(getStorageJSON('missing', { d: 1 })).toEqual({ d: 1 });
		});

		it('returns the default for invalid JSON', () => {
			window.localStorage.setItem('bad', '{not json');
			expect(getStorageJSON('bad', 'fallback')).toBe('fallback');
		});

		it('returns the default when the stored value is the literal null', () => {
			window.localStorage.setItem('null-payload', 'null');
			expect(getStorageJSON('null-payload', 'safe')).toBe('safe');
		});

		it('drops writes with circular references rather than corrupting state', () => {
			const circular = {};
			circular.self = circular;
			setStorageJSON('circ', circular);
			expect(window.localStorage.getItem('circ')).toBeNull();
		});
	});
});
