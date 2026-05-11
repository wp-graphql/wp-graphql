/* eslint-env browser, jest */
import '@testing-library/jest-dom';
import React from 'react';
import {
	render,
	screen,
	fireEvent,
	waitFor,
	within,
} from '@testing-library/react';
import { CacheInspector } from '../../../../../plugins/cache-inspector/src/components/CacheInspector';

const REST_URL = 'http://example.test/wp-json/wpgraphql-cache-inspector/v1';

function mockFetch(handlers) {
	return jest.fn().mockImplementation((url, init = {}) => {
		const method = (init.method || 'GET').toUpperCase();
		for (const [pattern, handler] of handlers) {
			if (pattern.method === method && url.endsWith(pattern.path)) {
				return Promise.resolve(handler(init));
			}
		}
		return Promise.resolve({
			ok: false,
			status: 500,
			json: async () => ({ message: `Unhandled ${method} ${url}` }),
		});
	});
}

function jsonResponse(body, { ok = true, status = 200 } = {}) {
	return {
		ok,
		status,
		json: async () => body,
	};
}

function buildEntry(overrides = {}) {
	return {
		cacheKey: 'deadbeef'.repeat(8),
		sizeBytes: 1024,
		expiresAt: Math.floor(Date.now() / 1000) + 600,
		expiresIn: 600,
		...overrides,
	};
}

beforeEach(() => {
	window.WPGRAPHQL_IDE_CACHE_INSPECTOR = {
		restUrl: REST_URL,
		restNonce: 'test-nonce',
	};
});

afterEach(() => {
	delete window.WPGRAPHQL_IDE_CACHE_INSPECTOR;
	delete global.fetch;
});

describe('CacheInspector', () => {
	it('renders a loading state on first paint', async () => {
		global.fetch = jest.fn(() => new Promise(() => {})); // never resolves
		render(<CacheInspector />);
		expect(
			screen.getByText(/Reading cache inventory/i)
		).toBeInTheDocument();
	});

	it('renders the entries table on a successful list response', async () => {
		const entries = [
			buildEntry({ cacheKey: 'aaaa1111bbbb2222', sizeBytes: 4096 }),
			buildEntry({ cacheKey: 'cccc3333dddd4444', sizeBytes: 512 }),
		];
		global.fetch = mockFetch([
			[
				{ method: 'GET', path: '/entries' },
				() =>
					jsonResponse({
						storage: 'transient',
						entries,
						count: 2,
						truncated: false,
						totalSize: 4608,
					}),
			],
		]);

		render(<CacheInspector />);

		await waitFor(() =>
			expect(screen.getByRole('table')).toBeInTheDocument()
		);
		expect(screen.getByText(/4.5 KB/)).toBeInTheDocument();
		expect(screen.getAllByText(/Purge$/)).toHaveLength(2);
	});

	it('renders the empty state when there are no entries', async () => {
		global.fetch = mockFetch([
			[
				{ method: 'GET', path: '/entries' },
				() =>
					jsonResponse({
						storage: 'transient',
						entries: [],
						count: 0,
						truncated: false,
						totalSize: 0,
					}),
			],
		]);

		render(<CacheInspector />);
		await waitFor(() =>
			expect(
				screen.getByText(/No cached responses yet/i)
			).toBeInTheDocument()
		);
		expect(screen.queryByRole('table')).toBeNull();
	});

	it('renders the external-object-cache notice with no Header chrome', async () => {
		global.fetch = mockFetch([
			[
				{ method: 'GET', path: '/entries' },
				() =>
					jsonResponse({
						storage: 'object_cache',
						entries: [],
						count: 0,
						truncated: false,
						totalSize: 0,
					}),
			],
		]);

		render(<CacheInspector />);
		await waitFor(() =>
			expect(document.body.textContent).toMatch(/external object cache/i)
		);
		expect(screen.queryByRole('button', { name: /Purge all/i })).toBeNull();
		expect(screen.queryByRole('button', { name: /Refresh/i })).toBeNull();
	});

	it('shows the truncated warning when the server flags truncation', async () => {
		global.fetch = mockFetch([
			[
				{ method: 'GET', path: '/entries' },
				() =>
					jsonResponse({
						storage: 'transient',
						entries: [buildEntry()],
						count: 600,
						truncated: true,
						totalSize: 99999,
					}),
			],
		]);

		render(<CacheInspector />);
		await waitFor(() =>
			expect(document.body.textContent).toMatch(
				/Showing the 1 largest entries of 600 total/
			)
		);
	});

	it('renders an error notice with a Retry button when the list fails', async () => {
		global.fetch = mockFetch([
			[
				{ method: 'GET', path: '/entries' },
				() => jsonResponse({}, { ok: false, status: 500 }),
			],
		]);

		render(<CacheInspector />);
		await waitFor(() =>
			expect(document.body.textContent).toMatch(/Request failed \(500\)/)
		);
		expect(
			screen.getByRole('button', { name: /Retry/i })
		).toBeInTheDocument();
	});

	it('optimistically removes a row after a successful single-entry purge', async () => {
		const entries = [
			buildEntry({ cacheKey: 'aaaa1111bbbb2222' }),
			buildEntry({ cacheKey: 'cccc3333dddd4444' }),
		];
		global.fetch = mockFetch([
			[
				{ method: 'GET', path: '/entries' },
				() =>
					jsonResponse({
						storage: 'transient',
						entries,
						count: 2,
						truncated: false,
						totalSize: 2048,
					}),
			],
			[
				{ method: 'POST', path: '/purge' },
				() => jsonResponse({ deleted: true }),
			],
		]);

		render(<CacheInspector />);
		await waitFor(() =>
			expect(screen.getByRole('table')).toBeInTheDocument()
		);

		// Click the Purge button on the first row.
		const rows = screen.getAllByRole('row').slice(1); // strip header row
		fireEvent.click(
			within(rows[0]).getByRole('button', { name: /Purge/i })
		);

		await waitFor(() => expect(screen.getAllByRole('row')).toHaveLength(2)); // 1 header + 1 remaining
	});

	it('opens a confirm modal before bulk purge and only fires the request on confirm', async () => {
		const list = jest.fn(() =>
			jsonResponse({
				storage: 'transient',
				entries: [buildEntry()],
				count: 1,
				truncated: false,
				totalSize: 1024,
			})
		);
		const purgeAll = jest.fn(() => jsonResponse({ deleted: 1 }));
		global.fetch = mockFetch([
			[{ method: 'GET', path: '/entries' }, list],
			[{ method: 'POST', path: '/purge-all' }, purgeAll],
		]);

		render(<CacheInspector />);
		await waitFor(() =>
			expect(screen.getByRole('table')).toBeInTheDocument()
		);

		fireEvent.click(screen.getByRole('button', { name: /Purge all/i }));

		// Modal opens with confirm + cancel actions.
		const dialog = await screen.findByRole('dialog');
		expect(
			within(dialog).getByText(/This will delete/i)
		).toBeInTheDocument();
		expect(purgeAll).not.toHaveBeenCalled();

		fireEvent.click(
			within(dialog).getByRole('button', { name: /^Purge all$/i })
		);

		await waitFor(() => expect(purgeAll).toHaveBeenCalledTimes(1));
		expect(list).toHaveBeenCalledTimes(2); // initial + refetch
	});
});
