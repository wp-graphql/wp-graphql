import {
  unwrapInputType,
  defaultInputObjectFields,
  coerceArgValue,
} from "../utils/utils";
const { isInputObjectType, isLeafType } = wpGraphiQL.GraphQL;
import AbstractArgView from "./AbstractArgView";

const { useState } = wp.element;

const InputArgView = (props) => {
  let _previousArgSelection;

  const _getArgSelection = () => {
    return props.selection.fields.find(
      (field) => field.name.value === props.arg.name
    );
  };

  const _removeArg = () => {
    const { selection } = props;
    const argSelection = _getArgSelection();
    _previousArgSelection = argSelection;
    props.modifyFields(
      selection.fields.filter((field) => field !== argSelection),
      true
    );
  };

  const _addArg = () => {
    const {
      selection,
      arg,
      getDefaultScalarArgValue,
      parentField,
      makeDefaultArg,
    } = props;
    const argType = unwrapInputType(arg.type);

    let argSelection = null;
    if (_previousArgSelection) {
      argSelection = _previousArgSelection;
    } else if (isInputObjectType(argType)) {
      const fields = argType.getFields();
      argSelection = {
        kind: "ObjectField",
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
        kind: "ObjectField",
        name: { kind: "Name", value: arg.name },
        value: getDefaultScalarArgValue(parentField, arg, argType),
      };
    }

    if (!argSelection) {
      console.error("Unable to add arg for argType", argType);
    } else {
      return props.modifyFields(
        [...(selection.fields || []), argSelection],
        true
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

    if (!argSelection) {
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
      console.warn(
        "Unable to handle non leaf types in InputArgView.setArgValue",
        event
      );
      return;
    }
    let targetValue;
    let value;

    if (event === null || typeof event === "undefined") {
      value = null;
    } else if (
      !event.target &&
      !!event.kind &&
      event.kind === "VariableDefinition"
    ) {
      targetValue = event;
      value = targetValue.variable;
    } else if (typeof event.kind === "string") {
      value = event;
    } else if (event.target && typeof event.target.value === "string") {
      targetValue = event.target.value;
      value = coerceArgValue(argType, targetValue);
    }

    const newDoc = props.modifyFields(
      (selection.fields || []).map((field) => {
        const isTarget = field === argSelection;
        const newField = isTarget
          ? {
              ...field,
              value: value,
            }
          : field;

        return newField;
      }),
      options
    );

    return newDoc;
  };

  const _modifyChildFields = (fields) => {
    return props.modifyFields(
      props.selection.fields.map((field) =>
        field.name.value === props.arg.name
          ? {
              ...field,
              value: {
                kind: "ObjectValue",
                fields: fields,
              },
            }
          : field
      ),
      true
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
      setArgFields={_modifyChildFields}
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

export default InputArgView;
