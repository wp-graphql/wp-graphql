import { validateExecutableQuery } from '../../../../src/hooks/useExecution';

const NO_OP =
	'No operation to execute. Type a GraphQL query, mutation, or subscription and run again.';

describe('validateExecutableQuery', () => {
	describe('not runnable — no executable content', () => {
		it('flags an empty string', () => {
			expect(validateExecutableQuery('')).toEqual({
				runnable: false,
				message: NO_OP,
			});
		});

		it('flags whitespace only', () => {
			expect(validateExecutableQuery('   \n\t  ')).toEqual({
				runnable: false,
				message: NO_OP,
			});
		});

		it('flags null and undefined input', () => {
			expect(validateExecutableQuery(null)).toEqual({
				runnable: false,
				message: NO_OP,
			});
			expect(validateExecutableQuery(undefined)).toEqual({
				runnable: false,
				message: NO_OP,
			});
		});

		it('flags a single-line comment', () => {
			expect(validateExecutableQuery('# just a comment')).toEqual({
				runnable: false,
				message: NO_OP,
			});
		});

		it('flags the full welcome boilerplate', () => {
			const welcome = `# Welcome to the WPGraphQL IDE
#
# Lines starting with "#" are comments.
#
# Example:
#
#   {
#     posts {
#       nodes {
#         id
#       }
#     }
#   }
`;
			expect(validateExecutableQuery(welcome)).toEqual({
				runnable: false,
				message: NO_OP,
			});
		});

		it('flags comments interleaved with blank lines', () => {
			const text = `

# alpha

# beta

`;
			expect(validateExecutableQuery(text)).toEqual({
				runnable: false,
				message: NO_OP,
			});
		});

		it('flags a fragment-only document (parses, zero operations)', () => {
			const fragmentOnly = `fragment PostFields on Post {
				id
				title
			}`;
			expect(validateExecutableQuery(fragmentOnly)).toEqual({
				runnable: false,
				message: NO_OP,
			});
		});
	});

	describe('not runnable — parse error', () => {
		it('surfaces the parser message verbatim for malformed input', () => {
			const result = validateExecutableQuery('{ posts { id ');
			expect(result.runnable).toBe(false);
			expect(result.message).toMatch(/^Query parse error: /);
		});

		it('does not collapse parse errors into the no-operation message', () => {
			const result = validateExecutableQuery('query Foo {');
			expect(result.runnable).toBe(false);
			expect(result.message).not.toBe(NO_OP);
			expect(result.message).toMatch(/^Query parse error: /);
		});
	});

	describe('runnable', () => {
		it('accepts a shorthand query', () => {
			expect(
				validateExecutableQuery('{ posts { nodes { id } } }')
			).toEqual({ runnable: true });
		});

		it('accepts a named query', () => {
			expect(
				validateExecutableQuery(
					'query GetPosts { posts { nodes { id } } }'
				)
			).toEqual({ runnable: true });
		});

		it('accepts a mutation', () => {
			expect(
				validateExecutableQuery(
					'mutation Create { createPost(input: {}) { post { id } } }'
				)
			).toEqual({ runnable: true });
		});

		it('accepts a subscription', () => {
			expect(
				validateExecutableQuery('subscription OnNew { newPost { id } }')
			).toEqual({ runnable: true });
		});

		it('accepts a document with multiple operations', () => {
			const multi = `query A { posts { nodes { id } } }
				query B { pages { nodes { id } } }`;
			expect(validateExecutableQuery(multi)).toEqual({ runnable: true });
		});

		it('accepts a query that uses fragments', () => {
			const withFragment = `query GetPosts {
				posts { nodes { ...PostFields } }
			}
			fragment PostFields on Post {
				id
				title
			}`;
			expect(validateExecutableQuery(withFragment)).toEqual({
				runnable: true,
			});
		});

		it('accepts a query preceded by comments', () => {
			const commented = `# preamble
				# notes
				{ posts { nodes { id } } }`;
			expect(validateExecutableQuery(commented)).toEqual({
				runnable: true,
			});
		});
	});
});
