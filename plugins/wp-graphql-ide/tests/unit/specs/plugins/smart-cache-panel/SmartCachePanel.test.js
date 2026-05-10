/* eslint-env browser, jest */
import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import {
	SmartCachePanelView,
	_resetSessionStatsForTests,
	_recordResultForTests,
} from '../../../../../plugins/smart-cache-panel/src/components/SmartCachePanel';

function renderView({
	data = { graphqlObjectCache: {} },
	isAuthenticated = false,
	isMutation = false,
	cacheControl,
	docSettings,
	globalGrantMode = 'public',
} = {}) {
	return render(
		<SmartCachePanelView
			data={data}
			isAuthenticated={isAuthenticated}
			isMutation={isMutation}
			cacheControl={cacheControl}
			docSettings={docSettings}
			globalGrantMode={globalGrantMode}
		/>
	);
}

describe('SmartCachePanelView', () => {
	describe('cache HIT', () => {
		const hitData = {
			graphqlObjectCache: {
				cacheKey: 'abc123sha256deadbeef',
				message: 'Returned from cache',
			},
		};

		it('renders the HIT pill with the green is-hit modifier', () => {
			const { container } = renderView({ data: hitData });
			expect(screen.getByText('Cache HIT')).toBeInTheDocument();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-status')
			).toHaveClass('is-hit');
		});

		it('renders the cache key and message', () => {
			renderView({ data: hitData });
			expect(
				screen.getByText('abc123sha256deadbeef')
			).toBeInTheDocument();
			expect(screen.getByText('Returned from cache')).toBeInTheDocument();
		});

		it('copies the cache key to clipboard and flips the button label', async () => {
			const writeText = jest.fn().mockResolvedValue(undefined);
			Object.assign(navigator, { clipboard: { writeText } });
			renderView({ data: hitData });
			fireEvent.click(
				screen.getByRole('button', {
					name: /copy cache key to clipboard/i,
				})
			);
			expect(writeText).toHaveBeenCalledWith('abc123sha256deadbeef');
			await waitFor(() =>
				expect(screen.getByText('Copied')).toBeInTheDocument()
			);
		});

		it('does not render the prerequisite checklist on a HIT', () => {
			const { container } = renderView({ data: hitData });
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-checklist')
			).toBeNull();
		});
	});

	describe('cache MISS', () => {
		it('renders the MISS pill when graphqlObjectCache is empty', () => {
			const { container } = renderView();
			expect(screen.getByText('Cache MISS')).toBeInTheDocument();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-status')
			).toHaveClass('is-miss');
		});

		it('renders the MISS pill when data is undefined', () => {
			renderView({ data: undefined });
			expect(screen.getByText('Cache MISS')).toBeInTheDocument();
		});
	});

	describe('prerequisite checklist', () => {
		it('renders four rows; the settings row is always informational', () => {
			const { container } = renderView();
			const items = container.querySelectorAll(
				'.wpgraphql-ide-smart-cache-checklist-item'
			);
			expect(items).toHaveLength(4);
			expect(items[0]).toHaveClass('is-unknown');
		});

		it('flags the auth row as blocking when authenticated', () => {
			const { container } = renderView({ isAuthenticated: true });
			const items = container.querySelectorAll(
				'.wpgraphql-ide-smart-cache-checklist-item'
			);
			expect(items[1]).toHaveClass('is-blocking');
		});

		it('marks the auth row OK when anonymous', () => {
			const { container } = renderView({ isAuthenticated: false });
			const items = container.querySelectorAll(
				'.wpgraphql-ide-smart-cache-checklist-item'
			);
			expect(items[1]).toHaveClass('is-ok');
		});

		it('flags the mutation row as blocking when isMutation is true', () => {
			const { container } = renderView({ isMutation: true });
			const items = container.querySelectorAll(
				'.wpgraphql-ide-smart-cache-checklist-item'
			);
			expect(items[2]).toHaveClass('is-blocking');
		});

		it('headlines the MISS with the mutation reason when applicable', () => {
			const { container } = renderView({ isMutation: true });
			expect(
				container.querySelector(
					'.wpgraphql-ide-smart-cache-status-explainer'
				)
			).toHaveTextContent(/mutations are never cached/i);
		});

		it('headlines the MISS with the auth reason when authenticated', () => {
			const { container } = renderView({ isAuthenticated: true });
			expect(
				container.querySelector(
					'.wpgraphql-ide-smart-cache-status-explainer'
				)
			).toHaveTextContent(/authenticated request/i);
		});
	});

	describe('document policy card', () => {
		it('shows max-age set on the document', () => {
			const { container } = renderView({
				docSettings: { maxAgeHeader: 300 },
			});
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-policy')
			).toHaveTextContent(/300s \(set on this document\)/);
		});

		it('falls back to "Global default" when max-age is unset', () => {
			const { container } = renderView({ docSettings: {} });
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-policy')
			).toHaveTextContent(/Max-age:[\s\S]*Global default/);
		});

		it('labels grant=allow as Allowed', () => {
			const { container } = renderView({
				docSettings: { grant: 'allow' },
			});
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-policy')
			).toHaveTextContent(/Access:\s*Allowed/);
		});

		it('echoes the global grant when the doc uses the default', () => {
			const { container } = renderView({
				docSettings: {},
				globalGrantMode: 'deny',
			});
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-policy')
			).toHaveTextContent(/currently:\s*Denied/);
		});
	});

	describe('network cache card', () => {
		it('shows the Cache-Control header value when present', () => {
			const { container } = renderView({
				cacheControl: 'max-age=600, public',
			});
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-network')
			).toHaveTextContent('max-age=600, public');
		});

		it('explains max-age in plain language', () => {
			const { container } = renderView({
				cacheControl: 'max-age=600, public',
			});
			expect(
				container.querySelector(
					'.wpgraphql-ide-smart-cache-network-explainer'
				)
			).toHaveTextContent(/up to 600s/);
		});

		it('explains no-store in plain language', () => {
			const { container } = renderView({
				cacheControl: 'no-store',
			});
			expect(
				container.querySelector(
					'.wpgraphql-ide-smart-cache-network-explainer'
				)
			).toHaveTextContent(/will not store/);
		});

		it('omits the card when no Cache-Control header is present', () => {
			const { container } = renderView();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-network')
			).toBeNull();
		});
	});

	describe('session counter', () => {
		beforeEach(() => {
			_resetSessionStatsForTests();
		});

		it('hides the chip until at least one response is recorded', () => {
			const { container } = renderView();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-session')
			).toBeNull();
		});

		it('renders HIT/MISS counts and hit-rate after results are recorded', () => {
			_recordResultForTests(true);
			_recordResultForTests(true);
			_recordResultForTests(false);
			const { container } = renderView();
			const counter = container.querySelector(
				'.wpgraphql-ide-smart-cache-session'
			);
			expect(counter).toBeInTheDocument();
			expect(counter).toHaveTextContent('2 HIT');
			expect(counter).toHaveTextContent('1 MISS');
			expect(counter).toHaveTextContent(/67% hit rate/);
		});
	});
});
