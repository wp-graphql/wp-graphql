/**
 * Sends requests as a public visitor, resending with HTTP auth and
 * `PUBLIC_REQUEST_HEADER` when the server challenges, because `fetch`
 * cannot send Basic Auth without the WordPress cookie. The header has
 * `Access::force_public_request()` run the request as a guest.
 */

export const PUBLIC_REQUEST_HEADER = 'X-WPGraphQL-IDE-Public';

// Once the endpoint has challenged us, skip the doomed `omit` attempt
// for the rest of the page's lifetime.
let httpAuthRequired = false;

/**
 * Whether a response is the web server asking for HTTP authentication.
 *
 * @param {Response} response
 * @return {boolean} True for a 401 that carries a challenge.
 */
function isHttpAuthChallenge(response) {
	return response.status === 401 && response.headers.has('www-authenticate');
}

/**
 * Send a request as a public (logged-out) visitor.
 *
 * @param {string} url     Request URL.
 * @param {Object} options `fetch` options. `credentials` is ignored.
 * @return {Promise<Response>} The response.
 */
export async function fetchAsPublic(url, options = {}) {
	if (!httpAuthRequired) {
		const response = await fetch(url, { ...options, credentials: 'omit' });
		if (!isHttpAuthChallenge(response)) {
			return response;
		}
		httpAuthRequired = true;
	}

	return fetch(url, {
		...options,
		credentials: 'same-origin',
		headers: { ...options.headers, [PUBLIC_REQUEST_HEADER]: '1' },
	});
}
