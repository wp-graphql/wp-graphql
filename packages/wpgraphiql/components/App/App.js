import Router from "../Router/Router.js";
import { useEffect, useState } from "@wordpress/element";
import { QueryParamProvider, QueryParams, StringParam } from "use-query-params";
import { getEndpoint } from "../../context/AppContext";
import { client } from "../../data/client";
import { ApolloProvider } from "@apollo/client";
const { hooks, AppContextProvider, useAppContext } = window.wpGraphiQL;

/**
 * Filter the app to allow 3rd party plugins to wrap with their own context
 */
const FilteredApp = () => {
  /**
   * Pass the AppContext down to the filter
   */
  const appContext = useAppContext();

  /**
   * Pass the router through a filter, allowing
   */
  return hooks.applyFilters("graphiql_app", <Router />, { appContext });
};


/**
 * QueryParamHistory object, used to define how
 * the UseQueryParams hook should modify browser history
 */
const queryParamHistory = {
  push: args => {
    // we replace the state to not
    // constantly add to the history for every
    // character change
    history.replaceState(null, null, args.href);
  },
  replace: args => {
    history.replaceState(null, null, args.href);
  }
}

/**
 * Return the app
 *
 * @returns
 */
export const AppWithContext = () => {
  const filteredQueryParamsConfig = hooks.applyFilters(
    "graphiql_query_params_provider_config",
    {
      query: StringParam,
      variables: StringParam,
    }
  );

  const [render, setRender] = useState(false);

  useEffect(() => {
    if (!render) {
      const container = document.getElementById("graphiql");
      if (container) {
        container.classList.remove("graphiql-container");
      }
      hooks.doAction("graphiql_rendered");
      setRender(true);
    }
  }, []);



  return render ? (
    <QueryParamProvider history={queryParamHistory}>
      <QueryParams config={filteredQueryParamsConfig}>
        {(renderProps) => {
          const { query, setQuery } = renderProps;
          return (
            <AppContextProvider queryParams={query} setQueryParams={setQuery}>
              <ApolloProvider client={client(getEndpoint())}>
                <FilteredApp />
              </ApolloProvider>
            </AppContextProvider>
          );
        }}
      </QueryParams>
    </QueryParamProvider>
  ) : null;
};

export default AppWithContext;
