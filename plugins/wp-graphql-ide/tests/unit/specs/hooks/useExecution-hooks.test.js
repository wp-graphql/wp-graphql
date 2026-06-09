/* eslint-env browser, jest */
import { renderHook, act } from '@testing-library/react';

// Mock @wordpress/data so useSelect / useDispatch return controlled
// values without needing the real Redux stores registered.
jest.mock('@wordpress/data', () => {
	const dispatchMocks = {
		setResponse: jest.fn(),
		setResponseHeaders: jest.fn(),
		setResponseMeta: jest.fn(),
		setIsFetching: jest.fn(),
	};
	const selectors = {
		isFetching: () => false,
		getQuery: () => '{ posts { nodes { id } } }',
		getVariables: () => '',
		getHeaders: () => '',
		getHttpMethod: () => 'POST',
	};
	return {
		__esModule: true,
		// useSelect's first arg is `(select) => …`; we call it with a
		// shim that returns the named selectors above when asked for
		// the 'wpgraphql-ide/app' store.
		useSelect: (fn) => fn(() => selectors),
		useDispatch: () => dispatchMocks,
		// Expose the dispatch mocks so individual tests can inspect
		// calls.
		__dispatchMocks: dispatchMocks,
	};
});

// eslint-disable-next-line import/first
import hooks from '../../../../src/wordpress-hooks';
// eslint-disable-next-line import/first
import { useExecution } from '../../../../src/hooks/useExecution';

const NS = 'test/useExecution-hooks';

beforeEach(() => {
	// eslint-disable-next-line global-require
	const { __dispatchMocks } = require('@wordpress/data');
	Object.values(__dispatchMocks).forEach((m) => m.mockClear());
});

afterEach(() => {
	hooks.removeAllFilters('wpgraphql-ide.executeRequest', NS);
	hooks.removeAllFilters('wpgraphql-ide.executeResponse', NS);
	hooks.removeAllActions('wpgraphql-ide.afterExecute', NS);
});

describe('useExecution — request/response filter + afterExecute action', () => {
	it('fires wpgraphql-ide.executeRequest with the outbound payload', async () => {
		const seenRequests = [];
		hooks.addFilter('wpgraphql-ide.executeRequest', NS, (request) => {
			seenRequests.push({ ...request });
			return request;
		});

		const fetcher = jest.fn().mockResolvedValue({
			result: { data: {} },
			headers: {},
			status: 200,
			size: 0,
		});

		const { result } = renderHook(() => useExecution(fetcher));
		await act(async () => {
			await result.current.run();
		});

		expect(seenRequests).toHaveLength(1);
		expect(seenRequests[0]).toMatchObject({
			query: '{ posts { nodes { id } } }',
			httpMethod: 'POST',
		});
	});

	it('lets a filter mutate the request payload before the fetcher sees it', async () => {
		hooks.addFilter('wpgraphql-ide.executeRequest', NS, (request) => ({
			...request,
			headers: { ...request.headers, 'X-Test': 'injected' },
			variables: { ...request.variables, _injected: true },
		}));

		const fetcher = jest.fn().mockResolvedValue({
			result: { data: {} },
			headers: {},
			status: 200,
			size: 0,
		});

		const { result } = renderHook(() => useExecution(fetcher));
		await act(async () => {
			await result.current.run();
		});

		const [callArgs, callOpts] = fetcher.mock.calls[0];
		expect(callArgs.variables).toEqual({ _injected: true });
		expect(callOpts.headers).toEqual({ 'X-Test': 'injected' });
	});

	it('fires wpgraphql-ide.executeResponse with the parsed result', async () => {
		const seen = [];
		hooks.addFilter(
			'wpgraphql-ide.executeResponse',
			NS,
			(response, request) => {
				seen.push({ response, request });
				return response;
			}
		);

		const fetcher = jest.fn().mockResolvedValue({
			result: { data: { ping: 'pong' } },
			headers: {},
			status: 200,
			size: 0,
		});

		const { result } = renderHook(() => useExecution(fetcher));
		await act(async () => {
			await result.current.run();
		});

		expect(seen).toHaveLength(1);
		expect(seen[0].response).toEqual({ data: { ping: 'pong' } });
		expect(seen[0].request).toMatchObject({
			query: '{ posts { nodes { id } } }',
		});
	});

	it('uses the response filter result when setting the store', async () => {
		hooks.addFilter('wpgraphql-ide.executeResponse', NS, (response) => ({
			...response,
			extensions: { synthetic: true },
		}));

		const fetcher = jest.fn().mockResolvedValue({
			result: { data: { ping: 'pong' } },
			headers: {},
			status: 200,
			size: 0,
		});

		const { result } = renderHook(() => useExecution(fetcher));
		await act(async () => {
			await result.current.run();
		});

		// eslint-disable-next-line global-require
		const { __dispatchMocks } = require('@wordpress/data');
		const [stringifiedResponse] = __dispatchMocks.setResponse.mock.calls[0];
		const parsed = JSON.parse(stringifiedResponse);
		expect(parsed.extensions).toEqual({ synthetic: true });
	});

	it('fires wpgraphql-ide.afterExecute on success with the full envelope', async () => {
		const seen = [];
		hooks.addAction('wpgraphql-ide.afterExecute', NS, (payload) =>
			seen.push(payload)
		);

		const fetcher = jest.fn().mockResolvedValue({
			result: { data: { ping: 'pong' } },
			headers: { 'x-trace': 'abc' },
			status: 200,
			size: 42,
		});

		const { result } = renderHook(() => useExecution(fetcher));
		await act(async () => {
			await result.current.run();
		});

		expect(seen).toHaveLength(1);
		expect(seen[0]).toMatchObject({
			result: { data: { ping: 'pong' } },
			responseHeaders: { 'x-trace': 'abc' },
			httpStatus: 200,
			responseSize: 42,
			status: 'success',
			ok: true,
		});
		expect(seen[0].request).toMatchObject({
			query: '{ posts { nodes { id } } }',
		});
		expect(typeof seen[0].duration).toBe('number');
	});

	it('fires wpgraphql-ide.afterExecute on transport failure with ok=false', async () => {
		const seen = [];
		hooks.addAction('wpgraphql-ide.afterExecute', NS, (payload) =>
			seen.push(payload)
		);

		const fetcher = jest.fn().mockRejectedValue(new Error('boom'));

		const { result } = renderHook(() => useExecution(fetcher));
		await act(async () => {
			await result.current.run();
		});

		expect(seen).toHaveLength(1);
		expect(seen[0].ok).toBe(false);
		expect(seen[0].status).toBe('error');
		expect(seen[0].error).toBeInstanceOf(Error);
	});

	it('also runs executeResponse on transport errors so observers see them', async () => {
		const seen = [];
		hooks.addFilter('wpgraphql-ide.executeResponse', NS, (response) => {
			seen.push(response);
			return response;
		});

		const fetcher = jest.fn().mockRejectedValue(new Error('offline'));

		const { result } = renderHook(() => useExecution(fetcher));
		await act(async () => {
			await result.current.run();
		});

		expect(seen).toHaveLength(1);
		expect(seen[0].errors[0].message).toBe('offline');
	});
});
