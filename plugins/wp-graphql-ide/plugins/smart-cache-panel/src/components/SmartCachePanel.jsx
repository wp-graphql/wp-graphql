/* global navigator */
import React, { useState } from 'react';
import { Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * IDE-coupled wrapper. Reads auth + active query from the IDE store
 * and delegates to the pure {@link SmartCachePanelView}. To port this
 * plugin to wp-graphql-smart-cache, delete this wrapper.
 *
 * @param {Object} props
 * @param {Object} [props.data] `response.extensions.graphqlSmartCache`.
 *
 * @return {JSX.Element} Smart Cache extension renderer.
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
 * Pure renderer — props in, JSX out. The unit-tested entry point.
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

// Strips comments and string literals before testing for a top-level
// `mutation` keyword — avoids false positives from `# mutation foo`.
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

function missHeadline({ isAuthenticated, isMutation }) {
	if (isMutation) {
		return 'Mutations are never cached — resolvers ran.';
	}
	if (isAuthenticated) {
		return 'Authenticated request — resolvers ran.';
	}
	return 'Resolvers ran for this request.';
}

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

function checklistGlyph(state) {
	if (state === 'ok') {
		return '✓';
	}
	if (state === 'unknown') {
		return '?';
	}
	return '✗';
}
