import { useEffect, useCallback } from 'react';
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
				setSchema(buildClientSchema(result.data));
			}
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('Schema introspection failed:', error);
		}
	}, [fetcher, setSchema]);

	// Run introspection when schema is undefined (initial load or after invalidation).
	useEffect(() => {
		if (schema === undefined) {
			fetchSchema();
		}
	}, [schema, fetchSchema]);

	const refetch = useCallback(() => {
		setSchema(undefined);
	}, [setSchema]);

	return {
		schema,
		isLoading: schema === undefined,
		refetch,
	};
}
