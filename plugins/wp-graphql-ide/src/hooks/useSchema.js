import { useState, useEffect, useCallback, useRef } from 'react';
import { useDispatch, useSelect } from '@wordpress/data';
import { buildClientSchema, getIntrospectionQuery } from 'graphql';

/**
 * Runs schema introspection and manages the schema in the app store.
 *
 * Auto-fetches once on mount if no schema is loaded yet. After that, the
 * caller drives refetches by invoking the returned `refetch` — same pattern
 * Apollo, GraphiQL, and urql expose. The schema stays in place during a
 * refetch (stale-while-revalidate), so the Docs Explorer doesn't flicker.
 *
 * @param {Function} fetcher - GraphQL fetcher function. Receives { query }.
 * @return {{
 *   schema: Object|undefined,
 *   isLoading: boolean,
 *   refetch: () => Promise<{ ok: boolean, error?: Error }>
 * }}
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

			if (result?.errors?.length) {
				throw new Error(
					result.errors[0].message || 'Introspection returned errors.'
				);
			}
			if (!result?.data) {
				throw new Error('Introspection returned no data.');
			}

			setSchema(buildClientSchema(result.data));
			return { ok: true };
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('Schema introspection failed:', error);
			return { ok: false, error };
		} finally {
			setIsLoading(false);
		}
	}, [fetcher, setSchema]);

	// Kick off the first fetch on mount if the store is empty. The
	// `hasMounted` ref makes this run exactly once for the lifetime of
	// the component — subsequent loads go through `refetch`.
	const hasMounted = useRef(false);
	const fetchSchemaRef = useRef(fetchSchema);
	fetchSchemaRef.current = fetchSchema;

	useEffect(() => {
		if (hasMounted.current) {
			return;
		}
		hasMounted.current = true;
		if (schema === undefined) {
			fetchSchemaRef.current();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	return {
		schema,
		isLoading,
		refetch: fetchSchema,
	};
}
