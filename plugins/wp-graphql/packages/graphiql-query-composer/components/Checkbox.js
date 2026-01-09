const Checkbox = (props) => {
  return props.checked
    ? props.styleConfig.checkboxChecked
    : props.styleConfig.checkboxUnchecked;
};

export default Checkbox;
