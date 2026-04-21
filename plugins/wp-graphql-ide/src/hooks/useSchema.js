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
				setSchema(buildClientSchema(result.data));
			}
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('Schema introspection failed:', error);
		}
	}, [fetcher, setSchema]);

	// Schema is loaded on demand — user clicks refresh or first Send.
	// Auto-loading on mount blocks the main thread on large schemas.
	const fetchSchemaRef = useRef(fetchSchema);
	fetchSchemaRef.current = fetchSchema;
	const hasRequestedSchema = useRef(false);
	useEffect(() => {
		if (schema === undefined && hasRequestedSchema.current) {
			fetchSchemaRef.current();
		}
	}, [schema]);

	const refetch = useCallback(() => {
		hasRequestedSchema.current = true;
		setSchema(undefined);
	}, [setSchema]);

	return {
		schema,
		isLoading: schema === undefined,
		refetch,
	};
}
