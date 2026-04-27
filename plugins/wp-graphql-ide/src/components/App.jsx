import { useCallback } from 'react';
import { useDispatch, useSelect } from '@wordpress/data';
import { parse, visit } from 'graphql';
import { IDELayout } from './IDELayout';
import './ide-layout.css';

export function App() {
	const shouldRenderStandalone = useSelect(
		(select) => select('wpgraphql-ide/app').shouldRenderStandalone(),
		[]
	);
	const isAuthenticated = useSelect(
		(select) => select('wpgraphql-ide/app').isAuthenticated(),
		[]
	);

	const { setDrawerOpen } = useDispatch('wpgraphql-ide/app');

	const fetcher = useCallback(
		async (graphQLParams, options = {}) => {
			let isIntrospectionQuery = false;

			try {
				const queryAST = parse(graphQLParams.query);
				visit(queryAST, {
					Field(node) {
						if (
							node.name.value === '__schema' ||
							node.name.value === '__type'
						) {
							isIntrospectionQuery = true;
							return visit.BREAK;
						}
					},
				});
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Error parsing GraphQL query:', error);
			}

			const { graphqlEndpoint, nonce } = window.WPGRAPHQL_IDE_DATA;

			const headers = {
				'Content-Type': 'application/json',
				Accept: 'application/json',
				...(options.headers || {}),
			};

			if (nonce && (isIntrospectionQuery || isAuthenticated)) {
				headers['X-WP-Nonce'] = nonce;
			}

			let credentials = 'omit';
			if (isIntrospectionQuery || isAuthenticated) {
				credentials = 'include';
			}

			const method = options?.method || 'POST';

			let url = graphqlEndpoint;
			let fetchOptions;
			if (method === 'GET') {
				const params = new URLSearchParams();
				params.set('query', graphQLParams.query);
				if (graphQLParams.variables) {
					params.set(
						'variables',
						JSON.stringify(graphQLParams.variables)
					);
				}
				if (graphQLParams.operationName) {
					params.set('operationName', graphQLParams.operationName);
				}
				fetchOptions = {
					method: 'GET',
					headers: { ...headers },
					credentials,
				};
				// Remove Content-Type for GET requests.
				delete fetchOptions.headers['Content-Type'];
				url = `${url}?${params.toString()}`;
			} else {
				fetchOptions = {
					method: 'POST',
					headers,
					body: JSON.stringify(graphQLParams),
					credentials,
				};
			}

			if (options.signal) {
				fetchOptions.signal = options.signal;
			}

			const response = await fetch(url, fetchOptions);

			// Collect response headers as a plain object so they can be
			// displayed in the IDE's Headers tab.
			const responseHeaders = {};
			response.headers.forEach((value, key) => {
				responseHeaders[key] = value;
			});

			const status = response.status;

			// Read the body as text first so we can measure payload size
			// accurately (Content-Length isn't always present).
			const rawText = await response.text();
			const contentLength = response.headers.get('content-length');
			const size =
				contentLength !== null
					? parseInt(contentLength, 10)
					: new Blob([rawText]).size;

			// Handle non-OK responses (e.g., HTTP Auth, 500 errors).
			if (!response.ok) {
				const contentType = response.headers.get('content-type') || '';
				if (!contentType.includes('application/json')) {
					return {
						result: {
							errors: [
								{
									message: `HTTP ${response.status}: ${response.statusText}. The server returned a non-JSON response. This may be caused by HTTP authentication or a server misconfiguration.`,
								},
							],
						},
						headers: responseHeaders,
						status,
						size,
					};
				}
			}

			let result;
			try {
				result = JSON.parse(rawText);
			} catch (err) {
				result = {
					errors: [
						{
							message: `Failed to parse response as JSON: ${err.message}`,
						},
					],
				};
			}
			return { result, headers: responseHeaders, status, size };
		},
		[isAuthenticated]
	);

	return (
		<div id="wpgraphql-ide-app">
			<IDELayout
				fetcher={fetcher}
				onClose={
					!shouldRenderStandalone
						? () => setDrawerOpen(false)
						: undefined
				}
			/>
		</div>
	);
}
