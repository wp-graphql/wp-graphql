import {
	getNamedType,
	isEnumType,
	isInputObjectType,
	isListType,
	isNonNullType,
	isScalarType,
} from 'graphql';
import { syntaxTree } from '@codemirror/language';

// Parse a Lezer JSON String/PropertyName token into a plain string. The
// raw token text includes the surrounding quotes and any escape
// sequences; JSON.parse handles both cleanly.
function parseStringNode(node, doc) {
	const raw = doc.sliceString(node.from, node.to);
	try {
		const v = JSON.parse(raw);
		return typeof v === 'string' ? v : null;
	} catch {
		return null;
	}
}

function findColonChild(propertyNode) {
	let child = propertyNode.firstChild;
	while (child) {
		if (child.name === ':') {
			return child;
		}
		child = child.nextSibling;
	}
	return null;
}

function findKeyChild(propertyNode) {
	let child = propertyNode.firstChild;
	while (child) {
		if (child.name === 'PropertyName') {
			return child;
		}
		child = child.nextSibling;
	}
	return null;
}

function parseKeyOfProperty(propertyNode, doc) {
	const keyNode = findKeyChild(propertyNode);
	return keyNode ? parseStringNode(keyNode, doc) : null;
}

// Collect every direct-child key of a single Object node, so completion
// can filter out duplicates within whichever object the cursor sits in
// (top-level variables OR a nested input-object).
function collectKeysOfObject(objectNode, doc) {
	const keys = new Set();
	if (!objectNode || objectNode.name !== 'Object') {
		return keys;
	}
	let cursor = objectNode.firstChild;
	while (cursor) {
		if (cursor.name === 'Property') {
			const k = parseKeyOfProperty(cursor, doc);
			if (k !== null) {
				keys.add(k);
			}
		}
		cursor = cursor.nextSibling;
	}
	return keys;
}

// Resolve where the cursor sits, returning the navigation path from the
// top-level variables object down to the slot the user is editing. The
// path is a list of `{ kind: 'key', name }` and `{ kind: 'array' }`
// steps; each `key` step descends into an input-object field, each
// `array` step descends into a list element. The `slotKind` says
// whether the cursor is at a key (`'key'`) or value (`'value'`) slot
// inside that destination.
//
// Returns `null` if the cursor isn't inside the top-level variables
// object — autocomplete shouldn't fire for malformed structure.
function buildPath(state, pos) {
	const tree = syntaxTree(state);
	let cur = tree.resolveInner(pos, -1);

	// Climb until we reach a Property/Object/Array — those are the only
	// structural anchors that influence completion.
	while (
		cur &&
		cur.name !== 'Property' &&
		cur.name !== 'Object' &&
		cur.name !== 'Array' &&
		cur.name !== 'JsonText'
	) {
		cur = cur.parent;
	}

	if (!cur || cur.name === 'JsonText') {
		// Empty doc or cursor outside any literal — treat as the
		// top-level key slot so `Ctrl+Space` in a blank editor still
		// surfaces variable names.
		return {
			slotKind: 'key',
			path: [],
			innerObject: null,
		};
	}

	let slotKind;
	let innerObject = null;
	let editingKey = null;
	const reverse = []; // innermost step first; reversed at the end
	let walker;

	if (cur.name === 'Property') {
		const colon = findColonChild(cur);
		if (!colon || pos <= colon.from) {
			// Editing the key — innermost slot is "key inside the
			// Object that contains this Property". Record the current
			// key name so the dropdown can keep it visible (otherwise
			// the in-progress edit filters itself out as a duplicate).
			slotKind = 'key';
			walker = cur.parent;
			innerObject = walker;
			editingKey = parseKeyOfProperty(cur, state.doc);
		} else {
			// Past the colon — value slot. Record the property's key as
			// the first step of the path so type resolution can descend
			// through it.
			slotKind = 'value';
			const keyName = parseKeyOfProperty(cur, state.doc);
			if (!keyName) {
				return null;
			}
			reverse.push({ kind: 'key', name: keyName });
			walker = cur.parent;
		}
	} else if (cur.name === 'Object') {
		slotKind = 'key';
		walker = cur;
		innerObject = walker;
	} else {
		// Array — value slot inside a list.
		slotKind = 'value';
		reverse.push({ kind: 'array' });
		walker = cur;
	}

	if (
		!walker ||
		(walker.name !== 'Object' && walker.name !== 'Array') ||
		!walker.parent
	) {
		return null;
	}

	// Walk up from `walker` toward the top, recording each containing
	// Property/Array as a path step. We expect a strict alternation:
	// Object↔Property and Array↔Property (and Array↔Array for arrays
	// of arrays). Anything else means the tree is malformed for our
	// purposes and we bail.
	while (walker && walker.parent && walker.parent.name !== 'JsonText') {
		const parent = walker.parent;
		if (
			(walker.name === 'Object' || walker.name === 'Array') &&
			parent.name === 'Property'
		) {
			const keyName = parseKeyOfProperty(parent, state.doc);
			if (!keyName) {
				return null;
			}
			reverse.push({ kind: 'key', name: keyName });
			walker = parent.parent;
		} else if (
			(walker.name === 'Object' || walker.name === 'Array') &&
			parent.name === 'Array'
		) {
			reverse.push({ kind: 'array' });
			walker = parent;
		} else {
			return null;
		}
	}

	if (!walker || !walker.parent || walker.parent.name !== 'JsonText') {
		return null;
	}

	return {
		slotKind,
		path: reverse.reverse(),
		innerObject,
		editingKey,
	};
}

// Walk a GraphQL InputType down a path of keys/array steps, returning
// the type at the final step (or null if any step doesn't fit — e.g.
// trying to descend into a key of a non-input-object type, or a field
// name the schema doesn't recognize).
function resolveTypeAtPath(rootMap, path) {
	if (path.length === 0 || path[0].kind !== 'key') {
		return null;
	}
	let type = rootMap[path[0].name];
	if (!type) {
		return null;
	}
	for (let i = 1; i < path.length; i++) {
		const step = path[i];
		if (step.kind === 'array') {
			const inner = isNonNullType(type) ? type.ofType : type;
			if (isListType(inner)) {
				type = inner.ofType;
			}
			// If the type isn't a list, GraphQL coerces a single value
			// into a one-element list — keep `type` as-is so we can
			// still complete inside the array literal.
		} else {
			const named = getNamedType(type);
			if (!isInputObjectType(named)) {
				return null;
			}
			const field = named.getFields()[step.name];
			if (!field) {
				return null;
			}
			type = field.type;
		}
	}
	return type;
}

function describeType(type) {
	return String(type);
}

// Build value-slot completion items for a resolved type. Only emits
// finite, type-driven options (enum values, booleans, null) — we don't
// guess freeform scalar values like Int/String/ID.
function valueCompletions(type) {
	if (!type) {
		return null;
	}
	const named = getNamedType(type);
	const options = [];
	if (isEnumType(named)) {
		for (const value of named.getValues()) {
			options.push({
				label: `"${value.name}"`,
				type: 'enum',
				detail: value.description || named.name,
			});
		}
	} else if (isScalarType(named) && named.name === 'Boolean') {
		options.push(
			{ label: 'true', type: 'keyword', detail: 'Boolean' },
			{ label: 'false', type: 'keyword', detail: 'Boolean' }
		);
	}
	// Nullable structured types accept `null`. Suggesting it is mostly
	// useful for enums/inputs — for free-form scalars the user's about
	// to type a literal anyway and `null` would clutter the dropdown.
	if (
		!isNonNullType(type) &&
		(isEnumType(named) || isInputObjectType(named) || isListType(type))
	) {
		options.push({ label: 'null', type: 'keyword', detail: 'nullable' });
	}
	return options.length ? options : null;
}

// Build key-slot completion items for an InputObjectType. The InputObject
// fields are filtered against keys already present in the Object node
// (so we don't suggest a key that's about to collide). The currently
// edited key, if any, is intentionally kept in the suggestion list so
// the dropdown doesn't disappear mid-keystroke.
function objectKeyCompletions(objectType, usedKeys) {
	const fields = objectType.getFields();
	const options = [];
	for (const [name, field] of Object.entries(fields)) {
		if (usedKeys.has(name)) {
			continue;
		}
		options.push({
			label: `"${name}"`,
			type: 'property',
			detail: describeType(field.type),
			info: field.description || undefined,
			apply: `"${name}": `,
		});
	}
	return options;
}

/**
 * Build a CodeMirror autocomplete source that suggests:
 *
 *   • Variable names at the top-level object's property-name slots,
 *     applied as `"name": ` so the user can type the value next.
 *   • Field names inside any nested input-object literal, walking
 *     through input-object fields and list elements as the user types.
 *   • Type-driven literals at value slots — enum values, `true`/
 *     `false`, and `null` for nullable structured types.
 *
 * Reads the current `variableToType` map through a ref so prop changes
 * to the editor don't require rebuilding the source.
 *
 * @param {{ current: import('graphql-language-service').VariableToType | null }} variableToTypeRef
 *
 * @return {(context: import('@codemirror/autocomplete').CompletionContext) => import('@codemirror/autocomplete').CompletionResult | null}
 */
export function createVariableCompletionSource(variableToTypeRef) {
	return (context) => {
		const variableToType = variableToTypeRef.current;
		if (!variableToType || Object.keys(variableToType).length === 0) {
			return null;
		}

		const ctx = buildPath(context.state, context.pos);
		if (!ctx) {
			return null;
		}

		// Top-level key slot — suggest the operation's variable names.
		if (ctx.path.length === 0 && ctx.slotKind === 'key') {
			const word = context.matchBefore(/"?[\w$]*/);
			if (!word && !context.explicit) {
				return null;
			}
			const used = ctx.innerObject
				? collectKeysOfObject(ctx.innerObject, context.state.doc)
				: new Set();
			if (ctx.editingKey) {
				used.delete(ctx.editingKey);
			}
			const options = Object.entries(variableToType)
				.filter(([name]) => !used.has(name))
				.map(([name, type]) => ({
					label: `"${name}"`,
					type: 'property',
					detail: describeType(type),
					apply: `"${name}": `,
				}));
			if (!options.length) {
				return null;
			}
			return {
				from: word ? word.from : context.pos,
				to: word ? word.to : context.pos,
				options,
				validFor: /^"?[\w$]*$/,
			};
		}

		// Past the top-level — descend through types to find the slot's
		// expected type.
		const resolvedType = resolveTypeAtPath(variableToType, ctx.path);

		if (ctx.slotKind === 'key') {
			// Resolved type is the input-object whose fields we want.
			const named = resolvedType ? getNamedType(resolvedType) : null;
			if (!named || !isInputObjectType(named)) {
				return null;
			}
			const word = context.matchBefore(/"?[\w$]*/);
			if (!word && !context.explicit) {
				return null;
			}
			const used = ctx.innerObject
				? collectKeysOfObject(ctx.innerObject, context.state.doc)
				: new Set();
			if (ctx.editingKey) {
				used.delete(ctx.editingKey);
			}
			const options = objectKeyCompletions(named, used);
			if (!options.length) {
				return null;
			}
			return {
				from: word ? word.from : context.pos,
				to: word ? word.to : context.pos,
				options,
				validFor: /^"?[\w$]*$/,
			};
		}

		// Value slot — resolved type is the value's expected type.
		const options = valueCompletions(resolvedType);
		if (!options) {
			return null;
		}
		const word = context.matchBefore(/"?[\w$]*/);
		return {
			from: word ? word.from : context.pos,
			to: word ? word.to : context.pos,
			options,
			validFor: /^"?[\w$]*$/,
		};
	};
}
