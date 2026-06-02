/* eslint-env browser, jest */
import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { OperationHistoryEntry } from '../../../../src/components/OperationHistoryEntry';

const NOW_SEC = Math.floor(Date.now() / 1000);

const GROUP = {
	hash: 'a'.repeat(64),
	runCount: 5,
	latestRun: NOW_SEC - 30,
	lastQuery: 'query GetPosts { posts { nodes { id title } } }',
	lastVariables: '{"first": 5}',
	lastHeaders: '{}',
	latestDocId: 9,
};

describe('OperationHistoryEntry', () => {
	it('renders the derived operation name, run count, and elapsed time', () => {
		render(<OperationHistoryEntry group={GROUP} onRestore={() => {}} />);
		expect(screen.getByText('GetPosts')).toBeInTheDocument();
		expect(
			screen.getByText((content) => content.includes('5 runs'))
		).toBeInTheDocument();
		expect(
			screen.getByText((content) => content.includes('ago'))
		).toBeInTheDocument();
	});

	it('uses _n singular when the operation has run exactly once', () => {
		render(
			<OperationHistoryEntry
				group={{ ...GROUP, runCount: 1 }}
				onRestore={() => {}}
			/>
		);
		expect(
			screen.getByText((content) => content.includes('1 run'))
		).toBeInTheDocument();
		// Make sure we didn't accidentally hit the plural form.
		expect(screen.queryByText(/1 runs/)).toBeNull();
	});

	it('falls back to "Anonymous query" when the query has no parseable shape', () => {
		render(
			<OperationHistoryEntry
				group={{ ...GROUP, lastQuery: 'not a parseable query' }}
				onRestore={() => {}}
			/>
		);
		expect(screen.getByText('Anonymous query')).toBeInTheDocument();
	});

	it('renders a truncated one-line preview of the latest query', () => {
		render(<OperationHistoryEntry group={GROUP} onRestore={() => {}} />);
		expect(
			screen.getByText('query GetPosts { posts { nodes { id title } } }')
		).toBeInTheDocument();
	});

	it('calls onRestore with the whole group when clicked', () => {
		const onRestore = jest.fn();
		render(<OperationHistoryEntry group={GROUP} onRestore={onRestore} />);
		fireEvent.click(screen.getByRole('button'));
		expect(onRestore).toHaveBeenCalledWith(GROUP);
	});
});
