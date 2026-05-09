import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { SmartCacheStatusBadge } from '../../../../../plugins/smart-cache-panel/src/components/SmartCacheStatusBadge';

function withSmartCache(graphqlObjectCache) {
	return {
		extensions: { graphqlSmartCache: { graphqlObjectCache } },
	};
}

describe('SmartCacheStatusBadge', () => {
	it('renders nothing when the response carries no smart-cache extension', () => {
		const { container } = render(
			<SmartCacheStatusBadge
				parsedResponse={{ extensions: {} }}
				focusResponseTab={jest.fn()}
			/>
		);
		expect(container).toBeEmptyDOMElement();
	});

	it('renders an "uncached" badge on a MISS', () => {
		render(
			<SmartCacheStatusBadge
				parsedResponse={withSmartCache({})}
				focusResponseTab={jest.fn()}
			/>
		);
		const badge = screen.getByRole('button', { name: 'uncached' });
		expect(badge).toHaveAttribute(
			'title',
			expect.stringMatching(/smart cache miss/i)
		);
		expect(badge).not.toHaveClass('is-hit');
	});

	it('renders a "cached" badge with the is-hit modifier on a HIT', () => {
		render(
			<SmartCacheStatusBadge
				parsedResponse={withSmartCache({ cacheKey: 'sha-x' })}
				focusResponseTab={jest.fn()}
			/>
		);
		const badge = screen.getByRole('button', { name: /cached/ });
		expect(badge).toHaveAttribute(
			'title',
			expect.stringMatching(/smart cache hit/i)
		);
		expect(badge).toHaveClass('is-hit');
	});

	it('focuses the Smart Cache extension tab on click', () => {
		const focusResponseTab = jest.fn();
		render(
			<SmartCacheStatusBadge
				parsedResponse={withSmartCache({ cacheKey: 'sha-x' })}
				focusResponseTab={focusResponseTab}
			/>
		);
		fireEvent.click(screen.getByRole('button'));
		expect(focusResponseTab).toHaveBeenCalledWith('ext:graphqlSmartCache');
	});
});
