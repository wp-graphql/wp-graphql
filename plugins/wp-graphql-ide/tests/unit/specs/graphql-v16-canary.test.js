/* eslint-env jest */
/**
 * Canary tests pinning graphql-js v16 behaviors that v17 changes.
 *
 * These tests are EXPECTED TO FAIL when the `graphql` dependency is bumped
 * to v17 (see https://www.graphql-js.org/upgrade-guides/v16-v17). They exist
 * so a future major bump fails loudly at the exact behaviors our code relies
 * on, instead of silently misbehaving at runtime. When intentionally
 * migrating to v17, update each pinned behavior below along with the code
 * that depends on it — do not just delete this file.
 *
 * Dependabot is configured to ignore graphql major updates until the
 * migration happens deliberately (see .github/dependabot.yml).
 */
import { GraphQLError, parse, version as graphqlVersion } from 'graphql';
import * as GraphQL from 'graphql';

describe('graphql-js v16 canary', () => {
	it('is the v16 major (bumping to v17 requires a coordinated migration)', () => {
		// v17 also requires our graphiql-ecosystem peers to support it; as of
		// graphql 17.0.2, @graphiql/toolkit 0.9.x only allows ^15 || ^16,
		// which would leave two graphql copies in the bundle.
		expect(graphqlVersion.split('.')[0]).toBe('16');
	});

	it('parses empty AST collections as arrays, not undefined', () => {
		// v17's parser omits empty optional collections (`arguments`,
		// `directives`, `variableDefinitions`, ...) as `undefined`. Our AST
		// consumers (App.jsx visit(), api/external-fragments.js,
		// hooks/useParsedQuery.js, the query-composer-panel utils) iterate
		// these without optional chaining today.
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
		// src/graphql.js assigns the whole graphql namespace to
		// `window.graphql` as a public surface for IDE extensions, so
		// exports removed in v17 are a breaking change for third-party
		// extension code, not just for us.
		expect(typeof GraphQL.printError).toBe('function');
		expect(typeof GraphQL.formatError).toBe('function');
		expect(typeof GraphQL.getOperationRootType).toBe('function');
		expect(typeof GraphQL.assertValidName).toBe('function');
		expect(typeof GraphQL.getVisitFn).toBe('function');
	});
});
