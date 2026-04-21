import { useEffect, useCallback, useRef } from 'react';
import { useDispatch, useSelect } from '@wordpress/data';
import { buildClientSchema, getIntrospectionQuery } from 'graphql';

/**
 * Runs schema introspection and manages the schema in the app store.
 *
 * Returns the current schema, a loading flag, and a refetch function for
 * invalidating and re-fetching the schema (e.g. from a refresh button).
 *
 * @param {Function} fetcher - GraphQL fetcher function. Receives { query }.
 * @return {{ schema: Object|undefined, isLoading: boolean, refetch: Function }}
 */
export function useSchema(fetcher) {
	const schema = useSelect(
		(select) => select('wpgraphql-ide/app').schema(),
		[]
	);

	const { setSchema } = useDispatch('wpgraphql-ide/app');

	const fetchSchema = useCallback(async () => {
		try {
			const result = await fetcher({
				query: getIntrospectionQuery(),
			});
			if (result?.data) {
				// Defer buildClientSchema to avoid blocking the main thread
				// on large schemas. This yields to the browser between the
				// network response and the CPU-intensive schema building.
				setTimeout(() => {
					try {
						setSchema(buildClientSchema(result.data));
					} catch (buildError) {
						// eslint-disable-next-line no-console
						console.error('Schema build failed:', buildError);
					}
				}, 0);
			}
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('Schema introspection failed:', error);
		}
	}, [fetcher, setSchema]);

	// Only load schema when explicitly requested (refresh button or first Send).
	// Auto-loading on mount caused browser freezes on large schemas.
	const hasRequestedRef = useRef(false);
	useEffect(() => {
		if (schema === undefined && hasRequestedRef.current) {
			fetchSchema();
		}
	}, [schema, fetchSchema]);

	const refetch = useCallback(() => {
		hasRequestedRef.current = true;
		setSchema(undefined);
	}, [setSchema]);

	return {
		schema,
		isLoading: schema === undefined,
		refetch,
	};
}
