import React from 'react';

/**
 * Status-bar HIT/MISS badge. Click focuses the Smart Cache tab.
 * Returns null when the response has no smart-cache extension.
 *
 * @param {Object}   props
 * @param {Object}   [props.parsedResponse]
 * @param {Function} props.focusResponseTab
 *
 * @return {JSX.Element|null} Badge button or null.
 */
export function SmartCacheStatusBadge({ parsedResponse, focusResponseTab }) {
	const objectCache =
		parsedResponse?.extensions?.graphqlSmartCache?.graphqlObjectCache;
	if (!objectCache) {
		return null;
	}

	const isHit = !!objectCache.cacheKey;

	return (
		<button
			type="button"
			className={`wpgraphql-ide-response-trace-badge wpgraphql-ide-smart-cache-badge${
				isHit ? ' is-hit' : ''
			}`}
			onClick={() => focusResponseTab('ext:graphqlSmartCache')}
			title={
				isHit
					? 'Smart Cache HIT — open the Smart Cache tab'
					: 'Smart Cache MISS — open the Smart Cache tab'
			}
		>
			{isHit ? '⚡ cached' : 'uncached'}
		</button>
	);
}
