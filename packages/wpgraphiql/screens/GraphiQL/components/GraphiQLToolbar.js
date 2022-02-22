import { GraphiQL } from "graphiql";
import { useGraphiQLContext } from "../context/GraphiQLContext";
const { hooks } = wpGraphiQL;

/**
 * GraphiQLToolbar
 *
 * This is the toolbar component that loads buttons that can
 * interact with GraphiQL
 *
 * Default Buttons:
 *
 * - Prettify
 * - History
 *
 * @param props
 */
const GraphiQLToolbar = (props) => {
  const { graphiql } = props;

  // Configure initial buttons to load into the Toolbar
  let defaultButtonsConfig = [
    {
      label: `Prettify`,
      title: `Prettify Query (Shift-Ctrl-P)`,
      onClick: (GraphiQL) => {
        GraphiQL().handlePrettifyQuery();
      },
    },
    {
      label: `History`,
      title: `Show History`,
      onClick: (GraphiQL) => {
        GraphiQL().handleToggleHistory();
      },
    },
  ];

  const graphiqlContext = useGraphiQLContext();

  // Setup the context to pass to the filters
  const filterContext = {
    ...props,
    ...{ GraphiQL, graphiqlContext },
  };

  // Allows the toolbar buttons to be filtered.
  const buttonsConfig = hooks.applyFilters(
    "graphiql_toolbar_buttons",
    defaultButtonsConfig,
    filterContext
  );

  // Provides a filterable area before the toolbar buttons
  const beforeToolbarButtons = hooks.applyFilters(
    "graphiql_toolbar_before_buttons",
    [],
    filterContext
  );

  // Provides a filterable area after the toolbar buttons
  const afterToolbarButtons = hooks.applyFilters(
    "graphiql_toolbar_after_buttons",
    [],
    filterContext
  );

  // Return the toolbar
  return (
    <div data-testid="graphiql-toolbar" style={{ display: "flex" }}>
      {
        // returns any components that were filtered in before the buttons
        beforeToolbarButtons.length > 0 ? beforeToolbarButtons : null
      }

      {
        // Iterates over the filtered buttons config and returns
        // the buttons
        buttonsConfig &&
          buttonsConfig.length &&
          buttonsConfig.map((button, i) => {
            const { label, title, onClick } = button;
            return (
              <GraphiQL.Button
                data-testid={label}
                key={i}
                onClick={() => {
                  onClick(graphiql);
                }}
                label={label}
                title={title}
              />
            );
          })
      }

      {
        // returns any components that were were filtered after the buttons
        afterToolbarButtons.length > 0 ? afterToolbarButtons : null
      }
    </div>
  );
};

export default GraphiQLToolbar;
