/* eslint-env jest */
import { parseAliasInUseError } from '../../../../src/utils/parse-publish-error';

describe('parseAliasInUseError', () => {
	it('parses the Smart Cache alias-collision message', () => {
		const message =
			'Alias "dbf44d5f37b20ab4c605bf196d5e0c1116446506969d790f66bd1780d4ff8ae2" already in use by another query "fdsf"';
		expect(parseAliasInUseError(message)).toEqual({
			alias: 'dbf44d5f37b20ab4c605bf196d5e0c1116446506969d790f66bd1780d4ff8ae2',
			conflictTitle: 'fdsf',
		});
	});

	it('handles quoted titles with spaces and punctuation', () => {
		const message =
			'Alias "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" already in use by another query "Recent Posts (v2)"';
		expect(parseAliasInUseError(message)?.conflictTitle).toBe(
			'Recent Posts (v2)'
		);
	});

	it('returns null for unrelated error messages', () => {
		expect(parseAliasInUseError('Network request failed')).toBeNull();
		expect(parseAliasInUseError('')).toBeNull();
		expect(parseAliasInUseError(null)).toBeNull();
		expect(parseAliasInUseError(undefined)).toBeNull();
	});

	it('rejects non-sha256 aliases so the pattern stays tight', () => {
		// Same shape but the alias isn't a 64-char hex string — we should
		// fall through to the generic error path.
		const message =
			'Alias "not-a-hash" already in use by another query "fdsf"';
		expect(parseAliasInUseError(message)).toBeNull();
	});
});
