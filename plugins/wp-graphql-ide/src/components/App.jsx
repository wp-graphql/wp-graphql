import { useCallback } from 'react';
import { useDispatch, useSelect } from '@wordpress/data';
import { parse, visit } from 'graphql';
import { IDELayout } from './IDELayout';
import './ide-layout.css';

export function App() {
	const { shouldRenderStandalone, isAuthenticated } = useSelect((select) => {
		const wpgraphqlIDEApp = select('wpgraphql-ide/app');
		return {
			shouldRenderStandalone: wpgraphqlIDEApp.shouldRenderStandalone(),
			isAuthenticated: wpgraphqlIDEApp.isAuthenticated(),
		};
	});

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

			const fetchOptions = {
				method: 'POST',
				headers,
				body: JSON.stringify(graphQLParams),
				credentials,
			};

			if (options.signal) {
				fetchOptions.signal = options.signal;
			}

			const response = await fetch(graphqlEndpoint, fetchOptions);

			return response.json();
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
