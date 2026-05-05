/**
 * Minimal client for talking to the site's WPGraphQL endpoint from
 * inside the IDE. Wraps `fetch` with the nonce and credentials handling
 * everyone forgets about, so callsites can just say
 * `await gql(query, variables)` and get parsed JSON.
 *
 * @since x-release-please-version
 */

/**
 * GraphQL transport / protocol error.
 *
 * Carries the underlying `errors` array and the HTTP status when one is
 * available so callers can branch on auth (401/403), rate limit (429),
 * etc. without re-walking response.errors. The first error message is
 * also surfaced as `error.message` for the common case where a caller
 * just wants to render a single string.
 *
 * @since x-release-please-version
 */
export class GraphQLClientError extends Error {
	constructor(message, { status = 0, errors = [] } = {}) {
		super(message);
		this.name = 'GraphQLClientError';
		this.status = status;
		this.errors = errors;
	}
}

function isAuthError(status) {
	return status === 401 || status === 403;
}

/**
 * Send a GraphQL request to the WPGraphQL endpoint configured in the
 * IDE's bootstrap data.
 *
 * Throws on:
 *   - missing endpoint configuration
 *   - transport failures (network down, CORS, etc.)
 *   - HTTP errors (including 401/403 — these are not silently swallowed)
 *   - GraphQL-level errors in the response body
 *
 * Errors are thrown as `GraphQLClientError` so callers can inspect the
 * HTTP status (`error.status`) and the full `errors` array
 * (`error.errors`) without re-parsing the response. The first GraphQL
 * error message is mirrored on `error.message` for the common single-
 * string render path.
 *
 * @since x-release-please-version
 *
 * @param {string}      query            GraphQL document.
 * @param {Object}      [variables]      Variables for the query.
 * @param {Object}      [options]        Request options.
 * @param {AbortSignal} [options.signal] Abort signal for cancellation.
 * @return {Promise<Object>} Resolves with the `data` field of the response.
 */
export async function gql(query, variables = {}, options = {}) {
	const data = window.WPGRAPHQL_IDE_DATA || {};
	const endpoint = data.graphqlEndpoint;
	if (!endpoint) {
		throw new GraphQLClientError('GraphQL endpoint is not configured.');
	}

	const response = await fetch(endpoint, {
		method: 'POST',
		credentials: 'include',
		signal: options.signal,
		headers: {
			'Content-Type': 'application/json',
			Accept: 'application/json',
			...(data.nonce ? { 'X-WP-Nonce': data.nonce } : {}),
		},
		body: JSON.stringify({ query, variables }),
	});

	if (!response.ok) {
		// Surface auth failures distinctly so callers can show "Sign in
		// again" instead of a generic "Request failed". Non-auth errors
		// fall through with the status preserved.
		const message = isAuthError(response.status)
			? `Authentication required (${response.status}).`
			: `GraphQL request failed: ${response.status}`;
		throw new GraphQLClientError(message, { status: response.status });
	}

	let body;
	try {
		body = await response.json();
	} catch (parseError) {
		throw new GraphQLClientError(
			'Could not parse GraphQL response as JSON.',
			{ status: response.status }
		);
	}

	if (Array.isArray(body.errors) && body.errors.length > 0) {
		throw new GraphQLClientError(
			body.errors[0].message || 'GraphQL error',
			{ status: response.status, errors: body.errors }
		);
	}
	return body.data;
}
