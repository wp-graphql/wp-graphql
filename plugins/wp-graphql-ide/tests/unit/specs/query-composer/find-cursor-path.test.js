/* eslint-env browser, jest */

import { parse } from 'graphql';
import { findCursorPath } from '../../../../plugins/query-composer-panel/src/utils';

// Helper: parse a document and return its operation-like definitions
// (OperationDefinition + FragmentDefinition) — the same shape ExplorerView
// passes to findCursorPath.
function operationsFrom(source) {
	return parse(source).definitions.filter(
		(d) =>
			d.kind === 'OperationDefinition' || d.kind === 'FragmentDefinition'
	);
}

// Helper: return the character offset of the cursor marker `|` in `source`
// and the source with the marker stripped out. Lets each test express the
// cursor inline at the field of interest.
function offsetAt(source) {
	const at = source.indexOf('|');
	if (at < 0) {
		throw new Error('Test source missing cursor marker "|"');
	}
	return { offset: at, text: source.slice(0, at) + source.slice(at + 1) };
}

describe('findCursorPath', () => {
	it('returns null for a non-numeric cursor offset', () => {
		const ops = operationsFrom('{ __typename }');
		expect(findCursorPath(ops, null)).toBeNull();
		expect(findCursorPath(ops, undefined)).toBeNull();
		expect(findCursorPath(ops, 'nope')).toBeNull();
	});

	it('returns null when the cursor is outside every operation', () => {
		// Anonymous query — its loc starts at the `{`. Offset 0 lands before.
		const ops = operationsFrom('   { __typename }');
		expect(findCursorPath(ops, 0)).toBeNull();
	});

	it('returns the deepest field path for a nested cursor', () => {
		const { offset, text } = offsetAt(
			'query MyQuery { posts { nodes { tit|le excerpt } } }'
		);
		const result = findCursorPath(operationsFrom(text), offset);
		expect(result).toEqual({
			opKey: 'query:MyQuery',
			fieldPath: ['posts', 'nodes', 'title'],
		});
	});

	it('returns the containing field when cursor is on whitespace inside its selection set', () => {
		const { offset, text } = offsetAt(
			'query MyQuery { posts { nodes {| title } } }'
		);
		const result = findCursorPath(operationsFrom(text), offset);
		expect(result).toEqual({
			opKey: 'query:MyQuery',
			fieldPath: ['posts', 'nodes'],
		});
	});

	it('returns an empty fieldPath when the cursor is on the operation keyword', () => {
		const { offset, text } = offsetAt('qu|ery MyQuery { posts { title } }');
		const result = findCursorPath(operationsFrom(text), offset);
		expect(result).toEqual({
			opKey: 'query:MyQuery',
			fieldPath: [],
		});
	});

	it('picks the operation whose loc spans the cursor among multiple', () => {
		const { offset, text } = offsetAt(
			'query A { posts { title } }\nquery B { pages { ti|tle } }'
		);
		const result = findCursorPath(operationsFrom(text), offset);
		expect(result).toEqual({
			opKey: 'query:B',
			fieldPath: ['pages', 'title'],
		});
	});

	it('uses positional keys for anonymous operations', () => {
		const { offset, text } = offsetAt('{ pos|ts { title } }');
		const result = findCursorPath(operationsFrom(text), offset);
		expect(result).toEqual({
			opKey: 'query:_0',
			fieldPath: ['posts'],
		});
	});

	it('returns the containing field path when cursor is on a fragment spread', () => {
		const { offset, text } = offsetAt(
			`query MyQuery { posts { nodes { ...Post|Bits } } }
			 fragment PostBits on Post { title }`
		);
		const result = findCursorPath(operationsFrom(text), offset);
		// We do not descend into the fragment in v1 — stop at the parent field.
		expect(result).toEqual({
			opKey: 'query:MyQuery',
			fieldPath: ['posts', 'nodes'],
		});
	});

	it('matches inside arguments by treating them as part of the field', () => {
		const { offset, text } = offsetAt(
			'query MyQuery { posts(fir|st: 5) { nodes { title } } }'
		);
		const result = findCursorPath(operationsFrom(text), offset);
		expect(result).toEqual({
			opKey: 'query:MyQuery',
			fieldPath: ['posts'],
		});
	});

	it('handles fragment definitions', () => {
		const { offset, text } = offsetAt(
			'fragment PostBits on Post { ti|tle excerpt }'
		);
		const result = findCursorPath(operationsFrom(text), offset);
		expect(result).toEqual({
			opKey: 'fragment:PostBits',
			fieldPath: ['title'],
		});
	});
});
