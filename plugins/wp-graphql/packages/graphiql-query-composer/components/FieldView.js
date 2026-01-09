import { Button, Tooltip } from "antd";
import { defaultArgs, unwrapOutputType } from "../utils/utils";
import Checkbox from "./Checkbox";
import ArgView from "./ArgView";
import FragmentView from "./FragmentView";
import AbstractView from "./AbstractView";
import { EllipsisOutlined } from "@ant-design/icons";

const {
  getNamedType,
  isInterfaceType,
  isObjectType,
  isUnionType,
} = wpGraphiQL.GraphQL;

const { useState } = wp.element;

const FieldView = (props) => {
  const [displayFieldActions, setDisplayFieldActions] = useState(false);

  let _previousSelection;

  const _addAllFieldsToSelections = (rawSubfields) => {
    const subFields = !!rawSubfields
      ? Object.keys(rawSubfields).map((fieldName) => {
          return {
            kind: "Field",
            name: { kind: "Name", value: fieldName },
            arguments: [],
          };
        })
      : [];

    const subSelectionSet = {
      kind: "SelectionSet",
      selections: subFields,
    };

    const nextSelections = [
      ...props.selections.filter((selection) => {
        if (selection.kind === "InlineFragment") {
          return true;
        } else {
          // Remove the current selection set for the target field
          return selection.name.value !== props.field.name;
        }
      }),
      {
        kind: "Field",
        name: { kind: "Name", value: props.field.name },
        arguments: defaultArgs(
          props.getDefaultScalarArgValue,
          props.makeDefaultArg,
          props.field
        ),
        selectionSet: subSelectionSet,
      },
    ];

    props.modifySelections(nextSelections);
  };

  const _addFieldToSelections = (rawSubfields) => {
    const nextSelections = [
      ...props.selections,
      _previousSelection || {
        kind: "Field",
        name: { kind: "Name", value: props.field.name },
        arguments: defaultArgs(
          props.getDefaultScalarArgValue,
          props.makeDefaultArg,
          props.field
        ),
      },
    ];

    props.modifySelections(nextSelections);
  };

  const _handleUpdateSelections = (event) => {
    const selection = _getSelection();
    if (selection && !event.altKey) {
      _removeFieldFromSelections();
    } else {
      const fieldType = getNamedType(props.field.type);
      const rawSubfields = isObjectType(fieldType) && fieldType.getFields();
      const shouldSelectAllSubfields = !!rawSubfields && event.altKey;

      shouldSelectAllSubfields
        ? _addAllFieldsToSelections(rawSubfields)
        : _addFieldToSelections(rawSubfields);
    }
  };

  const _removeFieldFromSelections = () => {
    const previousSelection = _getSelection();
    _previousSelection = previousSelection;
    props.modifySelections(
      props.selections.filter((selection) => selection !== previousSelection)
    );
  };

  const _getSelection = () => {
    const selection = props.selections.find(
      (selection) =>
        selection.kind === "Field" && props.field.name === selection.name.value
    );
    if (!selection) {
      return null;
    }
    if (selection.kind === "Field") {
      return selection;
    }
  };

  const _setArguments = (argumentNodes, options) => {
    const selection = _getSelection();
    if (!selection) {
      console.error("Missing selection when setting arguments", argumentNodes);
      return;
    }
    return props.modifySelections(
      props.selections.map((s) =>
        s === selection
          ? {
              alias: selection.alias,
              arguments: argumentNodes,
              directives: selection.directives,
              kind: "Field",
              name: selection.name,
              selectionSet: selection.selectionSet,
            }
          : s
      ),
      options
    );
  };

  const _modifyChildSelections = (selections, options) => {
    return props.modifySelections(
      props.selections.map((selection) => {
        if (
          selection.kind === "Field" &&
          props.field.name === selection.name.value
        ) {
          if (selection.kind !== "Field") {
            throw new Error("invalid selection");
          }
          return {
            alias: selection.alias,
            arguments: selection.arguments,
            directives: selection.directives,
            kind: "Field",
            name: selection.name,
            selectionSet: {
              kind: "SelectionSet",
              selections,
            },
          };
        }
        return selection;
      }),
      options
    );
  };

  const { field, schema, getDefaultFieldNames, styleConfig } = props;
  const selection = _getSelection();
  const type = unwrapOutputType(field.type);
  const args = field.args.sort((a, b) => a.name.localeCompare(b.name));
  let className = `graphiql-explorer-node graphiql-explorer-${field.name}`;

  if (field.isDeprecated) {
    className += " graphiql-explorer-deprecated";
  }

  const applicableFragments =
    isObjectType(type) || isInterfaceType(type) || isUnionType(type)
      ? props.availableFragments && props.availableFragments[type.name]
      : null;

  const childSelections = selection
    ? selection.selectionSet
      ? selection.selectionSet.selections
      : []
    : [];

  const node = (
    <div className={className}>
      <span
        title={field.description}
        style={{
          cursor: "pointer",
          display: "inline-flex",
          alignItems: "center",
          minHeight: "16px",
          WebkitUserSelect: "none",
          userSelect: "none",
        }}
        data-field-name={field.name}
        data-field-type={type.name}
        onClick={_handleUpdateSelections}
        onMouseEnter={() => {
          const containsMeaningfulSubselection =
            isObjectType(type) &&
            selection &&
            selection.selectionSet &&
            selection.selectionSet.selections.filter(
              (selection) => selection.kind !== "FragmentSpread"
            ).length > 0;

          if (containsMeaningfulSubselection) {
            setDisplayFieldActions(true);
          }
        }}
        onMouseLeave={() => setDisplayFieldActions(false)}
      >
        {isObjectType(type) ? (
          <span>
            {!!selection
              ? props.styleConfig.arrowOpen
              : props.styleConfig.arrowClosed}
          </span>
        ) : null}
        {isObjectType(type) ? null : (
          <Checkbox checked={!!selection} styleConfig={props.styleConfig} />
        )}
        <span
          style={{ color: styleConfig.colors.property }}
          className="graphiql-explorer-field-view"
        >
          {field.name}
        </span>
        {!displayFieldActions ? null : (
          <Tooltip
            getPopupContainer={() =>
              window.document.getElementsByClassName(`doc-explorer-app`)[0]
            }
            title={`Extract selections into a new reusable fragment`}
          >
            <Button
              size="small"
              type="primary"
              title="Extract selections into a new reusable fragment"
              onClick={(event) => {
                event.preventDefault();
                event.stopPropagation();
                // 1. Create a fragment spread node
                // 2. Copy selections from this object to fragment
                // 3. Replace selections in this object with fragment spread
                // 4. Add fragment to document
                const typeName = type.name;
                let newFragmentName = `${typeName}Fragment`;

                const conflictingNameCount = (applicableFragments || []).filter(
                  (fragment) => {
                    return fragment.name.value.startsWith(newFragmentName);
                  }
                ).length;

                if (conflictingNameCount > 0) {
                  newFragmentName = `${newFragmentName}${conflictingNameCount}`;
                }

                const nextSelections = [
                  {
                    kind: "FragmentSpread",
                    name: {
                      kind: "Name",
                      value: newFragmentName,
                    },
                    directives: [],
                  },
                ];

                const newFragmentDefinition = {
                  kind: "FragmentDefinition",
                  name: {
                    kind: "Name",
                    value: newFragmentName,
                  },
                  typeCondition: {
                    kind: "NamedType",
                    name: {
                      kind: "Name",
                      value: type.name,
                    },
                  },
                  directives: [],
                  selectionSet: {
                    kind: "SelectionSet",
                    selections: childSelections,
                  },
                };

                const newDoc = _modifyChildSelections(nextSelections, false);

                if (newDoc) {
                  const newDocWithFragment = {
                    ...newDoc,
                    definitions: [...newDoc.definitions, newFragmentDefinition],
                  };

                  props.onCommit(newDocWithFragment);
                } else {
                  console.warn("Unable to complete extractFragment operation");
                }
                setDisplayFieldActions(false);
              }}
              icon={<EllipsisOutlined />}
              style={{
                height: `18px`,
                margin: `0px 5px`,
              }}
            />
          </Tooltip>
        )}
      </span>
      {selection && args.length ? (
        <div
          style={{ marginLeft: 16 }}
          className="graphiql-explorer-graphql-arguments"
        >
          {args.map((arg) => (
            <ArgView
              key={arg.name}
              parentField={field}
              arg={arg}
              selection={selection}
              modifyArguments={_setArguments}
              getDefaultScalarArgValue={props.getDefaultScalarArgValue}
              makeDefaultArg={props.makeDefaultArg}
              onRunOperation={props.onRunOperation}
              styleConfig={props.styleConfig}
              onCommit={props.onCommit}
              definition={props.definition}
            />
          ))}
        </div>
      ) : null}
    </div>
  );

  if (selection) {
    const unwrappedType = unwrapOutputType(type);

    const fields =
      unwrappedType && "getFields" in unwrappedType
        ? unwrappedType.getFields()
        : null;

    // If the current field has nested fields
    if (fields) {
      return (
        <div className={`graphiql-explorer-${field.name}`}>
          {node}
          <div style={{ marginLeft: 16 }}>
            {!!applicableFragments
              ? applicableFragments.map((fragment) => {
                  const type = schema.getType(
                    fragment.typeCondition.name.value
                  );
                  const fragmentName = fragment.name.value;
                  return !type ? null : (
                    <FragmentView
                      key={fragmentName}
                      fragment={fragment}
                      selections={childSelections}
                      modifySelections={_modifyChildSelections}
                      schema={schema}
                      styleConfig={props.styleConfig}
                      onCommit={props.onCommit}
                    />
                  );
                })
              : null}
            {Object.keys(fields)
              .sort()
              .map((fieldName) => (
                <FieldView
                  key={fieldName}
                  field={fields[fieldName]}
                  selections={childSelections}
                  modifySelections={_modifyChildSelections}
                  schema={schema}
                  getDefaultFieldNames={getDefaultFieldNames}
                  getDefaultScalarArgValue={props.getDefaultScalarArgValue}
                  makeDefaultArg={props.makeDefaultArg}
                  onRunOperation={props.onRunOperation}
                  styleConfig={props.styleConfig}
                  onCommit={props.onCommit}
                  definition={props.definition}
                  availableFragments={props.availableFragments}
                />
              ))}
            {isInterfaceType(type) || isUnionType(type)
              ? schema
                  .getPossibleTypes(type)
                  .map((type) => (
                    <AbstractView
                      key={type.name}
                      implementingType={type}
                      selections={childSelections}
                      modifySelections={_modifyChildSelections}
                      schema={schema}
                      getDefaultFieldNames={getDefaultFieldNames}
                      getDefaultScalarArgValue={props.getDefaultScalarArgValue}
                      makeDefaultArg={props.makeDefaultArg}
                      onRunOperation={props.onRunOperation}
                      styleConfig={props.styleConfig}
                      onCommit={props.onCommit}
                      definition={props.definition}
                    />
                  ))
              : null}
          </div>
        </div>
      );
    } else if (isUnionType(type)) {
      return (
        <div className={`graphiql-explorer-${field.name}`}>
          {node}
          <div style={{ marginLeft: 16 }}>
            {schema.getPossibleTypes(type).map((type) => (
              <AbstractView
                key={type.name}
                implementingType={type}
                selections={childSelections}
                modifySelections={_modifyChildSelections}
                schema={schema}
                getDefaultFieldNames={getDefaultFieldNames}
                getDefaultScalarArgValue={props.getDefaultScalarArgValue}
                makeDefaultArg={props.makeDefaultArg}
                onRunOperation={props.onRunOperation}
                styleConfig={props.styleConfig}
                onCommit={props.onCommit}
                definition={props.definition}
              />
            ))}
          </div>
        </div>
      );
    }
  }
  return node;
};

export default FieldView;
