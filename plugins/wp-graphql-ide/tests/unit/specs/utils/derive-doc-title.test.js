import {
	deriveDocTitle,
	deriveStableDocTitle,
	isAutoTitle,
	displayDocTitle,
} from '../../../../src/utils/derive-doc-title';

describe('deriveDocTitle', () => {
	it('returns the operation name when present', () => {
		expect(
			deriveDocTitle('query GetPosts { posts { nodes { id } } }')
		).toBe('GetPosts');
	});

	it('returns the first top-level field for anonymous operations', () => {
		expect(deriveDocTitle('{ posts { nodes { id } } }')).toBe('posts');
	});

	it("falls back to 'Untitled' for empty input", () => {
		expect(deriveDocTitle('')).toBe('Untitled');
		expect(deriveDocTitle('   \n  ')).toBe('Untitled');
		expect(deriveDocTitle(null)).toBe('Untitled');
		expect(deriveDocTitle(undefined)).toBe('Untitled');
	});

	it("falls back to 'Untitled' for unparseable input", () => {
		expect(deriveDocTitle('not a graphql doc')).toBe('Untitled');
		expect(deriveDocTitle('{ unclosed')).toBe('Untitled');
	});

	it('uses the first operation when multiple are present', () => {
		const doc = `
			query First { posts { nodes { id } } }
			query Second { users { nodes { id } } }
		`;
		expect(deriveDocTitle(doc)).toBe('First');
	});

	it('handles mutation and subscription operations', () => {
		expect(
			deriveDocTitle('mutation CreateThing { createThing { id } }')
		).toBe('CreateThing');
		expect(
			deriveDocTitle('subscription WatchThing { thingChanged { id } }')
		).toBe('WatchThing');
	});
});

describe('deriveStableDocTitle', () => {
	it('returns null until the op-body opens', () => {
		// Before `(` or `{` arrives, the op name is still being typed.
		expect(deriveStableDocTitle('query GetPo')).toBeNull();
		expect(deriveStableDocTitle('query GetPosts')).toBeNull();
	});

	it('returns the op name once body or args open', () => {
		expect(deriveStableDocTitle('query GetPosts {')).toBe('GetPosts');
		expect(deriveStableDocTitle('query GetPosts(')).toBe('GetPosts');
		expect(
			deriveStableDocTitle(
				'mutation Save($id: ID!) { save(id: $id) { ok } }'
			)
		).toBe('Save');
	});

	it('returns null for null/empty input', () => {
		expect(deriveStableDocTitle(null)).toBeNull();
		expect(deriveStableDocTitle('')).toBeNull();
	});

	it('returns null for anonymous operations', () => {
		expect(deriveStableDocTitle('{ posts { id } }')).toBeNull();
	});
});

describe('isAutoTitle', () => {
	it('treats empty/whitespace as auto', () => {
		expect(isAutoTitle('')).toBe(true);
		expect(isAutoTitle('   ')).toBe(true);
		expect(isAutoTitle(null)).toBe(true);
		expect(isAutoTitle(undefined)).toBe(true);
	});

	it("treats the literal 'Untitled' as auto", () => {
		// The server-side upsert substitutes 'Untitled' for empty titles,
		// so the client treats it as a sentinel for derivation.
		expect(isAutoTitle('Untitled')).toBe(true);
	});

	it('treats a real title as not auto', () => {
		expect(isAutoTitle('My Important Query')).toBe(false);
		expect(isAutoTitle('untitled')).toBe(false); // case-sensitive
	});
});

describe('displayDocTitle', () => {
	it('derives from query when title is auto', () => {
		const doc = {
			title: 'Untitled',
			query: 'query GetPosts { posts { nodes { id } } }',
		};
		expect(displayDocTitle(doc)).toBe('GetPosts');
	});

	it('returns title verbatim when manually named', () => {
		const doc = {
			title: 'My Query',
			query: 'query Anything { posts { id } }',
		};
		expect(displayDocTitle(doc)).toBe('My Query');
	});

	it('handles missing doc', () => {
		expect(displayDocTitle(null)).toBe('Untitled');
		expect(displayDocTitle(undefined)).toBe('Untitled');
		expect(displayDocTitle({})).toBe('Untitled');
	});
});
