/* eslint-env browser, jest */
import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { SmartCachePanel } from '../../../../../plugins/smart-cache-panel/src/components/SmartCachePanel';

describe('SmartCachePanel', () => {
	describe('cache HIT', () => {
		const hitData = {
			graphqlObjectCache: {
				cacheKey: 'abc123sha256deadbeef',
				message: 'Returned from cache',
			},
		};

		it('renders the HIT pill with the green is-hit modifier', () => {
			const { container } = render(<SmartCachePanel data={hitData} />);
			expect(screen.getByText('Cache HIT')).toBeInTheDocument();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-status')
			).toHaveClass('is-hit');
		});

		it('renders the cache key and message', () => {
			render(<SmartCachePanel data={hitData} />);
			expect(
				screen.getByText('abc123sha256deadbeef')
			).toBeInTheDocument();
			expect(screen.getByText('Returned from cache')).toBeInTheDocument();
		});

		it('copies the cache key to clipboard and flips the button label', async () => {
			const writeText = jest.fn().mockResolvedValue(undefined);
			Object.assign(navigator, { clipboard: { writeText } });
			render(<SmartCachePanel data={hitData} />);
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
	});

	describe('cache MISS', () => {
		it('renders the MISS pill when graphqlObjectCache is empty', () => {
			const { container } = render(
				<SmartCachePanel data={{ graphqlObjectCache: {} }} />
			);
			expect(screen.getByText('Cache MISS')).toBeInTheDocument();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-status')
			).toHaveClass('is-miss');
		});

		it('renders the MISS pill when data is undefined', () => {
			render(<SmartCachePanel />);
			expect(screen.getByText('Cache MISS')).toBeInTheDocument();
		});

		it('shows the re-run hint and no cache key meta', () => {
			render(<SmartCachePanel data={{ graphqlObjectCache: {} }} />);
			expect(
				screen.getByText(/re-run the same query/i)
			).toBeInTheDocument();
			expect(
				screen.queryByRole('button', {
					name: /copy cache key to clipboard/i,
				})
			).not.toBeInTheDocument();
		});
	});
});
