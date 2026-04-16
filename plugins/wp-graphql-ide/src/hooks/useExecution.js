import { useCallback, useRef } from 'react';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Manages GraphQL query execution state.
 *
 * Parses variables and headers JSON, calls the fetcher, and stores the
 * response as a formatted JSON string in the app store. Supports cancellation
 * via AbortController.
 *
 * @param {Function} fetcher - GraphQL fetcher function. Receives { query, variables, operationName }.
 * @return {{ isFetching: boolean, run: Function, stop: Function }}
 */
export function useExecution(fetcher) {
	const { isFetching, query, variables, headers } = useSelect((select) => {
		const app = select('wpgraphql-ide/app');
		return {
			isFetching: app.isFetching(),
			query: app.getQuery(),
			variables: app.getVariables(),
			headers: app.getHeaders(),
		};
	}, []);

	const { setResponse, setIsFetching } = useDispatch('wpgraphql-ide/app');

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
			try {
				const result = await fetcher(
					{ query, variables: parsedVariables, operationName },
					{
						headers: parsedHeaders,
						signal: controller.signal,
					}
				);
				setResponse(JSON.stringify(result, null, 2));
			} catch (error) {
				if (error.name === 'AbortError') {
					return;
				}
				setResponse(
					JSON.stringify(
						{
							errors: [{ message: error.message }],
						},
						null,
						2
					)
				);
			} finally {
				if (abortControllerRef.current === controller) {
					abortControllerRef.current = null;
					setIsFetching(false);
				}
			}
		},
		[fetcher, query, variables, headers, setResponse, setIsFetching]
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
