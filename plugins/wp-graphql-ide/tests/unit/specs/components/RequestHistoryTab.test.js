/* eslint-env browser, jest */
import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

// `@wordpress/components` probes matchMedia at render time; jsdom doesn't
// ship it. Stub before the component imports.
if (typeof window.matchMedia !== 'function') {
	window.matchMedia = () => ({
		matches: false,
		media: '',
		onchange: null,
		addListener: () => {},
		removeListener: () => {},
		addEventListener: () => {},
		removeEventListener: () => {},
		dispatchEvent: () => false,
	});
}

// Mock the wp-data stores used by RequestHistoryTab. We control history
// and the active document directly so each test exercises a specific
// slug-match permutation. Stash on global so jest.mock's factory can
// reach the same refs without tripping the "no out-of-scope variables"
// guard.
global.__rht = {
	history: [],
	activeDocument: null,
	setVariables: jest.fn(),
	setHeaders: jest.fn(),
};

jest.mock('@wordpress/data', () => {
	const stores = {
		'wpgraphql-ide/app': {
			getHistory: () => global.__rht.history,
		},
		'wpgraphql-ide/document-editor': {
			getActiveDocument: () => global.__rht.activeDocument,
		},
	};
	return {
		useSelect: (cb) => cb((name) => stores[name]),
		useDispatch: () => ({
			setVariables: global.__rht.setVariables,
			setHeaders: global.__rht.setHeaders,
		}),
	};
});

const { __rht } = global;

// eslint-disable-next-line import/first
import { RequestHistoryTab } from '../../../../src/components/response-extensions/RequestHistoryTab';

describe('RequestHistoryTab', () => {
	beforeEach(() => {
		__rht.history.length = 0;
		__rht.activeDocument = null;
		__rht.setVariables.mockReset();
		__rht.setHeaders.mockReset();
	});

	it('renders only the runs whose operationHash matches the active doc slug', () => {
		const slug = 'a'.repeat(64);
		const otherHash = 'b'.repeat(64);
		__rht.activeDocument = { id: 9, slug, status: 'publish' };
		__rht.history.push(
			{
				id: 3,
				query: '{ a }',
				variables: 'v3',
				headers: '',
				duration_ms: 5,
				status: 'success',
				is_authenticated: true,
				http_method: 'POST',
				timestamp: 1700000003,
				operationHash: slug,
			},
			{
				id: 2,
				query: '{ other }',
				variables: '',
				headers: '',
				duration_ms: 4,
				status: 'success',
				is_authenticated: true,
				http_method: 'POST',
				timestamp: 1700000002,
				operationHash: otherHash,
			},
			{
				id: 1,
				query: '{ a }',
				variables: 'v1',
				headers: '',
				duration_ms: 3,
				status: 'success',
				is_authenticated: true,
				http_method: 'POST',
				timestamp: 1700000001,
				operationHash: slug,
			}
		);
		render(<RequestHistoryTab />);
		expect(screen.queryByText('#3')).toBeInTheDocument();
		expect(screen.queryByText('#1')).toBeInTheDocument();
		expect(screen.queryByText('#2')).toBeNull();
	});

	it('shows the empty state when no runs match the doc slug', () => {
		__rht.activeDocument = {
			id: 9,
			slug: 'a'.repeat(64),
			status: 'publish',
		};
		__rht.history.push({
			id: 1,
			query: '{ x }',
			operationHash: 'b'.repeat(64),
			timestamp: 1700000000,
		});
		render(<RequestHistoryTab />);
		expect(screen.getByText('No runs recorded yet')).toBeInTheDocument();
	});

	it("restoring a run calls setVariables / setHeaders with that run's values", () => {
		const slug = 'c'.repeat(64);
		__rht.activeDocument = { id: 9, slug, status: 'publish' };
		__rht.history.push({
			id: 7,
			query: '{ c }',
			variables: '{"first":3}',
			headers: '{"X-Foo":"bar"}',
			duration_ms: 8,
			status: 'success',
			http_method: 'POST',
			timestamp: 1700000007,
			operationHash: slug,
		});
		render(<RequestHistoryTab />);
		fireEvent.click(screen.getByRole('button'));
		expect(__rht.setVariables).toHaveBeenCalledWith('{"first":3}');
		expect(__rht.setHeaders).toHaveBeenCalledWith('{"X-Foo":"bar"}');
	});
});
