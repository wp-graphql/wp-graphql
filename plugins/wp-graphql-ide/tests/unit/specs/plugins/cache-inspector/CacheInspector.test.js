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

// @wordpress/components (SearchControl, etc.) probes window.matchMedia at
// render time; jsdom doesn't ship it. Stub before the component imports so
// the no-op survives the first render.
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
	const cacheKey = overrides.cacheKey ?? 'deadbeef'.repeat(8);
	return {
		cacheKey,
		sizeBytes: 1024,
		expiresAt: Math.floor(Date.now() / 1000) + 600,
		expiresIn: 600,
		type: /^[a-f0-9]{64}$/.test(cacheKey) ? 'response' : 'tracker',
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

	it('toggles the About-this-cache help panel, scoping the inspector to the object cache', async () => {
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

		const toggle = await screen.findByRole('button', {
			name: /About this cache/i,
		});
		// Closed by default — body content isn't in the DOM until opened.
		expect(toggle).toHaveAttribute('aria-expanded', 'false');
		expect(
			screen.queryByText(/Varnish, Cloudflare, Fastly/i)
		).not.toBeInTheDocument();

		fireEvent.click(toggle);
		expect(toggle).toHaveAttribute('aria-expanded', 'true');
		// Scope clarification copy — object cache vs. network cache.
		expect(
			screen.getByText(/Varnish, Cloudflare, Fastly/i)
		).toBeInTheDocument();
		expect(
			screen.getByText(/purging on this screen does not clear your CDN/i)
		).toBeInTheDocument();
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

	it('clicking the Size header toggles asc/desc and reflects in aria-sort', async () => {
		const entries = [
			buildEntry({ cacheKey: 'a'.repeat(64), sizeBytes: 4096 }),
			buildEntry({ cacheKey: 'b'.repeat(64), sizeBytes: 512 }),
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

		// Default sort is size desc — biggest row first.
		const rowsBefore = screen.getAllByRole('row').slice(1);
		expect(within(rowsBefore[0]).getByText(/4\.0 KB/)).toBeInTheDocument();

		// Clicking the Size header toggles to asc.
		fireEvent.click(screen.getByRole('button', { name: /Size/i }));
		await waitFor(() => {
			const header = screen
				.getAllByRole('columnheader')
				.find((th) =>
					within(th).queryByRole('button', { name: /Size/i })
				);
			expect(header).toHaveAttribute('aria-sort', 'ascending');
		});

		// Smallest row is now first.
		const rowsAfter = screen.getAllByRole('row').slice(1);
		expect(within(rowsAfter[0]).getByText(/512 B/)).toBeInTheDocument();
	});

	it('renders inline size bars whose max equals the largest entry', async () => {
		const entries = [
			buildEntry({ cacheKey: 'a'.repeat(64), sizeBytes: 4096 }),
			buildEntry({ cacheKey: 'b'.repeat(64), sizeBytes: 512 }),
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

		const bars = document.querySelectorAll(
			'.wpgraphql-ide-cache-inspector-size-bar'
		);
		expect(bars).toHaveLength(2);
		// aria-valuenow reports % of the largest entry — 100 for the
		// max-size row, ~12 (512/4096) for the smaller one.
		expect(bars[0].getAttribute('aria-valuenow')).toBe('100');
		expect(Number(bars[1].getAttribute('aria-valuenow'))).toBeLessThan(100);
		// The inner fill span carries the percentage as inline width.
		const fills = document.querySelectorAll(
			'.wpgraphql-ide-cache-inspector-size-bar-fill'
		);
		expect(fills).toHaveLength(2);
		expect(fills[0].style.width).toBe('100%');
	});

	it('renders a Response / Tracker badge per row based on entry type', async () => {
		global.fetch = mockFetch([
			[
				{ method: 'GET', path: '/entries' },
				() =>
					jsonResponse({
						storage: 'transient',
						entries: [
							buildEntry({
								cacheKey: 'a'.repeat(64),
								type: 'response',
							}),
							buildEntry({
								cacheKey: 'list:post',
								type: 'tracker',
							}),
						],
						count: 2,
						truncated: false,
						totalSize: 2048,
					}),
			],
		]);

		render(<CacheInspector />);
		await waitFor(() =>
			expect(screen.getByRole('table')).toBeInTheDocument()
		);

		expect(screen.getByText('Response')).toBeInTheDocument();
		expect(screen.getByText('Tracker')).toBeInTheDocument();
	});

	it('filters by entry type when the Trackers tab is selected', async () => {
		global.fetch = mockFetch([
			[
				{ method: 'GET', path: '/entries' },
				() =>
					jsonResponse({
						storage: 'transient',
						entries: [
							buildEntry({
								cacheKey: 'a'.repeat(64),
								type: 'response',
							}),
							buildEntry({
								cacheKey: 'list:post',
								type: 'tracker',
							}),
						],
						count: 2,
						truncated: false,
						totalSize: 2048,
					}),
			],
		]);

		render(<CacheInspector />);
		await waitFor(() =>
			expect(screen.getByRole('table')).toBeInTheDocument()
		);

		// Two rows visible by default; tabs include their unfiltered counts.
		expect(screen.getAllByRole('row')).toHaveLength(3); // header + 2
		expect(
			screen.getByRole('tab', { name: /All \(2\)/ })
		).toBeInTheDocument();
		expect(
			screen.getByRole('tab', { name: /Responses \(1\)/ })
		).toBeInTheDocument();
		expect(
			screen.getByRole('tab', { name: /Trackers \(1\)/ })
		).toBeInTheDocument();

		fireEvent.click(screen.getByRole('tab', { name: /Trackers/i }));
		await waitFor(() => expect(screen.getAllByRole('row')).toHaveLength(2)); // header + 1
		expect(screen.queryByText('Response')).toBeNull();
	});

	it('filters by cacheKey substring via SearchControl', async () => {
		global.fetch = mockFetch([
			[
				{ method: 'GET', path: '/entries' },
				() =>
					jsonResponse({
						storage: 'transient',
						entries: [
							buildEntry({ cacheKey: 'a'.repeat(64) }),
							buildEntry({ cacheKey: 'list:post' }),
						],
						count: 2,
						truncated: false,
						totalSize: 2048,
					}),
			],
		]);

		render(<CacheInspector />);
		await waitFor(() =>
			expect(screen.getByRole('table')).toBeInTheDocument()
		);

		const input = screen.getByPlaceholderText(/Search cache keys/i);
		fireEvent.change(input, { target: { value: 'list' } });
		await waitFor(() => expect(screen.getAllByRole('row')).toHaveLength(2)); // header + 1
	});

	it('renders Expired for entries whose expiresIn has hit zero', async () => {
		global.fetch = mockFetch([
			[
				{ method: 'GET', path: '/entries' },
				() =>
					jsonResponse({
						storage: 'transient',
						entries: [
							buildEntry({
								cacheKey: 'a'.repeat(64),
								expiresAt: Math.floor(Date.now() / 1000) - 1,
								expiresIn: 0,
							}),
						],
						count: 1,
						truncated: false,
						totalSize: 1024,
					}),
			],
		]);

		render(<CacheInspector />);
		await waitFor(() =>
			expect(screen.getByText('Expired')).toBeInTheDocument()
		);
	});

	it('bulk-selects rows and posts /purge-bulk with the right body', async () => {
		const entries = [
			buildEntry({ cacheKey: 'a'.repeat(64) }),
			buildEntry({ cacheKey: 'b'.repeat(64) }),
			buildEntry({ cacheKey: 'c'.repeat(64) }),
		];
		const list = jest.fn(() =>
			jsonResponse({
				storage: 'transient',
				entries,
				count: 3,
				truncated: false,
				totalSize: 3072,
			})
		);
		const purgeBulk = jest.fn((init) => {
			const body = JSON.parse(init.body);
			expect(body.cacheKeys).toHaveLength(2);
			return jsonResponse({ deleted: 2, failures: [] });
		});
		global.fetch = mockFetch([
			[{ method: 'GET', path: '/entries' }, list],
			[{ method: 'POST', path: '/purge-bulk' }, purgeBulk],
		]);

		render(<CacheInspector />);
		await waitFor(() =>
			expect(screen.getByRole('table')).toBeInTheDocument()
		);

		// Tick the row checkboxes for the first two entries.
		const rows = screen.getAllByRole('row').slice(1);
		fireEvent.click(within(rows[0]).getByRole('checkbox'));
		fireEvent.click(within(rows[1]).getByRole('checkbox'));

		// Stat strip now shows a selection state with a "Purge selected"
		// button.
		const purgeSelectedButton = await screen.findByRole('button', {
			name: /Purge selected/i,
		});
		fireEvent.click(purgeSelectedButton);

		// Confirm modal opens.
		const dialog = await screen.findByRole('dialog');
		fireEvent.click(
			within(dialog).getByRole('button', { name: /^Purge 2$/i })
		);

		await waitFor(() => expect(purgeBulk).toHaveBeenCalledTimes(1));
		expect(list).toHaveBeenCalledTimes(2); // initial + refetch
	});
});
