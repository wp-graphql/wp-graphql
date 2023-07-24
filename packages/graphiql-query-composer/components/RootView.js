import { capitalize } from "../utils/utils";
import FieldView from "./FieldView";
import styled from "styled-components";
import {
  Button,
  Collapse,
  Dropdown,
  Menu,
  Popconfirm,
  notification,
  Input,
} from "antd";
import {
  CopyOutlined,
  CloseCircleOutlined,
  MoreOutlined,
} from "@ant-design/icons";

const { hooks } = wpGraphiQL;

const { useEffect } = wp.element;

const StyledRootView = styled.div`
  .ant-collapse {
    margin-bottom: 10px;
  }
  .ant-collapse-content {
    padding-left: 15px;
    padding-top: 0px;
    max-height: 450px;
    overflow-y: scroll;
  }
  .ant-collapse-content-box {
    padding: 0;
  }
`;

const RootView = (props) => {
  let _previousOperationDef;

  const { index } = props;

  const _modifySelections = (selections, options) => {
    let operationDef = props.definition;

    if (
      operationDef.selectionSet.selections.length === 0 &&
      _previousOperationDef
    ) {
      operationDef = _previousOperationDef;
    }

    let newOperationDef;

    if (operationDef.kind === "FragmentDefinition") {
      newOperationDef = {
        ...operationDef,
        selectionSet: {
          ...operationDef.selectionSet,
          selections,
        },
      };
    } else if (operationDef.kind === "OperationDefinition") {
      let cleanedSelections = selections.filter((selection) => {
        return !(
          selection.kind === "Field" && selection.name.value === "__typename"
        );
      });

      if (cleanedSelections.length === 0) {
        cleanedSelections = [
          {
            kind: "Field",
            name: {
              kind: "Name",
              value: "__typename ## Placeholder value",
            },
          },
        ];
      }

      newOperationDef = {
        ...operationDef,
        selectionSet: {
          ...operationDef.selectionSet,
          selections: cleanedSelections,
        },
      };
    }

    _previousOperationDef = newOperationDef;
    return props.onEdit(newOperationDef, options);
  };

  const _onOperationRename = (event) => {
    props.onOperationRename(event.target.value);
  };

  const _rootViewElId = () => {
    const { operationType, name } = props;
    return `${operationType}-${name || "unknown"}`;
  };

  useEffect(() => {
    // @todo: there was some funky scroll positioning happening here
    // const rootViewElId = _rootViewElId();
    // props.onMount(rootViewElId);

    notification.config({
      top: 50,
      placement: "topRight",
      getContainer: () =>
        window.document.getElementsByClassName(`doc-explorer-app`)[0],
    });
  });

  const {
    operationType,
    definition,
    schema,
    getDefaultFieldNames,
    styleConfig,
  } = props;
  const rootViewElId = _rootViewElId();

  const fields = props.fields || {};
  const selections = definition.selectionSet.selections;

  const operationDisplayName =
    props.name || `${capitalize(operationType)} Name`;

  const operationActionMenuItems = {
    clone: (
      <Menu.Item key={`clone`}>
        <Button
          type="link"
          style={{ marginRight: 5 }}
          onClick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            props.onOperationClone();
            notification.success({
              message: `${capitalize(
                operationType
              )} "${operationDisplayName}" was cloned`,
            });
          }}
          icon={<CopyOutlined />}
        >{`Clone ${capitalize(operationType)}`}</Button>
      </Menu.Item>
    ),
    destroy: (
      <Menu.Item key={`destroy`}>
        <Popconfirm
          zIndex={10000}
          getPopupContainer={() =>
            window.document.getElementsByClassName(`doc-explorer-app`)[0]
          }
          title={`Are you sure you want to delete ${capitalize(
            operationType
          )} "${operationDisplayName}"`}
          onCancel={(e) => {
            e.preventDefault();
            e.stopPropagation();
          }}
          onConfirm={(e) => {
            e.preventDefault();
            e.stopPropagation();

            // Destroy the operation
            props.onOperationDestroy();
            notification.success({
              message: `${capitalize(
                operationType
              )} "${operationDisplayName}" was deleted`,
            });
          }}
        >
          <Button
            type="link"
            onClick={(e) => {
              e.preventDefault();
              e.stopPropagation();
            }}
            icon={<CloseCircleOutlined />}
          >{`Delete ${capitalize(operationType)}`}</Button>
        </Popconfirm>
      </Menu.Item>
    ),
  };

  const filteredMenuItems = hooks.applyFilters(
    "graphiql_explorer_operation_action_menu_items",
    operationActionMenuItems,
    { Menu, props }
  );

  // If there are no actions (filtered out) don't render the menu
  if (Object.keys(filteredMenuItems).length < 1) {
    return null;
  }

  const renderMenuItems =
    filteredMenuItems && Object.keys(filteredMenuItems).length
      ? Object.keys(filteredMenuItems).map((menuItemKey) => {
          return filteredMenuItems[menuItemKey] ?? null;
        })
      : null;

  const menu = <Menu>{renderMenuItems}</Menu>;

  const operationActions = (
    <Dropdown
      overlay={menu}
      arrow
      getPopupContainer={() =>
        window.document.getElementsByClassName(`doc-explorer-app`)[0]
      }
    >
      <Button type="text" onClick={(e) => e.stopPropagation()}>
        <MoreOutlined />
      </Button>
    </Dropdown>
  );

  return (
    <StyledRootView id={`collapse-wrap-${index}`}>
      <span id={`collapse-wrap-${rootViewElId}`} />
      <Collapse
        key={`collapse-${index}`}
        id={`collapse-${index}`}
        tabIndex="0"
        defaultActiveKey={0}
      >
        <Collapse.Panel
          key={0}
          header={
            <span
              style={{
                textOverflow: `ellipsis`,
                display: `inline-block`,
                maxWidth: `100%`,
                whiteSpace: `nowrap`,
                overflow: `hidden`,
                verticalAlign: `middle`,
                fontSize: `smaller`,
                color: styleConfig.colors.keyword,
                paddingBottom: 0,
              }}
              className="graphiql-operation-title-bar"
            >
              {operationType}{" "}
              <span style={{ color: styleConfig.colors.def }}>
                <Input
                  id={`operationName-${index}`}
                  name="operation-name"
                  data-lpignore="true" // prevents last pass extension from trying to autofill
                  defaultValue={props.name ?? ""}
                  placeholder={props.name ?? "name"}
                  onChange={(e) => {
                    if (props.name === e.target.value) {
                      return;
                    }

                    // Rename the operation
                    _onOperationRename(e);
                  }}
                  value={props.name}
                  onClick={(e) => {
                    e.stopPropagation();
                    e.preventDefault();
                  }}
                  style={{
                    color: styleConfig.colors.def,
                    width: `${Math.max(15, operationDisplayName.length)}ch`,
                    fontSize: `smaller`,
                  }}
                />
              </span>
            </span>
          }
          extra={operationActions}
        >
          <div
            style={{
              padding: "10px 0 0 0",
            }}
          >
            {Object.keys(fields)
              .sort()
              .map((fieldName) => (
                <FieldView
                  key={fieldName}
                  field={fields[fieldName]}
                  selections={selections}
                  modifySelections={_modifySelections}
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
          </div>
        </Collapse.Panel>
      </Collapse>
    </StyledRootView>
  );
};

export default RootView;
