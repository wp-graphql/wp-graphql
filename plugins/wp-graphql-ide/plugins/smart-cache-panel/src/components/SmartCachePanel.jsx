import React from 'react';

/**
 * Renders the `graphqlSmartCache` response extension. The plugin lives
 * inside wp-graphql-ide so it can later be lifted into the
 * wp-graphql-smart-cache plugin verbatim — the renderer reads only from
 * `data` (the extension payload) and depends on no IDE internals beyond
 * the public `registerResponseExtensionTab` API.
 *
 * Cache-hit shape (from wp-graphql-smart-cache `Cache\Results`):
 *
 *     {
 *       graphqlObjectCache: {
 *         message: string,
 *         cacheKey: string,  // sha256
 *       }
 *     }
 *
 * Cache-miss: `graphqlObjectCache` is empty / absent.
 *
 * @param {Object} props
 * @param {Object} [props.data] Parsed `response.extensions.graphqlSmartCache`.
 *
 * @return {JSX.Element}
 */
export function SmartCachePanel({ data }) {
	const objectCache = data?.graphqlObjectCache;
	const isHit = !!objectCache?.cacheKey;

	return (
		<div className="wpgraphql-ide-smart-cache">
			<div
				className={`wpgraphql-ide-smart-cache-status${
					isHit ? ' is-hit' : ' is-miss'
				}`}
				role="status"
			>
				<span className="wpgraphql-ide-smart-cache-status-dot" />
				<span className="wpgraphql-ide-smart-cache-status-label">
					{isHit ? 'Cache HIT' : 'Cache MISS'}
				</span>
				<span className="wpgraphql-ide-smart-cache-status-explainer">
					{isHit
						? 'Returned from the GraphQL Object Cache — no resolvers ran.'
						: 'Resolvers ran for this request.'}
				</span>
			</div>

			{isHit && objectCache?.cacheKey && (
				<dl className="wpgraphql-ide-smart-cache-meta">
					<dt>Cache key</dt>
					<dd>
						<code>{objectCache.cacheKey}</code>
					</dd>
					{objectCache?.message && (
						<>
							<dt>Message</dt>
							<dd>{objectCache.message}</dd>
						</>
					)}
				</dl>
			)}

			{!isHit && (
				<p className="wpgraphql-ide-smart-cache-hint">
					Re-run the same query to populate the Object Cache, then
					query again — the second response should land here as a
					HIT with a stable cache key.
				</p>
			)}
		</div>
	);
}
