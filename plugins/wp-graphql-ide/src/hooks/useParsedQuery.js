import { useMemo } from 'react';
import { parse as parseGraphQL } from 'graphql';
import { collectVariables } from 'graphql-language-service';

/**
 * Parse the GraphQL document once and surface the three derivations
 * that other parts of the UI need:
 *
 *  - `parsedQuery` — `{ ast, parseable, empty }`. Reused by the
 *    op-picker, the Publish button's "is this valid?" guard, and any
 *    composer state that wants the AST.
 *  - `operationNames` — named operations declared in the document.
 *    The Execute button promotes to a dropdown when length > 1 since
 *    graphql-php returns an error if multiple operations are sent
 *    without a target name.
 *  - `variableToType` — `{ varName: GraphQLInputType }` map for the
 *    Variables JSON linter and autocomplete. `null` when the schema
 *    hasn't loaded or the doc doesn't parse — the JSON editor falls
 *    back to syntax-only checking in that case.
 *
 * @param {string} query  - GraphQL document text.
 * @param {Object} schema - GraphQL schema (or null while loading).
 *
 * @return {{
 *   parsedQuery: { ast: object|null, parseable: boolean, empty: boolean },
 *   operationNames: Array<string>,
 *   variableToType: object|null,
 * }}
 */
export function useParsedQuery(query, schema) {
	const parsedQuery = useMemo(() => {
		if (!query || !query.trim()) {
			return { ast: null, parseable: false, empty: true };
		}
		try {
			return { ast: parseGraphQL(query), parseable: true, empty: false };
		} catch {
			return { ast: null, parseable: false, empty: false };
		}
	}, [query]);

	const operationNames = useMemo(() => {
		if (!parsedQuery.ast) {
			return [];
		}
		return parsedQuery.ast.definitions
			.filter(
				(d) =>
					d.kind === 'OperationDefinition' && d.name && d.name.value
			)
			.map((d) => d.name.value);
	}, [parsedQuery]);

	const variableToType = useMemo(() => {
		if (!schema || !parsedQuery.ast) {
			return null;
		}
		try {
			return collectVariables(schema, parsedQuery.ast);
		} catch {
			return null;
		}
	}, [schema, parsedQuery]);

	return { parsedQuery, operationNames, variableToType };
}
