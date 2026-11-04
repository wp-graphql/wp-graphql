import { useState, useEffect, useCallback, useRef } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { buildClientSchema, getIntrospectionQuery } from 'graphql';
import hooks from '../wordpress-hooks';
import { isUserLoggedIn } from '../bootstrap';

// One id for the notice a failed load raises, so a load that fails again
// replaces it instead of stacking another, and a load that succeeds can
// dismiss it.
const SCHEMA_LOAD_ERROR_NOTICE_ID = 'wpgraphql-ide-schema-load-error';

/**
 * Runs schema introspection and manages the schema in the app store.
 *
 * Auto-fetches once on mount if no schema is loaded yet. After that, the
 * caller drives refetches by invoking the returned `refetch` — same pattern
 * Apollo, GraphiQL, and urql expose. The schema stays in place during a
 * refetch (stale-while-revalidate), so the Docs Explorer doesn't flicker.
 * `load` runs the same fetch and also raises an error notice when it fails,
 * and any fetch that succeeds dismisses that notice.
 *
 * @param {Function} fetcher - GraphQL fetcher function. Receives { query }.
 * @return {{
 *   schema: Object|undefined,
 *   isLoading: boolean,
 *   refetch: () => Promise<{ ok: boolean, error?: Error }>,
 *   load: () => Promise<{ ok: boolean, error?: Error }>
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
				// Errors surface to the user via the Refresh Schema notice
				// (see registry/index.js), so they need translation. Server
				// error messages pass through verbatim — they're a transport
				// detail not under our control.
				throw new Error(
					result.errors[0].message ||
						__('Introspection returned errors.', 'wpgraphql-ide')
				);
			}
			if (!result?.data) {
				throw new Error(
					__('Introspection returned no data.', 'wpgraphql-ide')
				);
			}

			setSchema(buildClientSchema(result.data));
			hooks.doAction(
				'wpgraphql-ide.notice.dismiss',
				SCHEMA_LOAD_ERROR_NOTICE_ID
			);
			return { ok: true };
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('Schema introspection failed:', error);
			return { ok: false, error };
		} finally {
			setIsLoading(false);
		}
	}, [fetcher, setSchema]);

	// Loads the IDE starts on its own (on mount, and when a query runs
	// before a schema has loaded) have no caller to report a failure to,
	// so raise the error notice here. The Refresh Schema button raises
	// its own (registry/index.js).
	const load = useCallback(async () => {
		const result = await fetchSchema();
		if (!result.ok) {
			// Signed-out visitors, as on the public IDE, get a general
			// message: with public introspection off, the raw error would
			// only be the introspection rule's. The schema load always
			// sends the nonce (App.jsx), whatever the auth toggle says, so
			// a signed-in user keeps the detailed message in public mode.
			let message = __(
				"The schema couldn't be loaded. If you think this is a mistake, contact the site administrator.",
				'wpgraphql-ide'
			);
			if (isUserLoggedIn) {
				message = sprintf(
					/* translators: %s: error message from the GraphQL schema introspection */
					__('Failed to load schema: %s', 'wpgraphql-ide'),
					result.error?.message ??
						__('Unknown error', 'wpgraphql-ide')
				);
			}
			hooks.doAction(
				'wpgraphql-ide.notice',
				{ id: SCHEMA_LOAD_ERROR_NOTICE_ID, content: message },
				'error'
			);
		}
		return result;
	}, [fetchSchema]);

	// Kick off the first fetch on mount if the store is empty. The
	// `hasMounted` ref makes this run exactly once for the lifetime of
	// the component. Later loads go through `load` (the retry in
	// IDELayout.jsx when a query runs without a schema) or `refetch`
	// (the Refresh Schema button).
	const hasMounted = useRef(false);
	const loadRef = useRef(load);
	loadRef.current = load;

	useEffect(() => {
		if (hasMounted.current) {
			return;
		}
		hasMounted.current = true;
		if (schema === undefined) {
			loadRef.current();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	return {
		schema,
		isLoading,
		refetch: fetchSchema,
		load,
	};
}
