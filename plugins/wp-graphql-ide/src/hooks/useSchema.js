import { useState, useEffect, useCallback, useRef } from 'react';
import { useDispatch, useSelect } from '@wordpress/data';
import { buildClientSchema, getIntrospectionQuery } from 'graphql';

/**
 * Runs schema introspection and manages the schema in the app store.
 *
 * Automatically fetches the schema on mount. Returns the current schema,
 * a loading flag, and a refetch function for invalidating and re-fetching
 * the schema (e.g. from a refresh button).
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
	const [isLoading, setIsLoading] = useState(false);

	const fetchSchema = useCallback(async () => {
		setIsLoading(true);
		try {
			const fetcherReturn = await fetcher({
				query: getIntrospectionQuery(),
			});
			// Fetchers may return the parsed body directly or a
			// { result, headers, status, size } envelope.
			const hasEnvelope =
				fetcherReturn &&
				typeof fetcherReturn === 'object' &&
				'result' in fetcherReturn &&
				'headers' in fetcherReturn;
			const result = hasEnvelope ? fetcherReturn.result : fetcherReturn;
			if (result?.data) {
				setSchema(buildClientSchema(result.data));
			}
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('Schema introspection failed:', error);
		} finally {
			setIsLoading(false);
		}
	}, [fetcher, setSchema]);

	// Auto-load schema on mount.
	const fetchSchemaRef = useRef(fetchSchema);
	fetchSchemaRef.current = fetchSchema;
	useEffect(() => {
		if (schema === undefined) {
			fetchSchemaRef.current();
		}
	}, [schema]);

	const refetch = useCallback(() => {
		setSchema(undefined);
	}, [setSchema]);

	return {
		schema,
		isLoading,
		refetch,
	};
}
