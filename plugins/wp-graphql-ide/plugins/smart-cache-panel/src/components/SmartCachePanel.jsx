/* global navigator */
import React, { useState } from 'react';
import { Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * IDE-coupled wrapper that pulls the auth pill state and the active
 * query from the wpgraphql-ide app store, then delegates rendering to
 * the pure {@link SmartCachePanelView}. When this plugin moves to
 * wp-graphql-smart-cache, delete this wrapper and pass props in from
 * whatever data layer that environment exposes — the inner renderer
 * has no other IDE coupling.
 *
 * Extension shape (from wp-graphql-smart-cache `Cache\Results`):
 *
 *     {
 *       graphqlObjectCache: { message, cacheKey } | {},
 *     }
 *
 * @param {Object} props
 * @param {Object} [props.data] Parsed `response.extensions.graphqlSmartCache`.
 *
 * @return {JSX.Element} The Smart Cache extension renderer.
 */
export function SmartCachePanel({ data }) {
	const isAuthenticated = useSelect(
		(s) => s('wpgraphql-ide/app').isAuthenticated(),
		[]
	);
	const query = useSelect((s) => s('wpgraphql-ide/app').getQuery(), []);

	return (
		<SmartCachePanelView
			data={data}
			isAuthenticated={isAuthenticated}
			isMutation={detectMutation(query)}
		/>
	);
}

/**
 * Pure renderer — receives everything as props and depends on no IDE
 * stores. This is the function unit tests target and the function
 * that would lift cleanly into wp-graphql-smart-cache.
 *
 * @param {Object}  props
 * @param {Object}  [props.data]
 * @param {boolean} props.isAuthenticated
 * @param {boolean} props.isMutation
 *
 * @return {JSX.Element} Smart Cache panel UI.
 */
export function SmartCachePanelView({ data, isAuthenticated, isMutation }) {
	const objectCache = data?.graphqlObjectCache;
	const isHit = !!objectCache?.cacheKey;
	const [copied, setCopied] = useState(false);

	const copyKey = async () => {
		if (!objectCache?.cacheKey) {
			return;
		}
		try {
			await navigator.clipboard.writeText(objectCache.cacheKey);
			setCopied(true);
			setTimeout(() => setCopied(false), 1500);
		} catch (err) {
			setCopied(false);
		}
	};

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
						: missHeadline({ isAuthenticated, isMutation })}
				</span>
			</div>

			{isHit && objectCache?.cacheKey && (
				<dl className="wpgraphql-ide-smart-cache-meta">
					<dt>Cache key</dt>
					<dd>
						<code className="wpgraphql-ide-smart-cache-key">
							{objectCache.cacheKey}
						</code>
						<Button
							variant="tertiary"
							size="small"
							className="wpgraphql-ide-smart-cache-copy"
							onClick={copyKey}
							aria-label="Copy cache key to clipboard"
						>
							{copied ? 'Copied' : 'Copy'}
						</Button>
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
				<PrerequisiteChecklist
					isAuthenticated={isAuthenticated}
					isMutation={isMutation}
					cached={isHit}
				/>
			)}
		</div>
	);
}

/**
 * Cheap mutation detector — looks for a top-level `mutation` keyword in
 * the operation. Misses fancy edge cases (operations defined by name
 * with leading whitespace before a comment, etc.) but covers the
 * common case the badge needs to flag.
 *
 * @param {string} [query]
 *
 * @return {boolean} True if the query string declares a mutation.
 */
function detectMutation(query) {
	if (!query || typeof query !== 'string') {
		return false;
	}
	const stripped = query
		.replace(/#.*$/gm, '')
		.replace(/"""[\s\S]*?"""/g, '')
		.replace(/"(?:\\.|[^"\\])*"/g, '');
	return /(^|\s)mutation\b/.test(stripped);
}

/**
 * Headline copy for the MISS pill — picks the most-likely blocker
 * based on what the IDE can observe (auth pill state, mutation
 * keyword). The settings toggle is the one prerequisite the IDE
 * can't introspect, so we never blame it from the headline.
 *
 * @param {Object}  ctx
 * @param {boolean} ctx.isAuthenticated
 * @param {boolean} ctx.isMutation
 *
 * @return {string} Headline text for the MISS pill explainer span.
 */
function missHeadline({ isAuthenticated, isMutation }) {
	if (isMutation) {
		return 'Mutations are never cached — resolvers ran.';
	}
	if (isAuthenticated) {
		return 'Authenticated request — resolvers ran.';
	}
	return 'Resolvers ran for this request.';
}

/**
 * Renders the prerequisites Smart Cache requires for a HIT. The IDE
 * can definitively answer auth + mutation + cached; the
 * settings-toggle row is informational with a link target since the
 * IDE has no client-side view of the WP option.
 *
 * @param {Object}  props
 * @param {boolean} props.isAuthenticated
 * @param {boolean} props.isMutation
 * @param {boolean} props.cached
 *
 * @return {JSX.Element} Checklist UI listing the cache prerequisites.
 */
function PrerequisiteChecklist({ isAuthenticated, isMutation, cached }) {
	const items = [
		{
			key: 'enabled',
			label: 'Cache enabled in WPGraphQL settings',
			state: 'unknown',
			fixHint: (
				<>
					The IDE can&apos;t introspect this from the browser. Verify{' '}
					<strong>WPGraphQL → Settings → Cache → Cache toggle</strong>{' '}
					is on. Without it, every request bypasses the Object Cache
					regardless of auth state.
				</>
			),
		},
		{
			key: 'isAuthenticated',
			label: 'Anonymous request',
			state: isAuthenticated ? 'blocking' : 'ok',
			fixHint: (
				<>
					Smart Cache currently skips authenticated requests. Even
					with the IDE&apos;s auth pill off, your{' '}
					<code>wordpress_logged_in_*</code> cookie travels on every
					same-origin request — open the IDE in an{' '}
					<strong>incognito window</strong> or call the GraphQL
					endpoint with <code>curl</code>/Postman to test caching.
				</>
			),
		},
		{
			key: 'isMutation',
			label: 'Query (not a mutation)',
			state: isMutation ? 'blocking' : 'ok',
			fixHint: (
				<>
					Mutations are never cached — they exist to write data, so
					serving a stale write would be incorrect. Switch to a{' '}
					<code>query</code> operation to test the cache.
				</>
			),
		},
		{
			key: 'cached',
			label: 'Response stored in the Object Cache',
			state: cached ? 'ok' : 'blocking',
			fixHint: (
				<>
					Expected on the first call when the other prerequisites are
					met — the response was just stored. Re-run the same query
					(same operation, variables, and auth state) and it should
					land here as a HIT.
				</>
			),
		},
	];

	const someBlocking = items.some((i) => i.state === 'blocking');

	return (
		<div className="wpgraphql-ide-smart-cache-checklist">
			<div className="wpgraphql-ide-smart-cache-checklist-heading">
				{someBlocking
					? 'Prerequisites for a Cache HIT'
					: 'No blockers detected — re-run the query.'}
			</div>
			<ul className="wpgraphql-ide-smart-cache-checklist-list">
				{items.map((item) => (
					<li
						key={item.key}
						className={`wpgraphql-ide-smart-cache-checklist-item is-${item.state}`}
					>
						<span
							className="wpgraphql-ide-smart-cache-checklist-icon"
							aria-hidden="true"
						>
							{checklistGlyph(item.state)}
						</span>
						<div className="wpgraphql-ide-smart-cache-checklist-body">
							<div className="wpgraphql-ide-smart-cache-checklist-label">
								{item.label}
							</div>
							{item.state !== 'ok' && (
								<div className="wpgraphql-ide-smart-cache-checklist-hint">
									{item.fixHint}
								</div>
							)}
						</div>
					</li>
				))}
			</ul>
		</div>
	);
}

/**
 * @param {string} state
 *
 * @return {string} Glyph for the row icon (✓ ok, ? unknown, ✗ blocking).
 */
function checklistGlyph(state) {
	if (state === 'ok') {
		return '✓';
	}
	if (state === 'unknown') {
		return '?';
	}
	return '✗';
}
