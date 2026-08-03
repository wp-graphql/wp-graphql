/**
 * Canary tests pinning graphql-js v16 behaviors that v17 changes.
 *
 * These tests are EXPECTED TO FAIL when the `graphql` dependency is bumped
 * to v17 (see https://www.graphql-js.org/upgrade-guides/v16-v17). The legacy
 * bundled GraphiQL app cannot take that bump at all today: graphiql 1.11.5
 * and @apollo/client 3.x peer-depend on graphql ^15 || ^16, so a v17 bump
 * leaves two graphql copies in the bundle and cross-copy `instanceof` /
 * type-guard checks fail silently in production builds.
 *
 * Dependabot is configured to ignore graphql major updates until the legacy
 * IDE is retired and the migration happens deliberately (see
 * .github/dependabot.yml). If you are intentionally migrating to v17, update
 * each pinned behavior below along with the code that depends on it — do not
 * just delete this file.
 */
// Deep import mirrors packages/wpgraphiql/index.js, which exposes this whole
// namespace on `window` for GraphiQL extensions.
import * as GraphQL from 'graphql/index.js';

const { GraphQLError, parse, version: graphqlVersion } = GraphQL;

describe('graphql-js v16 canary', () => {
	it('is the v16 major (graphiql 1.x and @apollo/client 3.x cannot use v17)', () => {
		expect(graphqlVersion.split('.')[0]).toBe('16');
	});

	it('parses empty AST collections as arrays, not undefined', () => {
		// v17's parser omits empty optional collections (`arguments`,
		// `directives`, `variableDefinitions`, ...) as `undefined`. The
		// graphiql-query-composer utils and GraphiQLContext walk these
		// without optional chaining today.
		const doc = parse('{ posts { nodes { id } } }');
		const operation = doc.definitions[0];
		const field = operation.selectionSet.selections[0];

		expect(operation.variableDefinitions).toEqual([]);
		expect(operation.directives).toEqual([]);
		expect(field.arguments).toEqual([]);
	});

	it('supports the positional GraphQLError constructor', () => {
		// v17 removed positional arguments; only
		// `new GraphQLError(message, options)` remains.
		const doc = parse('{ posts { nodes { id } } }');
		const node = doc.definitions[0];

		const error = new GraphQLError('boom', node);

		expect(error.nodes).toEqual([node]);
	});

	it('exposes helpers on the namespace that v17 removes', () => {
		// The whole namespace is public via `window` (see index.js), so
		// exports removed in v17 break third-party GraphiQL extension code,
		// not just ours.
		expect(typeof GraphQL.printError).toBe('function');
		expect(typeof GraphQL.formatError).toBe('function');
		expect(typeof GraphQL.getOperationRootType).toBe('function');
		expect(typeof GraphQL.assertValidName).toBe('function');
		expect(typeof GraphQL.getVisitFn).toBe('function');
	});
});
