import { ApolloClient, InMemoryCache, HttpLink } from '@apollo/client';
import { getNonce } from '../context/AppContext';

/**
 * Create an Apollo Client with proper authentication headers.
 *
 * The client includes:
 * - credentials: 'include' to send cookies for authentication
 * - X-WP-Nonce header for CSRF protection
 *
 * This ensures the introspection query and other GraphQL requests
 * are properly authenticated when the user is logged into WordPress.
 *
 * @param {string} uri The GraphQL endpoint URL
 * @return {ApolloClient} Configured Apollo Client instance
 */
export const client = (uri) => {
	const nonce = getNonce();

	const httpLink = new HttpLink({
		uri,
		credentials: 'include',
		headers: nonce
			? {
					'X-WP-Nonce': nonce,
				}
			: {},
	});

	return new ApolloClient({
		link: httpLink,
		connectToDevTools: true,
		cache: new InMemoryCache({}),
	});
};
