/* eslint-env browser, jest */
import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { OperationHistoryEntry } from '../../../../src/components/OperationHistoryEntry';

const NOW_SEC = Math.floor(Date.now() / 1000);

const NAMED = {
	hash: 'a'.repeat(64),
	runCount: 5,
	latestRun: NOW_SEC - 30,
	lastQuery: 'query GetPosts { posts { nodes { id title } } }',
	lastVariables: '{"first": 5}',
	lastHeaders: '{}',
	latestDocId: 9,
};

const ANONYMOUS = {
	hash: 'b'.repeat(64),
	runCount: 1,
	latestRun: NOW_SEC - 5,
	lastQuery: '{ yo: __typename }',
	lastVariables: '',
	lastHeaders: '',
	latestDocId: 0,
};

describe('OperationHistoryEntry', () => {
	it('uses the operation name as the primary line for named operations', () => {
		render(<OperationHistoryEntry group={NAMED} onRestore={() => {}} />);
		expect(screen.getByText('GetPosts')).toBeInTheDocument();
		// Body still shows underneath as muted secondary.
		expect(
			screen.getByText('query GetPosts { posts { nodes { id title } } }')
		).toBeInTheDocument();
	});

	it('promotes the query body to the primary line for anonymous queries', () => {
		render(
			<OperationHistoryEntry group={ANONYMOUS} onRestore={() => {}} />
		);
		// The body itself is the identity — `{ yo: __typename }` is what
		// distinguishes this row from `{ typeShit: __typename }`, even
		// though both resolve to the `__typename` field name.
		expect(screen.getByText('{ yo: __typename }')).toBeInTheDocument();
		// Nothing in the row should reduce three different anonymous
		// queries to the same label.
		expect(screen.queryByText('__typename')).toBeNull();
	});

	it('renders the run count and elapsed time on the meta line', () => {
		render(<OperationHistoryEntry group={NAMED} onRestore={() => {}} />);
		expect(
			screen.getByText((content) => content.includes('5 runs'))
		).toBeInTheDocument();
		expect(
			screen.getByText((content) => content.includes('ago'))
		).toBeInTheDocument();
	});

	it('uses _n singular when the operation has run exactly once', () => {
		render(
			<OperationHistoryEntry group={ANONYMOUS} onRestore={() => {}} />
		);
		expect(
			screen.getByText((content) => content.includes('1 run'))
		).toBeInTheDocument();
		expect(screen.queryByText(/1 runs/)).toBeNull();
	});

	it('falls back to "Anonymous query" when the query is empty', () => {
		render(
			<OperationHistoryEntry
				group={{ ...ANONYMOUS, lastQuery: '' }}
				onRestore={() => {}}
			/>
		);
		expect(screen.getByText('Anonymous query')).toBeInTheDocument();
	});

	it('calls onRestore with the whole group when clicked', () => {
		const onRestore = jest.fn();
		render(<OperationHistoryEntry group={NAMED} onRestore={onRestore} />);
		fireEvent.click(screen.getByRole('button'));
		expect(onRestore).toHaveBeenCalledWith(NAMED);
	});
});
