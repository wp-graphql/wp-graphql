import {
	isEnumType,
	isInputObjectType,
	isLeafType,
	isListType,
	isNonNullType,
	isRequiredInputField,
	isScalarType,
	isWrappingType,
	parse,
} from 'graphql';

export const defaultColors = {
	keyword: '#B11A04',
	def: '#D2054E',
	property: '#1F61A0',
	qualifier: '#1C92A9',
	attribute: '#8B2BB9',
	number: '#2882F9',
	string: '#D64292',
	builtin: '#D47509',
	string2: '#0B7FC7',
	variable: '#397D13',
	atom: '#CA9800',
};

export function capitalize(string) {
	return string.charAt(0).toUpperCase() + string.slice(1);
}

export function defaultValue(argType) {
	if (isEnumType(argType)) {
		return { kind: 'EnumValue', value: argType.getValues()[0].name };
	}
	switch (argType.name) {
		case 'String':
			return { kind: 'StringValue', value: '' };
		case 'Float':
			return { kind: 'FloatValue', value: '1.5' };
		case 'Int':
			return { kind: 'IntValue', value: '10' };
		case 'Boolean':
			return { kind: 'BooleanValue', value: false };
		default:
			return { kind: 'StringValue', value: '' };
	}
}

export function defaultGetDefaultScalarArgValue(parentField, arg, argType) {
	return defaultValue(argType);
}

export function defaultGetDefaultFieldNames(type) {
	const fields = type.getFields();

	if (fields.id) {
		const res = ['id'];
		if (fields.email) {
			res.push('email');
		} else if (fields.name) {
			res.push('name');
		}
		return res;
	}

	if (fields.edges) {
		return ['edges'];
	}

	if (fields.node) {
		return ['node'];
	}

	if (fields.nodes) {
		return ['nodes'];
	}

	const leafFieldNames = [];
	Object.keys(fields).forEach((fieldName) => {
		if (isLeafType(fields[fieldName].type)) {
			leafFieldNames.push(fieldName);
		}
	});

	if (!leafFieldNames.length) {
		return ['__typename'];
	}
	return leafFieldNames.slice(0, 2);
}

export function isRequiredArgument(arg) {
	return isNonNullType(arg.type) && arg.defaultValue === undefined;
}

export function unwrapOutputType(outputType) {
	let unwrappedType = outputType;
	while (isWrappingType(unwrappedType)) {
		unwrappedType = unwrappedType.ofType;
	}
	return unwrappedType;
}

export function unwrapInputType(inputType) {
	let unwrappedType = inputType;
	while (isWrappingType(unwrappedType)) {
		unwrappedType = unwrappedType.ofType;
	}
	return unwrappedType;
}

/**
 * If `inputType` is a list (optionally wrapped in non-null), return the
 * list's element type. The element type is returned with its own
 * wrappers intact so the caller can distinguish `[ID!]` from `[ID]`.
 * Returns null when the type isn't a list.
 *
 * @param {*} inputType
 */
export function getListItemType(inputType) {
	let t = inputType;
	if (isNonNullType(t)) {
		t = t.ofType;
	}
	if (isListType(t)) {
		return t.ofType;
	}
	return null;
}

/**
 * Build a default GraphQL value AST node for any input type, peeling
 * non-null and list wrappers as it goes. Used when the composer needs
 * to seed a value — e.g. when the user first checks an arg, when a new
 * list item is added, or when `Inline` rebuilds an inline structure
 * for a variable that has no captured `defaultValue`.
 *
 * @param {*}        type
 * @param {Function} getDefaultScalarArgValue
 * @param {Function} makeDefaultArg
 * @param {*}        parentField
 * @param {*}        field
 */
export function makeDefaultValueNode(
	type,
	getDefaultScalarArgValue,
	makeDefaultArg,
	parentField,
	field
) {
	if (isNonNullType(type)) {
		return makeDefaultValueNode(
			type.ofType,
			getDefaultScalarArgValue,
			makeDefaultArg,
			parentField,
			field
		);
	}
	if (isListType(type)) {
		const itemType = type.ofType;
		const item = makeDefaultValueNode(
			itemType,
			getDefaultScalarArgValue,
			makeDefaultArg,
			parentField,
			field
		);
		return {
			kind: 'ListValue',
			values: item ? [item] : [],
		};
	}
	if (isInputObjectType(type)) {
		const fields = type.getFields();
		return {
			kind: 'ObjectValue',
			fields: defaultInputObjectFields(
				getDefaultScalarArgValue,
				makeDefaultArg,
				parentField,
				Object.keys(fields).map((k) => fields[k])
			),
		};
	}
	if (isLeafType(type)) {
		return getDefaultScalarArgValue(parentField, field || { type }, type);
	}
	return null;
}

export function coerceArgValue(argType, value) {
	if (typeof value !== 'string' && value.kind === 'VariableDefinition') {
		return value.variable;
	} else if (isScalarType(argType)) {
		try {
			switch (argType.name) {
				case 'String':
					return {
						kind: 'StringValue',
						value: String(argType.parseValue(value)),
					};
				case 'Float':
					return {
						kind: 'FloatValue',
						value: String(argType.parseValue(parseFloat(value))),
					};
				case 'Int':
					return {
						kind: 'IntValue',
						value: String(argType.parseValue(parseInt(value, 10))),
					};
				case 'Boolean':
					try {
						const parsed = JSON.parse(value);
						if (typeof parsed === 'boolean') {
							return { kind: 'BooleanValue', value: parsed };
						}
						return { kind: 'BooleanValue', value: false };
					} catch (e) {
						return {
							kind: 'BooleanValue',
							value: false,
						};
					}
				default:
					return {
						kind: 'StringValue',
						value: String(argType.parseValue(value)),
					};
			}
		} catch (e) {
			console.error('error coercing arg value', e, value);
			return { kind: 'StringValue', value };
		}
	} else {
		try {
			const parsedValue = argType.parseValue(value);
			if (parsedValue) {
				return { kind: 'EnumValue', value: String(parsedValue) };
			}
			return { kind: 'EnumValue', value: argType.getValues()[0].name };
		} catch (e) {
			return { kind: 'EnumValue', value: argType.getValues()[0].name };
		}
	}
}

export function isRunShortcut(event) {
	return event.ctrlKey && event.key === 'Enter';
}

export function canRunOperation(operationName) {
	return operationName !== 'FragmentDefinition';
}

export function defaultInputObjectFields(
	getDefaultScalarArgValue,
	makeDefaultArg,
	parentField,
	fields
) {
	const nodes = [];
	for (const field of fields) {
		if (
			isRequiredInputField(field) ||
			(makeDefaultArg && makeDefaultArg(parentField, field))
		) {
			const fieldType = unwrapInputType(field.type);
			if (isInputObjectType(fieldType)) {
				const fieldMap = fieldType.getFields();
				nodes.push({
					kind: 'ObjectField',
					name: { kind: 'Name', value: field.name },
					value: {
						kind: 'ObjectValue',
						fields: defaultInputObjectFields(
							getDefaultScalarArgValue,
							makeDefaultArg,
							parentField,
							Object.keys(fieldMap).map((k) => fieldMap[k])
						),
					},
				});
			} else if (isLeafType(fieldType)) {
				nodes.push({
					kind: 'ObjectField',
					name: { kind: 'Name', value: field.name },
					value: getDefaultScalarArgValue(
						parentField,
						field,
						fieldType
					),
				});
			}
		}
	}
	return nodes;
}

export function defaultArgs(getDefaultScalarArgValue, makeDefaultArg, field) {
	const args = [];
	for (const arg of field.args) {
		if (
			isRequiredArgument(arg) ||
			(makeDefaultArg && makeDefaultArg(field, arg))
		) {
			const argType = unwrapInputType(arg.type);
			if (isInputObjectType(argType)) {
				const argFields = argType.getFields();
				args.push({
					kind: 'Argument',
					name: { kind: 'Name', value: arg.name },
					value: {
						kind: 'ObjectValue',
						fields: defaultInputObjectFields(
							getDefaultScalarArgValue,
							makeDefaultArg,
							field,
							Object.keys(argFields).map((k) => argFields[k])
						),
					},
				});
			} else if (isLeafType(argType)) {
				args.push({
					kind: 'Argument',
					name: { kind: 'Name', value: arg.name },
					value: getDefaultScalarArgValue(field, arg, argType),
				});
			}
		}
	}
	return args;
}

export function parseQuery(text) {
	try {
		if (!text.trim()) {
			return null;
		}
		// Keep locations so the composer can map editor cursor offsets back
		// to the operation that contains them (drives auto-expansion).
		return parse(text);
	} catch (e) {
		return new Error(e);
	}
}

export const DEFAULT_OPERATION = {
	kind: 'OperationDefinition',
	operation: 'query',
	variableDefinitions: [],
	name: { kind: 'Name', value: 'MyQuery' },
	directives: [],
	selectionSet: {
		kind: 'SelectionSet',
		selections: [],
	},
};

export const DEFAULT_DOCUMENT = {
	kind: 'Document',
	definitions: [DEFAULT_OPERATION],
};

let parseQueryMemoize = null;
export function memoizeParseQuery(query) {
	if (parseQueryMemoize && parseQueryMemoize[0] === query) {
		return parseQueryMemoize[1];
	}
	const result = parseQuery(query);
	if (!result) {
		return DEFAULT_DOCUMENT;
	} else if (result instanceof Error) {
		if (parseQueryMemoize) {
			return parseQueryMemoize[1];
		}
		return DEFAULT_DOCUMENT;
	}
	parseQueryMemoize = [query, result];
	return result;
}

export const defaultStyles = {
	buttonStyle: {
		fontSize: '1.2em',
		padding: '0px',
		backgroundColor: 'white',
		border: 'none',
		margin: '5px 0px',
		height: '40px',
		width: '100%',
		display: 'block',
		maxWidth: 'none',
	},

	actionButtonStyle: {
		padding: '0px',
		backgroundColor: 'white',
		border: 'none',
		margin: '0px',
		maxWidth: 'none',
		height: '15px',
		width: '15px',
		display: 'inline-block',
		fontSize: 'smaller',
	},

	explorerActionsStyle: {
		margin: '4px -8px -8px',
		paddingLeft: '8px',
		bottom: '0px',
		width: '100%',
		textAlign: 'center',
		background: 'none',
		borderTop: 'none',
		borderBottom: 'none',
	},
};
