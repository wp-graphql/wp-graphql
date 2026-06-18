import {
	isEnumType,
	isInputObjectType,
	isListType,
	isNonNullType,
	isScalarType,
} from 'graphql';
import { ensureSyntaxTree, syntaxTree } from '@codemirror/language';

// Map the Lezer JSON value-node name to a coarse JSON kind label, so
// scalar mismatch messages read in user terms ("expected number, got
// string") regardless of which Lezer node produced the value.
const VALUE_NODE_TO_KIND = {
	String: 'string',
	Number: 'number',
	True: 'boolean',
	False: 'boolean',
	Null: 'null',
	Object: 'object',
	Array: 'array',
};

function getJsonKind(valueNodeName) {
	return VALUE_NODE_TO_KIND[valueNodeName] || 'unknown';
}

// Standard GraphQL scalars map cleanly to JSON kinds. Custom scalars
// (DateTime, JSON, etc.) generally accept strings; we don't try to
// validate inner format here — the server is the source of truth.
function expectedJsonKindForScalar(scalarType) {
	switch (scalarType.name) {
		case 'Int':
		case 'Float':
			return 'number';
		case 'Boolean':
			return 'boolean';
		case 'String':
		case 'ID':
			return 'string';
		default:
			return null;
	}
}

function describeType(type) {
	return String(type);
}

// Build a descriptor for a JSON value node that carries its source range
// plus lazy accessors for the structured children. We walk the tree
// eagerly (one pass per Object/Array level) but defer the recursion so a
// scalar variable doesn't traverse into siblings it'll never inspect.
function makeValueDescriptor(view, node) {
	return {
		from: node.from,
		to: node.to,
		name: node.name,
		getChildProps: () => collectObjectProps(view, node),
		getArrayChildren: () => collectArrayChildren(view, node),
	};
}

// The Lezer JSON tree models a Property as `PropertyName ":" value`, so
// the value is two siblings past the key (the literal colon sits in
// between). Walk forward until we hit a recognized value-kind node.
function findValueSibling(keyNode) {
	let cursor = keyNode ? keyNode.nextSibling : null;
	while (cursor) {
		if (VALUE_NODE_TO_KIND[cursor.name] !== undefined) {
			return cursor;
		}
		cursor = cursor.nextSibling;
	}
	return null;
}

function collectObjectProps(view, objectNode) {
	if (!objectNode || objectNode.name !== 'Object') {
		return null;
	}
	const props = new Map();
	let cursor = objectNode.firstChild;
	while (cursor) {
		if (cursor.name === 'Property') {
			const keyNode = cursor.firstChild;
			const valueNode = findValueSibling(keyNode);
			if (keyNode && valueNode) {
				const rawKey = view.state.doc.sliceString(
					keyNode.from,
					keyNode.to
				);
				try {
					const parsedKey = JSON.parse(rawKey);
					if (typeof parsedKey === 'string') {
						props.set(parsedKey, {
							keyFrom: keyNode.from,
							keyTo: keyNode.to,
							value: makeValueDescriptor(view, valueNode),
						});
					}
				} catch {
					// Unparseable PropertyName — JSON syntax linter handles it.
				}
			}
		}
		cursor = cursor.nextSibling;
	}
	return props;
}

function collectArrayChildren(view, arrayNode) {
	if (!arrayNode || arrayNode.name !== 'Array') {
		return null;
	}
	const children = [];
	let cursor = arrayNode.firstChild;
	while (cursor) {
		if (VALUE_NODE_TO_KIND[cursor.name] !== undefined) {
			children.push(makeValueDescriptor(view, cursor));
		}
		cursor = cursor.nextSibling;
	}
	return children;
}

// Recursively check a JSON value descriptor against a GraphQLInputType.
// Diagnostics are pushed onto the supplied array; ranges come from the
// Lezer tree, so the underline lands on the offending value rather than
// the surrounding key/object.
function checkValue(value, type, pathLabel, diagnostics) {
	if (isNonNullType(type)) {
		if (value.name === 'Null') {
			diagnostics.push({
				from: value.from,
				to: value.to,
				severity: 'error',
				message: `Expected value of non-null type "${describeType(
					type
				)}".`,
			});
			return;
		}
		checkValue(value, type.ofType, pathLabel, diagnostics);
		return;
	}

	if (value.name === 'Null') {
		// Nullable types accept null.
		return;
	}

	if (isListType(type)) {
		if (value.name !== 'Array') {
			// GraphQL coerces a single value into a one-element list, so
			// recurse rather than flag the shape itself.
			checkValue(value, type.ofType, pathLabel, diagnostics);
			return;
		}
		const items = value.getArrayChildren() || [];
		items.forEach((child, idx) => {
			checkValue(child, type.ofType, `${pathLabel}[${idx}]`, diagnostics);
		});
		return;
	}

	if (isInputObjectType(type)) {
		if (value.name !== 'Object') {
			diagnostics.push({
				from: value.from,
				to: value.to,
				severity: 'error',
				message: `Expected value of type "${describeType(type)}".`,
			});
			return;
		}
		const childProps = value.getChildProps();
		if (!childProps) {
			return;
		}
		const fields = type.getFields();
		for (const [fieldName, field] of Object.entries(fields)) {
			const provided = childProps.get(fieldName);
			if (!provided) {
				if (
					isNonNullType(field.type) &&
					field.defaultValue === undefined
				) {
					diagnostics.push({
						from: value.from,
						to: value.to,
						severity: 'error',
						message: `Field "${fieldName}" of required type "${describeType(
							field.type
						)}" was not provided.`,
					});
				}
				continue;
			}
			checkValue(
				provided.value,
				field.type,
				`${pathLabel}.${fieldName}`,
				diagnostics
			);
		}
		for (const [providedName, range] of childProps.entries()) {
			if (!fields[providedName]) {
				diagnostics.push({
					from: range.keyFrom,
					to: range.keyTo,
					severity: 'warning',
					message: `Field "${providedName}" is not defined on "${describeType(
						type
					)}".`,
				});
			}
		}
		return;
	}

	if (isEnumType(type)) {
		if (value.name !== 'String') {
			diagnostics.push({
				from: value.from,
				to: value.to,
				severity: 'error',
				message: `Expected value of type "${describeType(type)}".`,
			});
		}
		return;
	}

	if (isScalarType(type)) {
		const expected = expectedJsonKindForScalar(type);
		if (!expected) {
			return;
		}
		const actual = getJsonKind(value.name);
		if (actual !== expected) {
			diagnostics.push({
				from: value.from,
				to: value.to,
				severity: 'error',
				message: `Expected value of type "${describeType(type)}".`,
			});
		}
	}
}

/**
 * Validate the editor's JSON document against the operation's variable
 * definitions and produce CodeMirror diagnostics.
 *
 * Type mismatches and missing required values are errors; keys not in
 * the operation's variable list are warnings so an in-progress edit
 * (typing a variable that hasn't been declared yet) reads as
 * informational rather than broken.
 *
 * @param {import('@codemirror/view').EditorView}                    view
 * @param {import('graphql-language-service').VariableToType | null} variableToType
 *
 * @return {Array<import('@codemirror/lint').Diagnostic>}
 */
export function validateVariableTypes(view, variableToType) {
	if (!variableToType || Object.keys(variableToType).length === 0) {
		return [];
	}
	const text = view.state.doc.toString();
	if (!text.trim()) {
		return [];
	}

	// Bail if JSON syntax is broken — the JSON syntax linter handles
	// that, and stacking type errors on top adds noise.
	try {
		JSON.parse(text);
	} catch {
		return [];
	}

	// `syntaxTree(state)` returns whatever portion has been parsed so
	// far — on initial mount or after a fast paste, that can be a stub
	// tree where every node is `⚠` or untagged, which makes the value
	// type-check report "got unknown" against perfectly valid JSON.
	// Force a full parse (with a small timeout to stay responsive on
	// huge variable blobs) before trusting the tree.
	const tree =
		ensureSyntaxTree(view.state, view.state.doc.length, 50) ||
		syntaxTree(view.state);
	if (tree.length < view.state.doc.length) {
		// Parser still incomplete after the timeout — skip this lint
		// pass rather than emit false positives. The next doc change
		// will re-trigger and the tree will be ready.
		return [];
	}
	const root = tree.topNode.firstChild;
	if (!root || root.name !== 'Object') {
		return [
			{
				from: 0,
				to: text.length,
				severity: 'error',
				message: 'Variables must be a JSON object.',
			},
		];
	}

	const props = collectObjectProps(view, root);
	if (!props) {
		return [];
	}

	const diagnostics = [];

	for (const [name, type] of Object.entries(variableToType)) {
		const provided = props.get(name);
		if (!provided) {
			if (isNonNullType(type)) {
				diagnostics.push({
					from: root.from,
					to: root.to,
					severity: 'error',
					message: `Variable "$${name}" of required type "${describeType(
						type
					)}" was not provided.`,
				});
			}
			continue;
		}
		checkValue(provided.value, type, `$${name}`, diagnostics);
	}

	for (const [name, range] of props.entries()) {
		if (!variableToType[name]) {
			diagnostics.push({
				from: range.keyFrom,
				to: range.keyTo,
				severity: 'warning',
				message: `Variable "$${name}" is not defined by this operation.`,
			});
		}
	}

	return diagnostics;
}
