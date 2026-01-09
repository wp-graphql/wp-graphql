const { isInputObjectType, isLeafType } = wpGraphiQL.GraphQL;
import {
  coerceArgValue,
  defaultInputObjectFields,
  unwrapInputType,
} from "../utils/utils";
import AbstractArgView from "./AbstractArgView";

const ArgView = (props) => {
  let _previousArgSelection;
  const _getArgSelection = () => {
    const { selection } = props;
    return (selection.arguments || []).find(
      (arg) => arg.name.value === props.arg.name
    );
  };

  const _removeArg = (commit) => {
    const { selection } = props;
    const argSelection = _getArgSelection();
    _previousArgSelection = _getArgSelection();
    return props.modifyArguments(
      (selection.arguments || []).filter((arg) => arg !== argSelection),
      commit
    );
  };

  const _addArg = (commit) => {
    const {
      selection,
      getDefaultScalarArgValue,
      makeDefaultArg,
      parentField,
      arg,
    } = props;
    const argType = unwrapInputType(arg.type);

    let argSelection = null;
    if (_previousArgSelection) {
      argSelection = _previousArgSelection;
    } else if (isInputObjectType(argType)) {
      const fields = argType.getFields();
      argSelection = {
        kind: "Argument",
        name: { kind: "Name", value: arg.name },
        value: {
          kind: "ObjectValue",
          fields: defaultInputObjectFields(
            getDefaultScalarArgValue,
            makeDefaultArg,
            parentField,
            Object.keys(fields).map((k) => fields[k])
          ),
        },
      };
    } else if (isLeafType(argType)) {
      argSelection = {
        kind: "Argument",
        name: { kind: "Name", value: arg.name },
        value: getDefaultScalarArgValue(parentField, arg, argType),
      };
    }

    if (!argSelection) {
      console.error("Unable to add arg for argType", argType);
      return null;
    } else {
      return props.modifyArguments(
        [...(selection.arguments || []), argSelection],
        commit
      );
    }
  };

  const _setArgValue = (event, options) => {
    let settingToNull = false;
    let settingToVariable = false;
    let settingToLiteralValue = false;
    try {
      if (event.kind === "VariableDefinition") {
        settingToVariable = true;
      } else if (event === null || typeof event === "undefined") {
        settingToNull = true;
      } else if (typeof event.kind === "string") {
        settingToLiteralValue = true;
      }
    } catch (e) {}
    const { selection } = props;
    const argSelection = _getArgSelection();
    if (!argSelection && !settingToVariable) {
      console.error("missing arg selection when setting arg value");
      return;
    }
    const argType = unwrapInputType(props.arg.type);

    const handleable =
      isLeafType(argType) ||
      settingToVariable ||
      settingToNull ||
      settingToLiteralValue;

    if (!handleable) {
      console.warn("Unable to handle non leaf types in ArgView._setArgValue");
      return;
    }

    let targetValue;
    let value;

    if (event === null || typeof event === "undefined") {
      value = null;
    } else if (event.target && typeof event.target.value === "string") {
      targetValue = event.target.value;
      value = coerceArgValue(argType, targetValue);
    } else if (!event.target && event.kind === "VariableDefinition") {
      targetValue = event;
      value = targetValue.variable;
    } else if (typeof event.kind === "string") {
      value = event;
    }

    return props.modifyArguments(
      (selection.arguments || []).map((a) =>
        a === argSelection
          ? {
              ...a,
              value: value,
            }
          : a
      ),
      options
    );
  };

  const _setArgFields = (fields, commit) => {
    const { selection } = props;
    const argSelection = _getArgSelection();
    if (!argSelection) {
      console.error("missing arg selection when setting arg value");
      return;
    }

    return props.modifyArguments(
      (selection.arguments || []).map((a) =>
        a === argSelection
          ? {
              ...a,
              value: {
                kind: "ObjectValue",
                fields,
              },
            }
          : a
      ),
      commit
    );
  };

  const { arg, parentField } = props;
  const argSelection = _getArgSelection();

  return (
    <AbstractArgView
      argValue={argSelection ? argSelection.value : null}
      arg={arg}
      parentField={parentField}
      addArg={_addArg}
      removeArg={_removeArg}
      setArgFields={_setArgFields}
      setArgValue={_setArgValue}
      getDefaultScalarArgValue={props.getDefaultScalarArgValue}
      makeDefaultArg={props.makeDefaultArg}
      onRunOperation={props.onRunOperation}
      styleConfig={props.styleConfig}
      onCommit={props.onCommit}
      definition={props.definition}
    />
  );
};

export default ArgView;
