import { createContext, useState, useContext } from "@wordpress/element";
import LZString from "lz-string";
import { getExternalFragments } from "../utils/externalFragments";
import { parse, print } from "graphql/index";
const { hooks, useAppContext } = window.wpGraphiQL;

/**
 * Set the Fallback Query should GraphiQL fail to load a good initial query from localStorage or
 * url params
 *
 * @type {string}
 */
export const FALLBACK_QUERY = `# Welcome to GraphiQL
 #
 # GraphiQL is an in-browser tool for writing, validating, and
 # testing GraphQL queries.
 #
 # Type queries into this side of the screen, and you will see intelligent
 # typeaheads aware of the current GraphQL type schema and live syntax and
 # validation errors highlighted within the text.
 #
 # GraphQL queries typically start with a "{" character. Lines that starts
 # with a # are ignored.
 #
 # An example GraphQL query might look like:
 #
 query GetPosts {
   posts {
     nodes {
       id
       title
       date
     }    
   }
 }
 #
 # Keyboard shortcuts:
 #
 #  Prettify Query:  Shift-Ctrl-P (or press the prettify button above)
 #
 #  Run Query:  Ctrl-Enter (or press the play button above)
 #
 #  Auto Complete:  Ctrl-Space (or just start typing)
 #
 `;

export const GraphiQLContext = createContext();
export const useGraphiQLContext = () => useContext(GraphiQLContext);
export const GraphiQLContextProvider = ({ children }) => {
  const { queryParams, setQueryParams } = useAppContext();

  const getDefaultQuery = () => {
    let defaultQuery = '';
    let queryUrlParam = queryParams.query ?? null;

    if (queryUrlParam) {
      defaultQuery = LZString.decompressFromEncodedURIComponent(queryUrlParam);

      // if it's null, it's not an encoded query, but a string query, i.e. {posts{nodes{id}}}
      if (null === defaultQuery) {
        defaultQuery = queryUrlParam;
      }
    }

    try {
      defaultQuery = print(parse(defaultQuery));
    } catch (e) {
      defaultQuery =
        window?.localStorage?.getItem("graphiql:query") ?? null
    }

    return defaultQuery;
  };

  const defaultVariables =
    (window && window?.localStorage?.getItem("graphiql:variables")) ?? null;
  const [query, setQuery] = useState(getDefaultQuery());
  const [variables, setVariables] = useState(defaultVariables);
  const [externalFragments, setExternalFragments] = useState(
    getExternalFragments()
  );

  const _updateVariables = (variables) => {
    if (window && window.localStorage) {
      window.localStorage.setItem("graphiql:variables", variables);
    }
    setVariables(variables);
  };

  const _updateQuery = async (newQuery) => {
      const currentQuery = query;
    hooks.doAction("graphiql_update_query", { currentQuery, newQuery });

    let update = false;
    let encoded;
    let decoded;

    if (null !== newQuery && newQuery === query) {
      return;
    }

    if (null === newQuery || "" === newQuery) {
      update = true;
    } else {
      decoded = LZString.decompressFromEncodedURIComponent(newQuery);
      // the newQuery is not encoded, lets encode it now
      if (null === decoded) {
        // Encode the query
        encoded = LZString.compressToEncodedURIComponent(newQuery);
      } else {
        encoded = newQuery;
      }

      try {
        parse(newQuery);
        update = true;
      } catch (e) {
        console.warn({
          error: {
            e,
            newQuery,
          },
        });
        return;
      }
    }

    if (!update) {
      return;
    }

    // Store the query to localStorage
    if (window && window.localStorage && "" !== newQuery && null !== newQuery) {
      window?.localStorage.setItem("graphiql:query", newQuery);
    }

    const newQueryParams = { ...queryParams, query: encoded };

    if (JSON.stringify(newQueryParams !== JSON.stringify(queryParams))) {
      setQueryParams(newQueryParams);
    }

    if (currentQuery !== newQuery) {
      await setQuery(newQuery);
    }
  };

  // Filter the context values
  const context = hooks.applyFilters("graphiql_context_default_value", {
    query,
    setQuery: _updateQuery,
    variables,
    setVariables: _updateVariables,
    externalFragments,
    setExternalFragments,
  });

  return (
    <GraphiQLContext.Provider value={context}>
      {children}
    </GraphiQLContext.Provider>
  );
};
