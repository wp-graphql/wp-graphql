import {
  useContext,
  createContext,
  useState,
} from "@wordpress/element";
import { hooks } from "../index";

export const AppContext = createContext();
export const useAppContext = () => useContext(AppContext);

/**
 * Get the endpoint from the localized settings provided by WordPress when it enqueues the app
 * @returns
 */
export const getEndpoint = () => {
  return window?.wpGraphiQLSettings?.graphqlEndpoint ?? null;
};

/**
 * Get the nonce from the localized settings provided by WordPress when it enqueues the app
 *
 * @returns
 */
export const getNonce = () => {
  return window?.wpGraphiQLSettings?.nonce ?? null;
};

/**
 * AppContextProvider
 *
 * This provider maintains context useful for the entire application.
 *
 * @param {*} param0
 * @returns
 */
export const AppContextProvider = ({
  children,
  setQueryParams,
  queryParams,
}) => {
  const [schema, setSchema] = useState(null);
  const [nonce, setNonce] = useState(getNonce());
  const [endpoint, setEndpoint] = useState(getEndpoint());
  const [_queryParams, _setQueryParams] = useState(queryParams);

  const updateQueryParams = (newQueryParams) => {
    _setQueryParams(newQueryParams);
    setQueryParams(newQueryParams);
  };

  let appContextValue = {
    endpoint,
    setEndpoint,
    nonce,
    setNonce,
    schema,
    setSchema,
    queryParams: _queryParams,
    setQueryParams: updateQueryParams,
  };

  let filteredAppContextValue = hooks.applyFilters(
    "graphiql_app_context",
    appContextValue
  );

  return (
    <AppContext.Provider value={filteredAppContextValue}>
      {children}
    </AppContext.Provider>
  );
};
