/**
 * External fragments — declarative GraphQL fragments shipped by the
 * server (`wpgraphql_ide_external_fragments` PHP filter) and merged into
 * outgoing queries that reference them. Mirrors the 4.x behavior, but
 * smart-merges: only fragments named by an unresolved spread in the
 * outgoing query are injected, and transitive references between
 * external fragments are resolved.
 *
 * Registered as a built-in `wpgraphql-ide.executeRequest` filter
 * consumer so the rest of the IDE stays agnostic to fragment merging.
 *
 * @since x-release-please-version
 */

import { parse, visit } from 'graphql';

const NAMESPACE = 'wpgraphql-ide/external-fragments';

/**
 * Extract the fragment name from a single fragment-definition SDL
 * string. Returns null for anything that isn't a parseable fragment
 * definition (anonymous queries, multiple definitions, syntax errors).
 *
 * @param {string} sdl
 * @return {string|null}
 */
export function parseFragmentName(sdl) {
	if (typeof sdl !== 'string' || sdl.trim() === '') {
		return null;
	}
	try {
		const doc = parse(sdl);
		const def = doc.definitions[0];
		if (def?.kind === 'FragmentDefinition') {
			return def.name.value;
		}
	} catch {
		return null;
	}
	return null;
}

/**
 * Build a `name → SDL` map from the external fragments array,
 * skipping entries that don't parse as fragment definitions and
 * keeping the first definition when names collide.
 *
 * @param {string[]} fragments
 * @return {Map<string, string>}
 */
function buildFragmentMap(fragments) {
	const map = new Map();
	for (const sdl of fragments) {
		const name = parseFragmentName(sdl);
		if (name && !map.has(name)) {
			map.set(name, sdl);
		}
	}
	return map;
}

/**
 * Visit an AST, returning the set of fragment names defined in the
 * document and the set of fragment names referenced by spreads.
 *
 * @param {import('graphql').DocumentNode} ast
 * @return {{defined: Set<string>, spread: Set<string>}}
 */
function collectFragmentUsage(ast) {
	const defined = new Set();
	const spread = new Set();
	visit(ast, {
		FragmentDefinition(node) {
			defined.add(node.name.value);
		},
		FragmentSpread(node) {
			spread.add(node.name.value);
		},
	});
	return { defined, spread };
}

/**
 * Given an outgoing query and the parsed external-fragment map, return
 * the ordered list of fragment names that should be injected. Resolves
 * transitive references — if `A` is referenced by the query and `A`
 * spreads `B`, both are injected.
 *
 * Unparseable queries return an empty set so a syntax-broken query in
 * the editor never wedges execution.
 *
 * @param {string}              query
 * @param {Map<string, string>} fragmentMap
 * @return {string[]} Ordered list of fragment names to inject.
 */
function resolveInjections(query, fragmentMap) {
	let ast;
	try {
		ast = parse(query);
	} catch {
		return [];
	}
	const { defined, spread } = collectFragmentUsage(ast);
	const order = [];
	const seen = new Set();
	const queue = [];

	for (const name of spread) {
		if (!defined.has(name) && fragmentMap.has(name) && !seen.has(name)) {
			seen.add(name);
			order.push(name);
			queue.push(name);
		}
	}

	while (queue.length) {
		const name = queue.shift();
		const sdl = fragmentMap.get(name);
		let nestedAst;
		try {
			nestedAst = parse(sdl);
		} catch {
			continue;
		}
		const nested = collectFragmentUsage(nestedAst);
		for (const nestedName of nested.spread) {
			if (
				!defined.has(nestedName) &&
				fragmentMap.has(nestedName) &&
				!seen.has(nestedName)
			) {
				seen.add(nestedName);
				order.push(nestedName);
				queue.push(nestedName);
			}
		}
	}

	return order;
}

/**
 * Return a copy of `request` with the referenced external fragments
 * prepended to `request.query`. If there's nothing to inject (no
 * fragments, no query, no unresolved spreads), the original request
 * is returned unchanged so this function is safe to use as a no-op
 * pass-through in the filter chain.
 *
 * @param {Object}   request
 * @param {string[]} fragments
 * @return {Object}
 */
export function injectExternalFragments(request, fragments) {
	if (!Array.isArray(fragments) || fragments.length === 0) {
		return request;
	}
	const query = request?.query;
	if (typeof query !== 'string' || query === '') {
		return request;
	}
	const fragmentMap = buildFragmentMap(fragments);
	if (fragmentMap.size === 0) {
		return request;
	}
	const toInject = resolveInjections(query, fragmentMap);
	if (toInject.length === 0) {
		return request;
	}
	const prepended = toInject
		.map((name) => fragmentMap.get(name))
		.join('\n\n');
	return {
		...request,
		query: `${prepended}\n\n${query}`,
	};
}

/**
 * Register the built-in `wpgraphql-ide.executeRequest` filter consumer
 * that injects external fragments referenced by the outgoing query.
 * Idempotent — re-running removes the existing registration before
 * re-adding so hot reload doesn't stack consumers.
 *
 * @param {Object} hooks `@wordpress/hooks` instance.
 */
export function registerExternalFragmentInjector(hooks) {
	hooks.removeFilter('wpgraphql-ide.executeRequest', NAMESPACE);
	hooks.addFilter('wpgraphql-ide.executeRequest', NAMESPACE, (request) => {
		const fragments =
			(typeof window !== 'undefined' &&
				window.WPGRAPHQL_IDE_DATA?.context?.externalFragments) ||
			[];
		return injectExternalFragments(request, fragments);
	});
}
