import React from 'react';
import { render, waitFor, act } from '@testing-library/react';
import '@testing-library/jest-dom';
import { useExecution } from '../../../../src/hooks/useExecution';

let mockState;
let mockSetResponse;
let mockSetIsFetching;

jest.mock('@wordpress/data', () => ({
	useSelect: (fn) =>
		fn(() => ({
			isFetching: () => mockState.isFetching,
			getQuery: () => mockState.query,
			getVariables: () => mockState.variables,
			getHeaders: () => mockState.headers,
		})),
	useDispatch: () => ({
		setResponse: mockSetResponse,
		setIsFetching: mockSetIsFetching,
	}),
}));

// Harness exposes the hook's run function to test it imperatively.
function Harness({ fetcher, onReady }) {
	const execution = useExecution(fetcher);
	React.useEffect(() => {
		onReady(execution);
	}, [execution, onReady]);
	return null;
}

describe('useExecution', () => {
	beforeEach(() => {
		mockState = {
			isFetching: false,
			query: '{ hello }',
			variables: '',
			headers: '',
		};
		mockSetResponse = jest.fn();
		mockSetIsFetching = jest.fn();
	});

	test('run() calls fetcher with query and stores the response', async () => {
		const fetcher = jest
			.fn()
			.mockResolvedValue({ data: { hello: 'world' } });
		let exec;
		render(<Harness fetcher={fetcher} onReady={(e) => (exec = e)} />);

		await act(async () => {
			await exec.run();
		});

		expect(fetcher).toHaveBeenCalled();
		expect(fetcher.mock.calls[0][0].query).toBe('{ hello }');
		expect(mockSetResponse).toHaveBeenCalledWith(
			JSON.stringify({ data: { hello: 'world' } }, null, 2)
		);
		expect(mockSetIsFetching).toHaveBeenCalledWith(true);
		expect(mockSetIsFetching).toHaveBeenLastCalledWith(false);
	});

	test('run() sets error response when variables are not valid JSON', async () => {
		mockState.variables = '{ not valid';
		const fetcher = jest.fn();
		let exec;
		render(<Harness fetcher={fetcher} onReady={(e) => (exec = e)} />);

		await act(async () => {
			await exec.run();
		});

		expect(fetcher).not.toHaveBeenCalled();
		const response = JSON.parse(mockSetResponse.mock.calls[0][0]);
		expect(response.errors[0].message).toContain('Variables');
	});

	test('run() sets error response when headers are not valid JSON', async () => {
		mockState.headers = 'not json';
		const fetcher = jest.fn();
		let exec;
		render(<Harness fetcher={fetcher} onReady={(e) => (exec = e)} />);

		await act(async () => {
			await exec.run();
		});

		expect(fetcher).not.toHaveBeenCalled();
		const response = JSON.parse(mockSetResponse.mock.calls[0][0]);
		expect(response.errors[0].message).toContain('Headers');
	});

	test('run() parses valid JSON variables and passes them to fetcher', async () => {
		mockState.variables = '{"id": 123}';
		const fetcher = jest.fn().mockResolvedValue({ data: {} });
		let exec;
		render(<Harness fetcher={fetcher} onReady={(e) => (exec = e)} />);

		await act(async () => {
			await exec.run();
		});

		expect(fetcher.mock.calls[0][0].variables).toEqual({ id: 123 });
	});

	test('fetcher errors are stored as response errors', async () => {
		const fetcher = jest.fn().mockRejectedValue(new Error('network down'));
		let exec;
		render(<Harness fetcher={fetcher} onReady={(e) => (exec = e)} />);

		await act(async () => {
			await exec.run();
		});

		const response = JSON.parse(mockSetResponse.mock.calls[0][0]);
		expect(response.errors[0].message).toBe('network down');
	});

	test('exposes isFetching from the store', () => {
		mockState.isFetching = true;
		let exec;
		render(<Harness fetcher={jest.fn()} onReady={(e) => (exec = e)} />);

		waitFor(() => {
			expect(exec.isFetching).toBe(true);
		});
	});
});
