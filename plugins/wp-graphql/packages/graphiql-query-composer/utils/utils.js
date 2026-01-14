import {
  parse,
  isNonNullType,
  isLeafType,
  isWrappingType,
  isScalarType,
  isRequiredInputField,
  isInputObjectType,
  isEnumType,
} from "graphql";

import * as React from "react";

let parseQueryMemoize = null;

/**
 * Set a default operation if no operation is present in the Query document
 *
 * @type {{selectionSet: {selections: [], kind: string}, variableDefinitions: [], directives: [], kind: string, name: {kind: string, value: string}, operation: string}}
 */
const DEFAULT_OPERATION = {
  kind: "OperationDefinition",
  operation: "query",
  variableDefinitions: [],
  name: {
    kind: "Name",
    value: "NewQuery",
  },
  directives: [],
  selectionSet: {
    kind: "SelectionSet",
    selections: [],
  },
};

/**
 * Parse the GraphQL Query document
 *
 * @param query
 * @returns {Error|null|*}
 */
const parseQuery = (query) => {
  try {
    if (!query.trim()) {
      return null;
    }
    return parse(
      query,
      // Tell graphql to not bother track locations when parsing, we don't need
      // it and it's a tiny bit more expensive.
      { noLocation: true }
    );
  } catch (e) {
    return new Error(e);
  }
};

export const defaultGetDefaultFieldNames = (type) => {
  const fields = type.getFields();

  // Is there an `id` field?
  if (fields["id"]) {
    const res = ["id"];
    if (fields["email"]) {
      res.push("email");
    } else if (fields["name"]) {
      res.push("name");
    }
    return res;
  }

  // Is there an `edges` field?
  if (fields["edges"]) {
    return ["edges"];
  }

  // Is there an `node` field?
  if (fields["node"]) {
    return ["node"];
  }

  if (fields["nodes"]) {
    return ["nodes"];
  }

  // Include all leaf-type fields.
  const leafFieldNames = [];
  Object.keys(fields).forEach((fieldName) => {
    if (isLeafType(fields[fieldName].type)) {
      leafFieldNames.push(fieldName);
    }
  });

  if (!leafFieldNames.length) {
    // No leaf fields, add typename so that the query stays valid
    return ["__typename"];
  }
  return leafFieldNames.slice(0, 2); // Prevent too many fields from being added
};

export const defaultColors = {
  keyword: "#B11A04",
  // OperationName, FragmentName
  def: "#D2054E",
  // FieldName
  property: "#1F61A0",
  // FieldAlias
  qualifier: "#1C92A9",
  // ArgumentName and ObjectFieldName
  attribute: "#8B2BB9",
  number: "#2882F9",
  string: "#D64292",
  // Boolean
  builtin: "#D47509",
  // Enum
  string2: "#0B7FC7",
  variable: "#397D13",
  // Type
  atom: "#CA9800",
};

export const defaultArrowOpen = (
  <svg width="12" height="9">
    <path fill="#666" d="M 0 2 L 9 2 L 4.5 7.5 z" />
  </svg>
);

export const defaultArrowClosed = (
  <svg width="12" height="9">
    <path fill="#666" d="M 0 0 L 0 9 L 5.5 4.5 z" />
  </svg>
);

export const defaultCheckboxChecked = (
  <svg
    style={{ marginRight: "3px", marginLeft: "-3px" }}
    width="12"
    height="12"
    viewBox="0 0 18 18"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
  >
    <path
      d="M16 0H2C0.9 0 0 0.9 0 2V16C0 17.1 0.9 18 2 18H16C17.1 18 18 17.1 18 16V2C18 0.9 17.1 0 16 0ZM16 16H2V2H16V16ZM14.99 6L13.58 4.58L6.99 11.17L4.41 8.6L2.99 10.01L6.99 14L14.99 6Z"
      fill="#666"
    />
  </svg>
);

export const defaultCheckboxUnchecked = (
  <svg
    style={{ marginRight: "3px", marginLeft: "-3px" }}
    width="12"
    height="12"
    viewBox="0 0 18 18"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
  >
    <path
      d="M16 2V16H2V2H16ZM16 0H2C0.9 0 0 0.9 0 2V16C0 17.1 0.9 18 2 18H16C17.1 18 18 17.1 18 16V2C18 0.9 17.1 0 16 0Z"
      fill="#CCC"
    />
  </svg>
);

export const defaultStyles = {
  buttonStyle: {
    fontSize: "1.2em",
    padding: "0px",
    backgroundColor: "white",
    border: "none",
    margin: "5px 0px",
    height: "40px",
    width: "100%",
    display: "block",
    maxWidth: "none",
  },

  actionButtonStyle: {
    padding: "0px",
    backgroundColor: "white",
    border: "none",
    margin: "0px",
    maxWidth: "none",
    height: "15px",
    width: "15px",
    display: "inline-block",
    fontSize: "smaller",
  },

  explorerActionsStyle: {
    margin: "4px -8px -8px",
    paddingLeft: "8px",
    bottom: "0px",
    width: "100%",
    textAlign: "center",
    background: "none",
    borderTop: "none",
    borderBottom: "none",
  },
};

/**
 * Set a default query document if no operations are present in the editor
 *
 * @type {{kind: string, definitions: [{selectionSet: {selections: *[], kind: string}, variableDefinitions: *[], directives: *[], kind: string, name: {kind: string, value: string}, operation: string}]}}
 */
export const DEFAULT_DOCUMENT = {
  kind: "Document",
  definitions: [DEFAULT_OPERATION],
};

/**
 * Memoize the parsed query
 *
 * @param query
 * @returns {{kind: string, definitions: {selectionSet: {selections: *[], kind: string}, variableDefinitions: *[], directives: *[], kind: string, name: {kind: string, value: string}, operation: string}[]}|*}
 */
export const memoizeParseQuery = (query) => {

  if (parseQueryMemoize && parseQueryMemoize[0] === query) {
    return parseQueryMemoize[1];
  } else {
    const result = parseQuery(query);

    if (!result) {
      return DEFAULT_DOCUMENT;
    } else if (result instanceof Error) {
      if (parseQueryMemoize) {
        return parseQueryMemoize[1] ?? '';
      } else {
        return DEFAULT_DOCUMENT;
      }
    } else {
      parseQueryMemoize = [query, result];
      return result;
    }
  }
};

// Capitalize a string
export const capitalize = (string) => {
  return string.charAt(0).toUpperCase() + string.slice(1);
};

export const getDefaultFieldNames = (type) => {
  const fields = type.getFields();

  // Is there an `id` field?
  if (fields["id"]) {
    const res = ["id"];
    if (fields["email"]) {
      res.push("email");
    } else if (fields["name"]) {
      res.push("name");
    }
    return res;
  }

  // Is there an `edges` field?
  if (fields["edges"]) {
    return ["edges"];
  }

  // Is there an `node` field?
  if (fields["node"]) {
    return ["node"];
  }

  if (fields["nodes"]) {
    return ["nodes"];
  }

  // Include all leaf-type fields.
  const leafFieldNames = [];
  Object.keys(fields).forEach((fieldName) => {
    if (isLeafType(fields[fieldName].type)) {
      leafFieldNames.push(fieldName);
    }
  });

  if (!leafFieldNames.length) {
    // No leaf fields, add typename so that the query stays valid
    return ["__typename"];
  }
  return leafFieldNames.slice(0, 2); // Prevent too many fields from being added
};

export const isRequiredArgument = (arg) => {
  return isNonNullType(arg.type) && arg.defaultValue === undefined;
};

export const unwrapOutputType = (outputType) => {
  let unwrappedType = outputType;
  while (isWrappingType(unwrappedType)) {
    unwrappedType = unwrappedType.ofType;
  }
  return unwrappedType;
};

export const unwrapInputType = (inputType) => {
  let unwrappedType = inputType;
  while (isWrappingType(unwrappedType)) {
    unwrappedType = unwrappedType.ofType;
  }
  return unwrappedType;
};

export const coerceArgValue = (argType, value) => {
  // Handle the case where we're setting a variable as the value
  if (typeof value !== "string" && value.kind === "VariableDefinition") {
    return value.variable;
  } else if (isScalarType(argType)) {
    try {
      switch (argType.name) {
        case "String":
          return {
            kind: "StringValue",
            value: String(argType.parseValue(value)),
          };
        case "Float":
          return {
            kind: "FloatValue",
            value: String(argType.parseValue(parseFloat(value))),
          };
        case "Int":
          return {
            kind: "IntValue",
            value: String(argType.parseValue(parseInt(value, 10))),
          };
        case "Boolean":
          try {
            const parsed = JSON.parse(value);
            if (typeof parsed === "boolean") {
              return { kind: "BooleanValue", value: parsed };
            } else {
              return { kind: "BooleanValue", value: false };
            }
          } catch (e) {
            return {
              kind: "BooleanValue",
              value: false,
            };
          }
        default:
          return {
            kind: "StringValue",
            value: String(argType.parseValue(value)),
          };
      }
    } catch (e) {
      console.error("error coercing arg value", e, value);
      return { kind: "StringValue", value: value };
    }
  } else {
    try {
      const parsedValue = argType.parseValue(value);
      if (parsedValue) {
        return { kind: "EnumValue", value: String(parsedValue) };
      } else {
        return { kind: "EnumValue", value: argType.getValues()[0].name };
      }
    } catch (e) {
      return { kind: "EnumValue", value: argType.getValues()[0].name };
    }
  }
};

export const defaultInputObjectFields = (
  getDefaultScalarArgValue,
  makeDefaultArg,
  parentField,
  fields
) => {
  const nodes = [];
  for (const field of fields) {
    if (
      isRequiredInputField(field) ||
      (makeDefaultArg && makeDefaultArg(parentField, field))
    ) {
      const fieldType = unwrapInputType(field.type);
      if (isInputObjectType(fieldType)) {
        const fields = fieldType.getFields();
        nodes.push({
          kind: "ObjectField",
          name: { kind: "Name", value: field.name },
          value: {
            kind: "ObjectValue",
            fields: defaultInputObjectFields(
              getDefaultScalarArgValue,
              makeDefaultArg,
              parentField,
              Object.keys(fields).map((k) => fields[k])
            ),
          },
        });
      } else if (isLeafType(fieldType)) {
        nodes.push({
          kind: "ObjectField",
          name: { kind: "Name", value: field.name },
          value: getDefaultScalarArgValue(parentField, field, fieldType),
        });
      }
    }
  }
  return nodes;
};

export const defaultArgs = (
  getDefaultScalarArgValue,
  makeDefaultArg,
  field
) => {
  const args = [];
  for (const arg of field.args) {
    if (
      isRequiredArgument(arg) ||
      (makeDefaultArg && makeDefaultArg(field, arg))
    ) {
      const argType = unwrapInputType(arg.type);
      if (isInputObjectType(argType)) {
        const fields = argType.getFields();
        args.push({
          kind: "Argument",
          name: { kind: "Name", value: arg.name },
          value: {
            kind: "ObjectValue",
            fields: defaultInputObjectFields(
              getDefaultScalarArgValue,
              makeDefaultArg,
              field,
              Object.keys(fields).map((k) => fields[k])
            ),
          },
        });
      } else if (isLeafType(argType)) {
        args.push({
          kind: "Argument",
          name: { kind: "Name", value: arg.name },
          value: getDefaultScalarArgValue(field, arg, argType),
        });
      }
    }
  }
  return args;
};

export const defaultValue = (argType) => {
  if (isEnumType(argType)) {
    return { kind: "EnumValue", value: argType.getValues()[0].name };
  } else {
    switch (argType.name) {
      case "String":
        return { kind: "StringValue", value: "" };
      case "Float":
        return { kind: "FloatValue", value: "1.5" };
      case "Int":
        return { kind: "IntValue", value: "10" };
      case "Boolean":
        return { kind: "BooleanValue", value: false };
      default:
        return { kind: "StringValue", value: "" };
    }
  }
};

export const defaultGetDefaultScalarArgValue = (parentField, arg, argType) => {
  return defaultValue(argType);
};

export const isRunShortcut = (event) => {
  return event.ctrlKey && event.key === "Enter";
};

export const canRunOperation = (operationName) => {
  return operationName !== "FragmentDefinition";
};
