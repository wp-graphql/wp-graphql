/* global navigator */
import React, {
	useEffect,
	useRef,
	useState,
	useSyncExternalStore,
} from 'react';
import { Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

// Module-scoped session counter so HIT/MISS totals survive Smart Cache
// panel remounts (switching to Tracing and back). Cleared on page reload.
let sessionStats = { hit: 0, miss: 0 };
const sessionSubscribers = new Set();
function recordResult(isHit) {
	sessionStats = {
		hit: sessionStats.hit + (isHit ? 1 : 0),
		miss: sessionStats.miss + (isHit ? 0 : 1),
	};
	sessionSubscribers.forEach((fn) => fn());
}
function subscribeSessionStats(fn) {
	sessionSubscribers.add(fn);
	return () => sessionSubscribers.delete(fn);
}
function getSessionStats() {
	return sessionStats;
}

/**
 * IDE-coupled wrapper. Reads auth, query, response headers, and the
 * active document's smart-cache settings from the IDE store, then
 * delegates to the pure {@link SmartCachePanelView}. Delete this
 * wrapper if porting to wp-graphql-smart-cache.
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
	const responseHeaders = useSelect(
		(s) => s('wpgraphql-ide/app').getResponseHeaders(),
		[]
	);
	const activeDoc = useSelect(
		(s) => s('wpgraphql-ide/document-editor').getActiveDocument(),
		[]
	);
	const globalGrantMode =
		(typeof window !== 'undefined' &&
			window.WPGRAPHQL_IDE_DATA?.documentSettings?.globalGrantMode) ||
		'public';

	return (
		<SmartCachePanelView
			data={data}
			isAuthenticated={isAuthenticated}
			isMutation={detectMutation(query)}
			cacheControl={pickCacheControl(responseHeaders)}
			docSettings={activeDoc?.documentSettings}
			globalGrantMode={globalGrantMode}
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
 * @param {string}  [props.cacheControl]
 * @param {Object}  [props.docSettings]
 * @param {string}  [props.globalGrantMode]
 *
 * @return {JSX.Element} Smart Cache panel UI.
 */
export function SmartCachePanelView({
	data,
	isAuthenticated,
	isMutation,
	cacheControl,
	docSettings,
	globalGrantMode,
}) {
	const objectCache = data?.graphqlObjectCache;
	const isHit = !!objectCache?.cacheKey;
	const [copied, setCopied] = useState(false);

	// Increment session counter when a new response arrives. Guarded by
	// the data reference so re-renders don't double-count.
	const lastSeenRef = useRef(null);
	useEffect(() => {
		if (data && data !== lastSeenRef.current) {
			lastSeenRef.current = data;
			recordResult(!!data?.graphqlObjectCache?.cacheKey);
		}
	}, [data]);
	const stats = useSyncExternalStore(subscribeSessionStats, getSessionStats);

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

			<SessionCounter stats={stats} />

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

			<DocumentPolicyCard
				docSettings={docSettings}
				globalGrantMode={globalGrantMode}
			/>

			<NetworkCacheCard cacheControl={cacheControl} />

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

// Header maps may key as `cache-control` or `Cache-Control` depending on
// the fetch implementation; normalize to lowercase before reading.
function pickCacheControl(headers) {
	if (!headers || typeof headers !== 'object') {
		return null;
	}
	for (const [k, v] of Object.entries(headers)) {
		if (k.toLowerCase() === 'cache-control') {
			return v;
		}
	}
	return null;
}

function SessionCounter({ stats }) {
	const total = stats.hit + stats.miss;
	if (total === 0) {
		return null;
	}
	const ratio = total > 0 ? Math.round((stats.hit / total) * 100) : 0;
	return (
		<div
			className="wpgraphql-ide-smart-cache-session"
			aria-label="Session cache statistics"
		>
			<span className="wpgraphql-ide-smart-cache-session-label">
				This session:
			</span>
			<span className="wpgraphql-ide-smart-cache-session-stat is-hit">
				{stats.hit} HIT
			</span>
			<span className="wpgraphql-ide-smart-cache-session-stat is-miss">
				{stats.miss} MISS
			</span>
			<span className="wpgraphql-ide-smart-cache-session-ratio">
				({ratio}% hit rate)
			</span>
		</div>
	);
}

function DocumentPolicyCard({ docSettings, globalGrantMode }) {
	const maxAge = docSettings?.maxAgeHeader;
	const grant = docSettings?.grant;

	if (!docSettings && !globalGrantMode) {
		return null;
	}

	const maxAgeText =
		maxAge !== undefined && maxAge !== null && maxAge !== ''
			? `${maxAge}s (set on this document)`
			: 'Global default';
	const grantText = grant ? labelForGrant(grant) : 'Global default';

	return (
		<dl className="wpgraphql-ide-smart-cache-policy">
			<dt>Configured for this document</dt>
			<dd>
				<div className="wpgraphql-ide-smart-cache-policy-row">
					<span className="wpgraphql-ide-smart-cache-policy-label">
						Max-age:
					</span>
					<span>{maxAgeText}</span>
				</div>
				<div className="wpgraphql-ide-smart-cache-policy-row">
					<span className="wpgraphql-ide-smart-cache-policy-label">
						Access:
					</span>
					<span>
						{grantText}
						{!grant && (
							<>
								{' '}
								<span className="wpgraphql-ide-smart-cache-policy-muted">
									(currently:{' '}
									{labelForGrant(globalGrantMode || 'public')}
									)
								</span>
							</>
						)}
					</span>
				</div>
			</dd>
		</dl>
	);
}

function labelForGrant(grant) {
	if (grant === 'allow' || grant === 'public') {
		return 'Allowed';
	}
	if (grant === 'deny') {
		return 'Denied';
	}
	return grant;
}

function NetworkCacheCard({ cacheControl }) {
	if (!cacheControl) {
		return null;
	}

	const interpretation = interpretCacheControl(cacheControl);

	return (
		<dl className="wpgraphql-ide-smart-cache-network">
			<dt>Network cache (Cache-Control)</dt>
			<dd>
				<code>{cacheControl}</code>
				{interpretation && (
					<div className="wpgraphql-ide-smart-cache-network-explainer">
						{interpretation}
					</div>
				)}
			</dd>
		</dl>
	);
}

function interpretCacheControl(value) {
	if (!value) {
		return null;
	}
	const lower = value.toLowerCase();
	if (lower.includes('no-store')) {
		return 'Downstream caches (Varnish, CDNs) will not store this response.';
	}
	const m = /max-age=(\d+)/.exec(lower);
	if (m) {
		const secs = parseInt(m[1], 10);
		return `Downstream caches may store this response for up to ${secs}s.`;
	}
	return null;
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
