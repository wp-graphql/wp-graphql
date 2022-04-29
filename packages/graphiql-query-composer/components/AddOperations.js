import { Button, Form } from "antd";

/**
 * Add Operations (queries, mutations, subscriptions) to the Query Builder
 *
 * @param props
 * @returns {JSX.Element}
 * @constructor
 */
const AddOperations = (props) => {
  const { actionOptions, addOperation } = props;

  const height = actionOptions.length * 45;

  return (
    <>
      <div
        style={{
          padding: `10px 10px 0 10px`,
          borderTop: `1px solid #ccc`,
          overflowY: `hidden`,
          minHeight: `${height}px`,
        }}
      >
        <Form
          name="add-graphql-operation"
          className="variable-editor-title graphiql-explorer-actions"
          layout="inline"
          onSubmit={(event) => event.preventDefault()}
        >
          {actionOptions.map((action, i) => {
            const { type } = action;

            return (
              <Button
                key={i}
                style={{ marginBottom: `5px`, textTransform: `capitalize` }}
                block
                type="primary"
                onClick={() => addOperation(type)}
              >
                Add New {type}
              </Button>
            );
          })}
        </Form>
      </div>
    </>
  );
};

export default AddOperations;
