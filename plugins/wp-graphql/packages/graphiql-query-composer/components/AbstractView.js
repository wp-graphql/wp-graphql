import Checkbox from "./Checkbox";
import FieldView from "./FieldView";

const AbstractView = (props) => {
  let _previousSelection;

  const _addFragment = () => {
    props.modifySelections([
      ...props.selections,
      _previousSelection || {
        kind: "InlineFragment",
        typeCondition: {
          kind: "NamedType",
          name: { kind: "Name", value: props.implementingType.name },
        },
        selectionSet: {
          kind: "SelectionSet",
          selections: props
            .getDefaultFieldNames(props.implementingType)
            .map((fieldName) => ({
              kind: "Field",
              name: { kind: "Name", value: fieldName },
            })),
        },
      },
    ]);
  };

  const _getSelection = () => {
    const selection = props.selections.find(
      (selection) =>
        selection.kind === "InlineFragment" &&
        selection.typeCondition &&
        props.implementingType.name === selection.typeCondition.name.value
    );

    if (!selection) {
      return null;
    }
    if (selection.kind === "InlineFragment") {
      return selection;
    }
  };

  const _removeFragment = () => {
    const thisSelection = _getSelection();
    _previousSelection = thisSelection;
    props.modifySelections(props.selections.filter((s) => s !== thisSelection));
  };

  const _modifyChildSelections = (selections, options) => {
    const thisSelection = _getSelection();
    return props.modifySelections(
      props.selections.map((selection) => {
        if (selection === thisSelection) {
          return {
            directives: selection.directives,
            kind: "InlineFragment",
            typeCondition: {
              kind: "NamedType",
              name: { kind: "Name", value: props.implementingType.name },
            },
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

  const { implementingType, schema, getDefaultFieldNames, styleConfig } = props;

  const selection = _getSelection();
  const fields = implementingType.getFields();
  const childSelections = selection
    ? selection.selectionSet
      ? selection.selectionSet.selections
      : []
    : [];

  return (
    <div className={`graphiql-explorer-${implementingType.name}`}>
      <span
        style={{ cursor: "pointer" }}
        onClick={selection ? _removeFragment : _addFragment}
      >
        <Checkbox checked={!!selection} styleConfig={props.styleConfig} />
        <span style={{ color: styleConfig.colors.atom }}>
          {props.implementingType.name}
        </span>
      </span>
      {selection ? (
        <div style={{ marginLeft: 16 }}>
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
                onCommit={props.onCommit}
                styleConfig={props.styleConfig}
                definition={props.definition}
                availableFragments={props.availableFragments}
              />
            ))}
        </div>
      ) : null}
    </div>
  );
};

export default AbstractView;
