/**
 * Minimal client for talking to the site's WPGraphQL endpoint from
 * inside the IDE. Wraps `fetch` with the nonce and credentials handling
 * everyone forgets about, so callsites can just say
 * `await gql(query, variables)` and get parsed JSON.
 *
 * Throws on transport failures, HTTP errors, or GraphQL-level errors.
 * The first GraphQL error message bubbles up so callers can surface it
 * without re-walking the errors array.
 */

export async function gql(query, variables = {}) {
	const data = window.WPGRAPHQL_IDE_DATA || {};
	const endpoint = data.graphqlEndpoint;
	if (!endpoint) {
		throw new Error('GraphQL endpoint is not configured.');
	}

	const response = await fetch(endpoint, {
		method: 'POST',
		credentials: 'include',
		headers: {
			'Content-Type': 'application/json',
			Accept: 'application/json',
			...(data.nonce ? { 'X-WP-Nonce': data.nonce } : {}),
		},
		body: JSON.stringify({ query, variables }),
	});

	if (!response.ok) {
		throw new Error(`GraphQL request failed: ${response.status}`);
	}

	const body = await response.json();
	if (Array.isArray(body.errors) && body.errors.length > 0) {
		throw new Error(body.errors[0].message || 'GraphQL error');
	}
	return body.data;
}
