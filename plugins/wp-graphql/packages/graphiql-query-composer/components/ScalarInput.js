import { unwrapInputType } from "../utils/utils";
import { Input } from "antd";
const { useRef, useEffect } = wp.element;

const ScalarInput = (props) => {
  let input = useRef(null);

  const _handleChange = (event) => {
    props.setArgValue(event, true);
  };

  const { arg, argValue, styleConfig } = props;
  const argType = unwrapInputType(arg.type);
  const value = typeof argValue.value === "string" ? argValue.value : "";
  const color =
    props.argValue.kind === "StringValue"
      ? styleConfig.colors.string
      : styleConfig.colors.number;

  return (
    <span style={{ color }}>
      {argType.name === "String" ? '"' : ""}
      <Input
        name={arg.name}
        style={{
          width: `15ch`,
          color,
          minHeight: `16px`,
        }}
        size="small"
        ref={(node) => {
          input = node;
        }}
        type="text"
        onChange={(e) => {
          _handleChange(e);
        }}
        value={value}
      />
      {argType.name === "String" ? '"' : ""}
    </span>
  );
};

export default ScalarInput;
