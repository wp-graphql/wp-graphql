import { unwrapInputType, isRequiredArgument } from "../utils/utils";
const {
  isEnumType,
  isInputObjectType,
  isScalarType,
  parseType,
  visit,
} = wpGraphiQL.GraphQL;
import ScalarInput from "./ScalarInput";
import InputArgView from "./InputArgView";
import Checkbox from "./Checkbox";
import { Button, Select, Tooltip } from "antd";

const { useState } = wp.element;

/**
 * This view is used
 * @param props
 * @returns {JSX.Element}
 * @constructor
 */
const AbstractArgView = (props) => {
  const [displayArgActions, setDisplayArgActions] = useState(true);
  const { definition } = props;

  const { argValue, arg, styleConfig } = props;
  const argType = unwrapInputType(arg.type);
  let input = null;

  if (argValue) {
    if (argValue.kind === "Variable") {
      input = (
        <span style={{ color: styleConfig.colors.variable }}>
          ${argValue.name.value}
        </span>
      );
    } else if (isScalarType(argType)) {
      if (argType.name === "Boolean") {
        input = (
          <Select
            getPopupContainer={() =>
              window.document.getElementsByClassName("doc-explorer-app")[0]
            }
            size="small"
            style={{
              color: styleConfig.colors.builtin,
              minHeight: "16px",
              minWidth: `16ch`,
            }}
            onChange={(value) => {
              const event = {
                target: {
                  value,
                },
              };
              props.setArgValue(event);
            }}
            value={
              argValue.kind === "BooleanValue" ? argValue.value : undefined
            }
          >
            <Select.Option key="true" value="true">
              true
            </Select.Option>
            <Select.Option key="false" value="false">
              false
            </Select.Option>
          </Select>
        );
      } else {
        input = (
          <ScalarInput
            setArgValue={props.setArgValue}
            arg={arg}
            argValue={argValue}
            onRunOperation={props.onRunOperation}
            styleConfig={props.styleConfig}
          />
        );
      }
    } else if (isEnumType(argType)) {
      if (argValue.kind === "EnumValue") {
        input = (
          <Select
            size="small"
            getPopupContainer={() =>
              window.document.getElementsByClassName("doc-explorer-app")[0]
            }
            style={{
              backgroundColor: "white",
              minHeight: "16px",
              minWidth: `20ch`,
              color: styleConfig.colors.string2,
            }}
            onChange={(value) => {
              const event = {
                target: {
                  value,
                },
              };
              props.setArgValue(event);
            }}
            value={argValue.value}
          >
            {argType.getValues().map((value, i) => (
              <Select.Option key={i} value={value.name}>
                {value.name}
              </Select.Option>
            ))}
          </Select>
        );
      } else {
        console.error(
          "arg mismatch between arg and selection",
          argType,
          argValue
        );
      }
    } else if (isInputObjectType(argType)) {
      if (argValue.kind === "ObjectValue") {
        const fields = argType.getFields();
        input = (
          <div style={{ marginLeft: 16 }}>
            {Object.keys(fields)
              .sort()
              .map((fieldName) => (
                <InputArgView
                  key={fieldName}
                  arg={fields[fieldName]}
                  parentField={props.parentField}
                  selection={argValue}
                  modifyFields={props.setArgFields}
                  getDefaultScalarArgValue={props.getDefaultScalarArgValue}
                  makeDefaultArg={props.makeDefaultArg}
                  onRunOperation={props.onRunOperation}
                  styleConfig={props.styleConfig}
                  onCommit={props.onCommit}
                  definition={props.definition}
                />
              ))}
          </div>
        );
      } else {
        console.error(
          "arg mismatch between arg and selection",
          argType,
          argValue
        );
      }
    }
  }

  const variablize = () => {
    /**
         1. Find current operation variables
         2. Find current arg value
         3. Create a new variable
         4. Replace current arg value with variable
         5. Add variable to operation
         */

    const baseVariableName = arg.name;
    const conflictingNameCount = (
      props.definition.variableDefinitions || []
    ).filter((varDef) =>
      varDef.variable.name.value.startsWith(baseVariableName)
    ).length;

    let variableName;
    if (conflictingNameCount > 0) {
      variableName = `${baseVariableName}${conflictingNameCount}`;
    } else {
      variableName = baseVariableName;
    }
    // To get an AST definition of our variable from the instantiated arg,
    // we print it to a string, then parseType to get our AST.
    const argPrintedType = arg.type.toString();
    const argType = parseType(argPrintedType);

    const base = {
      kind: "VariableDefinition",
      variable: {
        kind: "Variable",
        name: {
          kind: "Name",
          value: variableName,
        },
      },
      type: argType,
      directives: [],
    };

    const variableDefinitionByName = (name) =>
      (props.definition.variableDefinitions || []).find(
        (varDef) => varDef.variable.name.value === name
      );

    let variable;
    let subVariableUsageCountByName = {};

    if (typeof argValue !== "undefined" && argValue !== null) {
      /** In the process of devariabilizing descendent selections,
       * we may have caused their variable definitions to become unused.
       * Keep track and remove any variable definitions with 1 or fewer usages.
       * */
      const cleanedDefaultValue = visit(argValue, {
        Variable(node) {
          const varName = node.name.value;
          const varDef = variableDefinitionByName(varName);

          subVariableUsageCountByName[varName] =
            subVariableUsageCountByName[varName] + 1 || 1;

          if (!varDef) {
            return;
          }

          return varDef.defaultValue;
        },
      });

      const isNonNullable = base.type.kind === "NonNullType";

      // We're going to give the variable definition a default value, so we must make its type nullable
      const unwrappedBase = isNonNullable
        ? { ...base, type: base.type.type }
        : base;

      variable = { ...unwrappedBase, defaultValue: cleanedDefaultValue };
    } else {
      variable = base;
    }

    const newlyUnusedVariables = Object.entries(subVariableUsageCountByName)
      // $FlowFixMe: Can't get Object.entries to realize usageCount *must* be a number
      .filter(([_, usageCount]) => usageCount < 2)
      .map(([varName, _]) => varName);

    if (variable) {
      const newDoc = props.setArgValue(variable, false);

      if (newDoc) {
        const targetOperation = newDoc.definitions.find((definition) => {
          if (
            !!definition.operation &&
            !!definition.name &&
            !!definition.name.value &&
            //
            !!props.definition.name &&
            !!props.definition.name.value
          ) {
            return definition.name.value === props.definition.name.value;
          } else {
            return false;
          }
        });

        const newVariableDefinitions = [
          ...(targetOperation?.variableDefinitions || []),
          variable,
        ].filter(
          (varDef) =>
            newlyUnusedVariables.indexOf(varDef.variable.name.value) === -1
        );

        const newOperation = {
          ...targetOperation,
          variableDefinitions: newVariableDefinitions,
        };

        const existingDefs = newDoc.definitions;

        const newDefinitions = existingDefs.map((existingOperation) => {
          if (targetOperation === existingOperation) {
            return newOperation;
          } else {
            return existingOperation;
          }
        });

        const finalDoc = {
          ...newDoc,
          definitions: newDefinitions,
        };

        props.onCommit(finalDoc);
      }
    }
  };

  const devariablize = () => {
    /**
     * 1. Find the current variable definition in the operation def
     * 2. Extract its value
     * 3. Replace the current arg value
     * 4. Visit the resulting operation to see if there are any other usages of the variable
     * 5. If not, remove the variableDefinition
     */
    if (!argValue || !argValue.name || !argValue.name.value) {
      return;
    }

    const variableName = argValue.name.value;
    const variableDefinition = (
      props.definition.variableDefinitions || []
    ).find((varDef) => varDef.variable.name.value === variableName);

    if (!variableDefinition) {
      return;
    }

    const defaultValue = variableDefinition.defaultValue;

    const newDoc = props.setArgValue(defaultValue, {
      commit: false,
    });

    if (newDoc) {
      const targetOperation = newDoc.definitions.find(
        (definition) => definition.name.value === props.definition.name.value
      );

      if (!targetOperation) {
        return;
      }

      // After de-variabilizing, see if the variable is still in use. If not, remove it.
      let variableUseCount = 0;

      visit(targetOperation, {
        Variable(node) {
          if (node.name.value === variableName) {
            variableUseCount = variableUseCount + 1;
          }
        },
      });

      let newVariableDefinitions = targetOperation.variableDefinitions || [];

      // A variable is in use if it shows up at least twice (once in the definition, once in the selection)
      if (variableUseCount < 2) {
        newVariableDefinitions = newVariableDefinitions.filter(
          (varDef) => varDef.variable.name.value !== variableName
        );
      }

      const newOperation = {
        ...targetOperation,
        variableDefinitions: newVariableDefinitions,
      };

      const existingDefs = newDoc.definitions;

      const newDefinitions = existingDefs.map((existingOperation) => {
        if (targetOperation === existingOperation) {
          return newOperation;
        } else {
          return existingOperation;
        }
      });

      const finalDoc = {
        ...newDoc,
        definitions: newDefinitions,
      };

      props.onCommit(finalDoc);
    }
  };

  const isArgValueVariable = argValue && argValue.kind === "Variable";

  // If the query definition doesn't have a name, we can't properly variablize it
  // as variables require a named query
  const variablizeActionButton =
    definition.name === undefined || !argValue ? null : (
      <Tooltip
        getPopupContainer={() =>
          window.document.getElementsByClassName(`doc-explorer-app`)[0]
        }
        title={
          isArgValueVariable
            ? "Remove the variable"
            : "Extract the current value into a GraphQL variable"
        }
      >
        <Button
          type={isArgValueVariable ? "danger" : "default"}
          size="small"
          className="toolbar-button"
          title={
            isArgValueVariable
              ? "Remove the variable"
              : "Extract the current value into a GraphQL variable"
          }
          onClick={(event) => {
            event.preventDefault();
            event.stopPropagation();

            if (isArgValueVariable) {
              devariablize();
            } else {
              variablize();
            }
          }}
        >
          <span
            style={{
              color: !isArgValueVariable
                ? styleConfig.colors.variable
                : "inherit",
            }}
          >
            {"$"}
          </span>
        </Button>
      </Tooltip>
    );

  return (
    <div
      style={{
        cursor: "pointer",
        minHeight: "20px",
        WebkitUserSelect: "none",
        userSelect: "none",
      }}
      data-arg-name={arg.name}
      data-arg-type={argType.name}
      className={`graphiql-explorer-${arg.name}`}
    >
      <span
        style={{ cursor: "pointer" }}
        onClick={(event) => {
          const shouldAdd = !argValue;
          if (shouldAdd) {
            props.addArg(true);
          } else {
            props.removeArg(true);
          }
          setDisplayArgActions(shouldAdd);
        }}
      >
        {isInputObjectType(argType) ? (
          <span>
            {argValue
              ? props.styleConfig.arrowOpen
              : props.styleConfig.arrowClosed}
          </span>
        ) : (
          <Checkbox checked={!!argValue} styleConfig={props.styleConfig} />
        )}
        <span
          style={{ color: styleConfig.colors.attribute }}
          title={arg.description}
          onMouseEnter={() => {
            // Make implementation a bit easier and only show 'variablize' action if arg is already added
            if (argValue !== null && typeof argValue !== "undefined") {
              setDisplayArgActions(true);
            }
          }}
          onMouseLeave={() => setDisplayArgActions(true)}
        >
          {arg.name}
          {isRequiredArgument(arg) ? "*" : ""}: {variablizeActionButton}{" "}
        </span>{" "}
      </span>
      {input || <span />}{" "}
    </div>
  );
};

export default AbstractArgView;
