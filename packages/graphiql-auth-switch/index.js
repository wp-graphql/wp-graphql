import {
  AuthSwitchProvider,
  useAuthSwitchContext,
} from "./AuthSwitchContext";
import AuthSwitch from "./AuthSwitch";
const { hooks, useAppContext } = window.wpGraphiQL;

/**
 * Returns a public fetcher with no credentials/nonce used
 *
 * @param endpoint
 * @returns {function(*=): Promise<*>}
 */
export const getPublicFetcher = (endpoint) => {
  return (params) => {
    const headers = {
      Accept: "application/json",
      "content-type": "application/json",
    };

    const fetchParams = {
      method: "POST",
      headers,
      body: JSON.stringify(params),
      credentials: "omit", // Omitting credentials prevents the cookie from being sent
    };

    return fetch(endpoint, fetchParams).then((res) => {
      return res.json();
    });
  };
};

/**
 * Filter the fetcher that's used by GraphiQL to return a public fetcher
 * if the AuthSwitch is toggled to execute as a public user
 */
hooks.addFilter("graphiql_fetcher", "graphiql-auth-switch", (res, props) => {
  const { usePublicFetcher } = useAuthSwitchContext();
  const { endpoint } = useAppContext();

  if (usePublicFetcher) {
    return getPublicFetcher(endpoint);
  }

  return res;
});

/**
 * Wrap the App with the AuthSwitchProvider
 */
hooks.addFilter("graphiql_app", "graphiql-auth", (app) => {
  return <AuthSwitchProvider>{app}</AuthSwitchProvider>;
});

/**
 * Hook into the Toolbar to add the AuthSwitch button, which allows users to toggle
 * between executing GraphQL queries as a public and authenticated user.
 */
hooks.addFilter(
  "graphiql_toolbar_before_buttons",
  "graphiql-auth-switch",
  (res) => {
    res.push(<AuthSwitch key="auth-switch" />);
    return res;
  },
  1
);
