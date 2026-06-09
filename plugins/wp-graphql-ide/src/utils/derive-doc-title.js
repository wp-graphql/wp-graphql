import { parse as parseGraphQL } from 'graphql';

// Treat empty/whitespace and the literal 'Untitled' as the auto sentinel.
// 'Untitled' is the server-side fallback in upsert_document(), so saved-then-
// reloaded auto docs round-trip as 'Untitled' rather than '' — matching here
// keeps derivation working without a server change.
export function isAutoTitle(title) {
	return !title || !title.trim() || title === 'Untitled';
}

// Live, partial-tolerant op-name extraction. Matches `query M`, `mutation Foo`,
// etc. before the body is typed — drives char-by-char tab title updates while
// the user types the operation name.
const OP_NAME_RE = /\b(?:query|mutation|subscription)\s+([A-Za-z_]\w*)/;

// "Op name complete" detection. Same as above but requires a body opener
// (`{` or `(`) after the name. Used by the sticky-persist step so we only
// freeze the title once the user has clearly finished naming the op.
const OP_NAME_COMPLETE_RE =
	/\b(?:query|mutation|subscription)\s+([A-Za-z_]\w*)\s*[({]/;

export function deriveDocTitle(query) {
	if (!query || !query.trim()) {
		return 'Untitled';
	}
	// Partial regex first — works while the user is still typing.
	const partial = query.match(OP_NAME_RE);
	if (partial) {
		return partial[1];
	}
	// Anonymous shorthand fallback (`{ posts { id } }`) — needs a parse.
	let ast;
	try {
		ast = parseGraphQL(query);
	} catch {
		return 'Untitled';
	}
	const op = ast.definitions.find((d) => d.kind === 'OperationDefinition');
	if (!op) {
		return 'Untitled';
	}
	const field = op.selectionSet?.selections?.find((s) => s.kind === 'Field');
	return field?.name?.value || 'Untitled';
}

// Returns the op name only when it's clearly "complete" (followed by a body
// opener). Caller persists this to doc.title; once persisted, the title stops
// drifting even if the user later edits the op name or removes it.
export function deriveStableDocTitle(query) {
	if (!query) {
		return null;
	}
	const m = query.match(OP_NAME_COMPLETE_RE);
	return m ? m[1] : null;
}

export function displayDocTitle(doc) {
	return isAutoTitle(doc?.title) ? deriveDocTitle(doc?.query) : doc.title;
}
