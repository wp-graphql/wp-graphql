/* eslint-env browser, jest */

// jsdom doesn't ship `window.crypto.subtle` or `TextEncoder` reliably.
// Node provides both — wire them onto `window` / global before the util
// reads them. Module-level so the polyfill is in place before the first
// require / lazy import.
const { webcrypto } = require('crypto');
const { TextEncoder } = require('util');
if (!window.crypto || !window.crypto.subtle) {
	Object.defineProperty(window, 'crypto', {
		configurable: true,
		value: webcrypto,
	});
}
if (typeof global.TextEncoder === 'undefined') {
	global.TextEncoder = TextEncoder;
}

const {
	computeOperationHash,
	__resetOperationHashCacheForTests,
} = require('../../../../src/utils/operation-hash');

describe('computeOperationHash', () => {
	beforeEach(() => {
		__resetOperationHashCacheForTests();
	});

	it('returns a 64-char hex hash for a valid query', async () => {
		const hash = await computeOperationHash('{ __typename }');
		expect(hash).toMatch(/^[a-f0-9]{64}$/);
	});

	it('is stable across whitespace and comment differences', async () => {
		const a = await computeOperationHash('{__typename}');
		const b = await computeOperationHash('{\n  __typename\n}');
		const c = await computeOperationHash(
			'# A comment\n{\n  __typename\n}\n'
		);
		expect(a).toBe(b);
		expect(b).toBe(c);
	});

	it('changes when the operation name changes', async () => {
		// Identity is content-based, but a named operation is part of
		// the document — the printer emits the name, so different names
		// produce different hashes.
		const a = await computeOperationHash('query A { __typename }');
		const b = await computeOperationHash('query B { __typename }');
		expect(a).not.toBe(b);
	});

	it('returns null for unparseable input', async () => {
		expect(await computeOperationHash('{ unclosed')).toBeNull();
		expect(await computeOperationHash('not graphql at all')).toBeNull();
	});

	it('returns null for empty input', async () => {
		expect(await computeOperationHash('')).toBeNull();
		expect(await computeOperationHash('   ')).toBeNull();
		expect(await computeOperationHash(null)).toBeNull();
		expect(await computeOperationHash(undefined)).toBeNull();
	});
});
