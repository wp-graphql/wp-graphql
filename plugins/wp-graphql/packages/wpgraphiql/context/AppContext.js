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
/**
 * Detects if there's a URL mismatch between the current page and the GraphQL endpoint.
 *
 * This commonly happens with local development tools like LocalWP that serve WordPress
 * from a custom domain but the user accesses it via localhost with a port.
 *
 * @param {string} endpoint The GraphQL endpoint URL
 * @returns {object|null} Object with mismatch details, or null if no mismatch
 */
const detectUrlMismatch = (endpoint) => {
  if (!endpoint) return null;

  try {
    const endpointUrl = new URL(endpoint);
    const currentUrl = new URL(window.location.href);

    // Check if origins differ
    if (endpointUrl.origin !== currentUrl.origin) {
      return {
        currentOrigin: currentUrl.origin,
        endpointOrigin: endpointUrl.origin,
      };
    }
  } catch {
    // URL parsing failed
    return null;
  }

  return null;
};

/**
 * Adjusts the endpoint URL to use the current page's origin if there's a mismatch.
 *
 * This fixes issues with local development tools (like LocalWP) where WordPress
 * is configured for a custom domain but accessed via localhost with a port.
 * By using the current origin, cookies and nonces will work correctly.
 *
 * @param {string} endpoint The original GraphQL endpoint URL
 * @returns {string} The adjusted endpoint URL using the current origin
 */
const getAdjustedEndpoint = (endpoint) => {
  if (!endpoint) return endpoint;

  try {
    const endpointUrl = new URL(endpoint);
    const currentUrl = new URL(window.location.href);

    // If origins match, return the original endpoint
    if (endpointUrl.origin === currentUrl.origin) {
      return endpoint;
    }

    // Replace the endpoint's origin with the current page's origin
    // This preserves the pathname and query string (e.g., /index.php?graphql)
    const adjustedUrl = new URL(endpointUrl.pathname + endpointUrl.search, currentUrl.origin);
    return adjustedUrl.toString();
  } catch {
    // If URL parsing fails, return the original endpoint
    return endpoint;
  }
};

export const AppContextProvider = ({
  children,
  setQueryParams,
  queryParams,
}) => {
  // Get the original endpoint and detect any URL mismatch
  const originalEndpoint = getEndpoint();
  const urlMismatch = detectUrlMismatch(originalEndpoint);

  // Use the adjusted endpoint that matches the current origin
  // This ensures cookies and nonces work correctly with LocalWP and similar tools
  const adjustedEndpoint = getAdjustedEndpoint(originalEndpoint);

  const [schema, setSchema] = useState(null);
  const [schemaLoading, setSchemaLoading] = useState(true);
  const [schemaError, setSchemaError] = useState(null);
  const [nonce, setNonce] = useState(getNonce());
  const [endpoint, setEndpoint] = useState(adjustedEndpoint);
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
    schemaLoading,
    setSchemaLoading,
    schemaError,
    setSchemaError,
    urlMismatch,
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
