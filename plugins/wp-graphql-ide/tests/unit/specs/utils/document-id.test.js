import { isTempId } from '../../../../src/utils/document-id';

describe('isTempId', () => {
	it('returns true for IDs prefixed with `temp-`', () => {
		expect(isTempId('temp-1234')).toBe(true);
		expect(isTempId('temp-abc')).toBe(true);
	});

	it('returns false for numeric / non-temp IDs', () => {
		expect(isTempId(42)).toBe(false);
		expect(isTempId('42')).toBe(false);
		expect(isTempId('post-42')).toBe(false);
	});

	it('coerces non-string IDs to string before checking', () => {
		expect(isTempId({ toString: () => 'temp-x' })).toBe(true);
	});

	it('handles edge cases without throwing', () => {
		expect(isTempId('')).toBe(false);
		expect(isTempId(0)).toBe(false);
		expect(isTempId(null)).toBe(false);
		expect(isTempId(undefined)).toBe(false);
	});
});
