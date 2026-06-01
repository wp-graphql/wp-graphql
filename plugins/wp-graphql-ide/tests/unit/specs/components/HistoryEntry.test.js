/* eslint-env browser, jest */
import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { HistoryEntry } from '../../../../src/components/HistoryEntry';

const ENTRY = {
	id: 42,
	query: 'query GetPosts { posts { nodes { id title } } }',
	variables: '{"first": 5}',
	headers: '{}',
	duration_ms: 87,
	status: 'success',
	is_authenticated: true,
	http_method: 'POST',
	timestamp: 1716800000,
};

describe('HistoryEntry', () => {
	it('renders the operation name as the label, method, status, and duration', () => {
		render(<HistoryEntry entry={ENTRY} onRestore={() => {}} />);
		expect(screen.getByText('GetPosts')).toBeInTheDocument();
		expect(screen.getByText('POST')).toBeInTheDocument();
		expect(screen.getByText('OK')).toBeInTheDocument();
		expect(screen.getByText('87ms')).toBeInTheDocument();
		expect(screen.getByText('#42')).toBeInTheDocument();
	});

	it('uses the first top-level field name for anonymous shorthand queries', () => {
		// `deriveDocTitle('{ posts { id } }')` returns 'posts' (the first
		// field), not 'Untitled' — so the label shows the field name.
		render(
			<HistoryEntry
				entry={{ ...ENTRY, query: '{ posts { id } }' }}
				onRestore={() => {}}
			/>
		);
		expect(screen.getByText('posts')).toBeInTheDocument();
	});

	it('falls back to "Anonymous query" when deriveDocTitle returns Untitled', () => {
		// Empty / unparseable queries are the cases that trip the fallback.
		render(
			<HistoryEntry
				entry={{ ...ENTRY, query: 'not a parseable query' }}
				onRestore={() => {}}
			/>
		);
		expect(screen.getByText('Anonymous query')).toBeInTheDocument();
	});

	it('renders ERR for failed executions and applies the status class', () => {
		render(
			<HistoryEntry
				entry={{ ...ENTRY, status: 'error' }}
				onRestore={() => {}}
			/>
		);
		const status = screen.getByText('ERR');
		expect(status).toBeInTheDocument();
		expect(status).toHaveClass('wpgraphql-ide-history-status--error');
	});

	it('calls onRestore with the entry when the button is clicked', () => {
		const onRestore = jest.fn();
		render(<HistoryEntry entry={ENTRY} onRestore={onRestore} />);
		fireEvent.click(screen.getByRole('button'));
		expect(onRestore).toHaveBeenCalledWith(ENTRY);
	});

	it('renders the avatar with the public class when is_authenticated is false', () => {
		render(
			<HistoryEntry
				entry={{ ...ENTRY, is_authenticated: false }}
				onRestore={() => {}}
				avatarUrl="https://example.com/a.png"
			/>
		);
		const img = screen.getByRole('img', { name: 'Public' });
		expect(img).toHaveClass('is-public');
	});

	it('omits the avatar when no avatarUrl is provided', () => {
		render(<HistoryEntry entry={ENTRY} onRestore={() => {}} />);
		expect(screen.queryByRole('img')).not.toBeInTheDocument();
	});

	it('skips the preview when the query is empty', () => {
		render(
			<HistoryEntry
				entry={{ ...ENTRY, query: '' }}
				onRestore={() => {}}
			/>
		);
		expect(
			document.querySelector('.wpgraphql-ide-history-entry-preview')
		).toBeNull();
	});
});
