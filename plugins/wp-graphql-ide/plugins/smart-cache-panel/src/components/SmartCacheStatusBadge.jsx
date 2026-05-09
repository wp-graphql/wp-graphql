import React from 'react';

/**
 * Status-bar badge that surfaces the GraphQL Object Cache HIT/MISS
 * state at-a-glance, alongside the existing duration/size/resolver
 * badges. Click switches the response tab to Smart Cache so users
 * can see the cache key without opening the kebab.
 *
 * Hidden when the response carries no `graphqlSmartCache` extension —
 * a query against a server without smart-cache active just shows
 * nothing here, no nag.
 *
 * @param {Object}   props
 * @param {Object}   [props.parsedResponse]
 * @param {Function} props.focusResponseTab
 *
 * @return {JSX.Element|null} Badge button or null when no smart-cache data.
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
