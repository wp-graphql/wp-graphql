import { useCallback, useRef } from 'react';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Manages GraphQL query execution state.
 *
 * Parses variables and headers JSON, calls the fetcher, and stores the
 * response as a formatted JSON string in the app store. Supports cancellation
 * via AbortController.
 *
 * @param {Function} fetcher              - GraphQL fetcher function. Receives { query, variables, operationName }.
 * @param {Object}   [options]            - Optional configuration.
 * @param {Function} [options.onComplete] - Called after execution with { result, duration_ms, status }.
 * @return {{ isFetching: boolean, run: Function, stop: Function }}
 */
export function useExecution(fetcher, options = {}) {
	const isFetching = useSelect(
		(select) => select('wpgraphql-ide/app').isFetching(),
		[]
	);
	const query = useSelect(
		(select) => select('wpgraphql-ide/app').getQuery(),
		[]
	);
	const variables = useSelect(
		(select) => select('wpgraphql-ide/app').getVariables(),
		[]
	);
	const headers = useSelect(
		(select) => select('wpgraphql-ide/app').getHeaders(),
		[]
	);
	const httpMethod = useSelect(
		(select) => select('wpgraphql-ide/app').getHttpMethod(),
		[]
	);

	const { setResponse, setResponseHeaders, setResponseMeta, setIsFetching } =
		useDispatch('wpgraphql-ide/app');

	const abortControllerRef = useRef(null);

	const run = useCallback(
		async (operationName) => {
			// Cancel any in-flight request.
			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
			}
			const controller = new AbortController();
			abortControllerRef.current = controller;

			let parsedVariables;
			try {
				parsedVariables = variables ? JSON.parse(variables) : {};
			} catch (error) {
				setResponse(
					JSON.stringify(
						{
							errors: [
								{
									message: `Variables are not valid JSON: ${error.message}`,
								},
							],
						},
						null,
						2
					)
				);
				return;
			}

			let parsedHeaders;
			try {
				parsedHeaders = headers ? JSON.parse(headers) : {};
			} catch (error) {
				setResponse(
					JSON.stringify(
						{
							errors: [
								{
									message: `Headers are not valid JSON: ${error.message}`,
								},
							],
						},
						null,
						2
					)
				);
				return;
			}

			setIsFetching(true);
			const startTime = Date.now();
			let status = 'success';
			try {
				const fetcherReturn = await fetcher(
					{ query, variables: parsedVariables, operationName },
					{
						headers: parsedHeaders,
						signal: controller.signal,
						method: httpMethod,
					}
				);
				// Support both new envelope shape and legacy fetchers that
				// return the parsed result directly.
				const hasEnvelope =
					fetcherReturn &&
					typeof fetcherReturn === 'object' &&
					'result' in fetcherReturn &&
					'headers' in fetcherReturn;
				const result = hasEnvelope
					? fetcherReturn.result
					: fetcherReturn;
				const responseHeaders = hasEnvelope
					? fetcherReturn.headers
					: null;
				const httpStatus = hasEnvelope
					? (fetcherReturn.status ?? null)
					: null;
				const responseSize = hasEnvelope
					? (fetcherReturn.size ?? null)
					: null;
				const duration = Date.now() - startTime;

				setResponse(JSON.stringify(result, null, 2));
				setResponseHeaders(responseHeaders);
				setResponseMeta({
					status: httpStatus,
					duration,
					size: responseSize,
				});

				if (result?.errors) {
					status = 'error';
				}

				if (options.onComplete) {
					options.onComplete({
						result,
						duration_ms: duration,
						status,
						variables: variables || '',
						responseHeaders,
						httpStatus,
						responseSize,
					});
				}
			} catch (error) {
				if (error.name === 'AbortError') {
					return;
				}
				const errorResponse = {
					errors: [{ message: error.message }],
				};
				const duration = Date.now() - startTime;
				setResponse(JSON.stringify(errorResponse, null, 2));
				setResponseHeaders(null);
				setResponseMeta({
					status: null,
					duration,
					size: null,
				});

				if (options.onComplete) {
					options.onComplete({
						result: errorResponse,
						duration_ms: duration,
						status: 'error',
						variables: variables || '',
						responseHeaders: null,
						httpStatus: null,
						responseSize: null,
					});
				}
			} finally {
				if (abortControllerRef.current === controller) {
					abortControllerRef.current = null;
					setIsFetching(false);
				}
			}
		},
		[
			fetcher,
			query,
			variables,
			headers,
			httpMethod,
			setResponse,
			setResponseHeaders,
			setResponseMeta,
			setIsFetching,
			options,
		]
	);

	const stop = useCallback(() => {
		if (abortControllerRef.current) {
			abortControllerRef.current.abort();
			abortControllerRef.current = null;
			setIsFetching(false);
		}
	}, [setIsFetching]);

	return { isFetching, run, stop };
}
