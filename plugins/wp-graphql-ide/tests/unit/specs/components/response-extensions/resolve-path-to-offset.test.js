import { resolvePathToOffset } from '../../../../../src/components/response-extensions/resolve-path-to-offset';

describe('resolvePathToOffset', () => {
	const query = `query GetPosts {
  posts {
    edges {
      node {
        id
        title
        link
      }
    }
    pageInfo {
      hasNextPage
    }
  }
}`;

	it('resolves a top-level field', () => {
		const offset = resolvePathToOffset(query, ['posts']);
		expect(query.slice(offset, offset + 5)).toBe('posts');
	});

	it('resolves a nested field', () => {
		const offset = resolvePathToOffset(query, [
			'posts',
			'pageInfo',
			'hasNextPage',
		]);
		expect(query.slice(offset, offset + 'hasNextPage'.length)).toBe(
			'hasNextPage'
		);
	});

	it('strips numeric (array index) segments', () => {
		const offset = resolvePathToOffset(query, [
			'posts',
			'edges',
			0,
			'node',
			'link',
		]);
		expect(query.slice(offset, offset + 4)).toBe('link');
	});

	it('strips string-encoded numeric segments (JSON tracing)', () => {
		const offset = resolvePathToOffset(query, [
			'posts',
			'edges',
			'0',
			'node',
			'link',
		]);
		expect(query.slice(offset, offset + 4)).toBe('link');
	});

	it('returns null for an unparseable query', () => {
		expect(resolvePathToOffset('query Foo {', ['foo'])).toBeNull();
	});

	it('returns null when the path cannot be walked', () => {
		expect(resolvePathToOffset(query, ['posts', 'nonexistent'])).toBeNull();
	});

	it('returns null for empty input', () => {
		expect(resolvePathToOffset('', ['posts'])).toBeNull();
		expect(resolvePathToOffset(query, [])).toBeNull();
		expect(resolvePathToOffset(query, null)).toBeNull();
	});

	it('matches by alias when the field is aliased', () => {
		const aliased = `query A {
  posts {
    edges {
      node {
        permalink: link
      }
    }
  }
}`;
		const offset = resolvePathToOffset(aliased, [
			'posts',
			'edges',
			0,
			'node',
			'permalink',
		]);
		// The offset is on the field's name (`link`), even though the
		// path used the alias.
		expect(aliased.slice(offset, offset + 4)).toBe('link');
	});
});
