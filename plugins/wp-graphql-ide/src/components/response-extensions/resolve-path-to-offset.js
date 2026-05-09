import { parse as parseGraphQL } from 'graphql';

/**
 * Resolve a WPGraphQL trace path (e.g. `['posts', 'edges', 0, 'node',
 * 'link']`) to the character offset of that field's name token in the
 * query string. Used to wire up "click a row in Tracing → cursor
 * jumps to the field" navigation.
 *
 * Numeric segments are list indices and don't appear in the GraphQL
 * AST — they're stripped before traversal. Aliases take precedence
 * over field names so a path like `mediaItem.imageBig` (where the
 * trace records the alias) still resolves.
 *
 * Returns the start offset of the field's name token, or `null` when
 * the query is unparseable, no operation matches, or the path can't
 * be walked all the way down.
 *
 * @param {string}               query
 * @param {Array<string|number>} path
 * @return {number | null}
 */
export function resolvePathToOffset(query, path) {
	if (!query || !Array.isArray(path)) {
		return null;
	}
	// WPGraphQL emits tracing paths via JSON, which can hand back
	// list indices as either numbers (`0`) or all-digit strings
	// (`"0"`). Filter both shapes so the AST walk only sees field
	// names.
	const fieldPath = path.filter((seg) => {
		if (typeof seg === 'number') {
			return false;
		}
		if (typeof seg === 'string' && /^\d+$/.test(seg)) {
			return false;
		}
		return true;
	});
	if (fieldPath.length === 0) {
		return null;
	}

	let ast;
	try {
		ast = parseGraphQL(query);
	} catch {
		return null;
	}

	for (const def of ast.definitions || []) {
		if (def.kind !== 'OperationDefinition') {
			continue;
		}
		const offset = walkSelectionSet(def.selectionSet, fieldPath, 0);
		if (offset !== null) {
			return offset;
		}
	}
	return null;
}

function walkSelectionSet(selectionSet, path, depth) {
	if (!selectionSet || depth >= path.length) {
		return null;
	}
	const target = path[depth];
	for (const sel of selectionSet.selections || []) {
		if (sel.kind !== 'Field') {
			// Fragment spreads and inline fragments could resolve
			// further if we expanded them, but the trace records the
			// resolved field path so the simpler walk is usually
			// enough. Skip for now.
			continue;
		}
		const name = sel.alias?.value || sel.name?.value;
		if (name !== target) {
			continue;
		}
		if (depth === path.length - 1) {
			return sel.name?.loc?.start ?? null;
		}
		const nested = walkSelectionSet(sel.selectionSet, path, depth + 1);
		if (nested !== null) {
			return nested;
		}
	}
	return null;
}
