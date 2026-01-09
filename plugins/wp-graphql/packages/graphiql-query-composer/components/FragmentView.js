import Checkbox from "./Checkbox";
import { Button, Tooltip } from "antd";
import { LinkOutlined } from "@ant-design/icons";

const FragmentView = (props) => {
  let _previousSelection;

  const _addFragment = () => {
    props.modifySelections([
      ...props.selections,
      _previousSelection || {
        kind: "FragmentSpread",
        name: props.fragment.name,
      },
    ]);
  };

  const _getSelection = () => {
    const selection = props.selections.find((selection) => {
      return (
        selection.kind === "FragmentSpread" &&
        selection.name.value === props.fragment.name.value
      );
    });

    return selection;
  };

  const _removeFragment = () => {
    const thisSelection = _getSelection();
    _previousSelection = thisSelection;
    props.modifySelections(
      props.selections.filter((s) => {
        const isTargetSelection =
          s.kind === "FragmentSpread" &&
          s.name.value === props.fragment.name.value;

        return !isTargetSelection;
      })
    );
  };

  const { styleConfig } = props;
  const selection = _getSelection();

  return (
    <div className={`graphiql-explorer-${props.fragment.name.value}`}>
      <span
        style={{ cursor: "pointer" }}
        onClick={selection ? _removeFragment : _addFragment}
      >
        <Checkbox checked={!!selection} styleConfig={props.styleConfig} />
        <span
          style={{ color: styleConfig.colors.def }}
          className={`graphiql-explorer-${props.fragment.name.value}`}
        >
          {props.fragment.name.value}
        </span>
        <Tooltip
          getPopupContainer={() =>
            window.document.getElementsByClassName(`doc-explorer-app`)[0]
          }
          title={`Edit the ${props.fragment.name.value} Fragment`}
        >
          <Button
            style={{
              height: `18px`,
              margin: `0px 5px`,
            }}
            title={`Edit the ${props.fragment.name.value} Fragment`}
            type="primary"
            size="small"
            onClick={(e) => {
              e.preventDefault();
              e.stopPropagation();
              const el = window.document.getElementById(
                `collapse-wrap-fragment-${props.fragment.name.value}`
              );

              // Scroll the fragment editor into view
              el &&
                el.scrollIntoView({
                  behavior: "smooth",
                  block: "start",
                  inline: "nearest",
                });
            }}
            icon={<LinkOutlined />}
          />
        </Tooltip>
      </span>
    </div>
  );
};

export default FragmentView;
