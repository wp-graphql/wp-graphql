import Explorer from "./components/Explorer";

const { hooks } = window.wpGraphiQL;
import {
  ExplorerContext,
  ExplorerProvider,
  useExplorer,
} from "./components/ExplorerContext";
import { BooleanParam, StringParam } from "use-query-params";
import "./index.scss";

/**
 * Hook into the GraphiQL Toolbar to add the button to toggle the Explorer
 */
hooks.addFilter(
  "graphiql_toolbar_after_buttons",
  "graphiql-extension",
  (res, props) => {
    const { GraphiQL } = props;

    const { toggleExplorer } = useExplorer();

    res.push(
      <ExplorerContext.Consumer key="graphiql-query-composer-button">
        {(context) => {
          return (
            <GraphiQL.Button
              onClick={() => {
                // Toggle the state of the explorer context
                toggleExplorer();
              }}
              label="Query Composer"
              title="Query Composer"
            />
          );
        }}
      </ExplorerContext.Consumer>
    );

    return res;
  }
);

/**
 * Add the Explorer panel before GraphiQL
 */
hooks.addFilter(
  "graphiql_before_graphiql",
  "graphiql-explorer",
  (res, props) => {
    res.push(<Explorer {...props} key="graphiql-explorer" />);
    return res;
  }
);

// /**
//  * Wrap the GraphiQL App with the explorer context
//  */
hooks.addFilter(
  "graphiql_app",
  "graphiql-explorer",
  (app, { appContext }) => {
    return <ExplorerProvider appContext={appContext}>{app}</ExplorerProvider>;
  },
  99
);

hooks.addFilter(
  "graphiql_query_params_provider_config",
  "graphiql-explorer",
  (config) => {
    return {
      ...config,
      ...{
        isQueryComposerOpen: BooleanParam,
        explorerIsOpen: StringParam,
      },
    };
  }
);
