/**
 * Returns the authenticated fetcher
 *
 * @param endpoint
 * @param options
 * @returns {function(*=): Promise<*>}
 */
export const getFetcher = (endpoint, options) => {
  const { nonce } = options;

  return (params) => {
    const headers = {
      Accept: "application/json",
      "content-type": "application/json",
      "X-WP-Nonce": nonce,
    };

    const fetchParams = {
      method: "POST",
      headers,
      body: JSON.stringify(params),
      credentials: "include",
    };

    return fetch(endpoint, fetchParams).then((res) => {
      return res.json();
    });
  };
};
