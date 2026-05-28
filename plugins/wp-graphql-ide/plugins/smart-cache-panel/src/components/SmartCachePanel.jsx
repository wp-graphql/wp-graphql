/* global navigator */
import React, { useEffect, useState, useSyncExternalStore } from 'react';
import { Button } from '@wordpress/components';
import { select, useSelect } from '@wordpress/data';
import { addAction } from '@wordpress/hooks';
import { Icon, check, cancelCircleFilled, help, info } from '@wordpress/icons';

// Module-scoped session counter so HIT/MISS totals survive Smart Cache
// panel remounts (switching to Tracing and back). Scoped to the inputs
// the server hashes for its cache key — query + variables + auth +
// operation name — so any change that would produce a different bucket
// zeroes the running totals. Stats for the previous bucket are dropped
// on purpose; tracking historical hit rates across ops is out of scope.
// Cleared on page reload.
let sessionStats = { hit: 0, miss: 0, key: null };
const sessionSubscribers = new Set();
function notifyStats() {
	sessionSubscribers.forEach((fn) => fn());
}
function recordResult(isHit) {
	sessionStats = {
		...sessionStats,
		hit: sessionStats.hit + (isHit ? 1 : 0),
		miss: sessionStats.miss + (isHit ? 0 : 1),
	};
	notifyStats();
}
/**
 * Compose the cache-key signature from the inputs the server hashes
 * (query + variables + auth + operation). Pipe-separator collisions
 * would only mean the counter doesn't reset when it should, which is
 * harmless.
 *
 * @param {string|null|undefined} query
 * @param {string|null|undefined} variables
 * @param {boolean}               isAuthenticated
 * @param {string|null|undefined} operationName
 *
 * @return {string} Stable signature for the current cache-key inputs.
 */
function composeCacheKeySignature(
	query,
	variables,
	isAuthenticated,
	operationName
) {
	const q = typeof query === 'string' ? query : '';
	const v = typeof variables === 'string' ? variables : '';
	const o = typeof operationName === 'string' ? operationName : '';
	return `${q}|${v}|${isAuthenticated ? '1' : '0'}|${o}`;
}
function setActiveCacheKey(signature) {
	const next = typeof signature === 'string' ? signature : '';
	if (sessionStats.key === next) {
		return;
	}
	sessionStats = { hit: 0, miss: 0, key: next };
	notifyStats();
}
function subscribeSessionStats(fn) {
	sessionSubscribers.add(fn);
	return () => sessionSubscribers.delete(fn);
}
function getSessionStats() {
	return sessionStats;
}
// Test-only hatches — let specs simulate a fresh page load and a
// recorded response without going through the IDE-coupled wrapper.
export function _resetSessionStatsForTests() {
	sessionStats = { hit: 0, miss: 0, key: null };
	notifyStats();
}
export function _recordResultForTests(isHit) {
	recordResult(isHit);
}
export function _setActiveCacheKeyForTests(
	query,
	variables,
	isAuthenticated,
	operationName
) {
	setActiveCacheKey(
		composeCacheKeySignature(
			query,
			variables,
			isAuthenticated,
			operationName
		)
	);
}

// Variables ride the request envelope as a parsed object; normalize to a
// string so they participate in the cache-key signature the same way the
// editor's raw JSON would.
function stringifyVariables(variables) {
	if (variables === null || variables === undefined) {
		return '';
	}
	if (typeof variables === 'string') {
		return variables;
	}
	try {
		return JSON.stringify(variables);
	} catch (err) {
		return '';
	}
}

// Auth toggling flips user_id in the server's cache-key inputs, so it has
// to feed the signature too. Read defensively — the store may not be
// registered yet under test.
function readIsAuthenticated() {
	try {
		const store = select('wpgraphql-ide/app');
		return store ? !!store.isAuthenticated() : false;
	} catch (err) {
		return false;
	}
}

let sessionTrackingStarted = false;

/**
 * Record HIT/MISS off the IDE's per-execution action rather than from
 * inside the panel component. The panel only mounts while its response
 * tab is active, so a component-local effect stops counting the moment
 * the user switches to another tab. `wpgraphql-ide.afterExecute` fires
 * once per completed execution regardless of which tab is mounted —
 * aborted and short-circuited runs never fire it, so they correctly
 * don't count. Idempotent; safe to call on every IDE-ready event.
 *
 * @return {void}
 */
export function initSmartCacheSessionTracking() {
	if (sessionTrackingStarted) {
		return;
	}
	sessionTrackingStarted = true;
	addAction(
		'wpgraphql-ide.afterExecute',
		'wpgraphql-ide/smart-cache-session',
		(envelope) => {
			const request = envelope?.request || {};
			// Sync the active bucket to the executed inputs first; a bucket
			// change zeroes the running totals before this result lands.
			setActiveCacheKey(
				composeCacheKeySignature(
					request.query,
					stringifyVariables(request.variables),
					readIsAuthenticated(),
					request.operationName
				)
			);
			recordResult(
				!!envelope?.result?.extensions?.graphqlSmartCache
					?.graphqlObjectCache?.cacheKey
			);
		}
	);
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

	// HIT/MISS recording lives in `initSmartCacheSessionTracking` (driven
	// by `wpgraphql-ide.afterExecute`), not here — this panel unmounts when
	// another response tab is active, and the counter must keep running.

	return (
		<SmartCachePanelView
			data={data}
			isAuthenticated={isAuthenticated}
			isMutation={detectMutation(query)}
			isIntrospection={detectIntrospection(query)}
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
 * @param {boolean} props.isIntrospection
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
	isIntrospection,
	cacheControl,
	docSettings,
	globalGrantMode,
}) {
	const objectCache = data?.graphqlObjectCache;
	const diagnostics = data?.diagnostics;
	const isHit = !!objectCache?.cacheKey;
	const [copied, setCopied] = useState(false);

	// The wrapper component records HIT/MISS once per response; the view
	// just subscribes to the running totals.
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
				<span
					className="wpgraphql-ide-smart-cache-status-icon"
					aria-hidden="true"
				>
					<Icon icon={isHit ? check : info} size={20} />
				</span>
				<span className="wpgraphql-ide-smart-cache-status-label">
					{isHit ? 'Cache HIT' : 'Cache MISS'}
				</span>
				<span className="wpgraphql-ide-smart-cache-status-explainer">
					{isHit
						? 'Returned from the GraphQL Object Cache — no resolvers ran.'
						: missHeadline({
								isAuthenticated,
								isMutation,
								isIntrospection,
							})}
				</span>
			</div>

			<SessionCounter stats={stats} />

			<TtlCard isHit={isHit} diagnostics={diagnostics} />

			{isHit && objectCache?.cacheKey && (
				<DetailsSection
					summaryLabel="Cache key"
					className="wpgraphql-ide-smart-cache-cache-key-details"
					defaultOpen
				>
					<div className="wpgraphql-ide-smart-cache-key-row">
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
					</div>
				</DetailsSection>
			)}

			<PurgeMapSection diagnostics={diagnostics} defaultOpen={isHit} />

			<SkippedKeysSection diagnostics={diagnostics} />

			<DocumentPolicySection
				docSettings={docSettings}
				globalGrantMode={globalGrantMode}
				globalTtl={diagnostics?.globalTtl}
			/>

			<NetworkCacheSection cacheControl={cacheControl} />

			{!isHit && (
				<DetailsSection
					summaryLabel="Why this missed"
					summaryAside={prereqAside({
						isAuthenticated,
						isMutation,
						isIntrospection,
					})}
					className="wpgraphql-ide-smart-cache-prereqs-details"
					defaultOpen
				>
					<PrerequisiteChecklist
						isAuthenticated={isAuthenticated}
						isMutation={isMutation}
						isIntrospection={isIntrospection}
						cached={isHit}
					/>
				</DetailsSection>
			)}
		</div>
	);
}

function DetailsSection({
	summaryLabel,
	summaryAside,
	className,
	defaultOpen,
	children,
}) {
	const classes = ['wpgraphql-ide-smart-cache-details', className]
		.filter(Boolean)
		.join(' ');
	const detailsProps = defaultOpen ? { open: true } : {};
	return (
		<details className={classes} {...detailsProps}>
			<summary className="wpgraphql-ide-smart-cache-details-summary">
				<span className="wpgraphql-ide-smart-cache-details-label">
					{summaryLabel}
				</span>
				{summaryAside && (
					<span className="wpgraphql-ide-smart-cache-details-aside">
						{summaryAside}
					</span>
				)}
			</summary>
			<div className="wpgraphql-ide-smart-cache-details-body">
				{children}
			</div>
		</details>
	);
}

// Strips comments and string literals before testing for a top-level
// `mutation` keyword — avoids false positives from `# mutation foo`.
function detectMutation(query) {
	if (!query || typeof query !== 'string') {
		return false;
	}
	const stripped = stripCommentsAndStrings(query);
	return /(^|\s)mutation\b/.test(stripped);
}

// Same comment / string scrubbing, then look for `__schema` or `__type`
// — the two introspection root fields. Selecting either of those is
// what causes the IDE's fetcher (App.jsx) to force credentials + nonce
// on the request, regardless of the auth toggle state. That in turn
// means the server sees a viewer and Smart Cache stays disabled.
function detectIntrospection(query) {
	if (!query || typeof query !== 'string') {
		return false;
	}
	const stripped = stripCommentsAndStrings(query);
	return /\b(__schema|__type)\b/.test(stripped);
}

function stripCommentsAndStrings(query) {
	return query
		.replace(/#.*$/gm, '')
		.replace(/"""[\s\S]*?"""/g, '')
		.replace(/"(?:\\.|[^"\\])*"/g, '');
}

function missHeadline({ isAuthenticated, isMutation, isIntrospection }) {
	if (isMutation) {
		return 'Mutations are never cached — resolvers ran.';
	}
	if (isIntrospection) {
		return 'Introspection always authenticates — resolvers ran.';
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

function TtlCard({ isHit, diagnostics }) {
	const expiresAt = diagnostics?.expiresAt;
	const cachedAt = diagnostics?.cachedAt;
	const globalTtl = diagnostics?.globalTtl;
	const storage = diagnostics?.storage;

	// Tick once per second so the countdown / progress bar update live
	// for the duration the user has the panel open. Cleared on unmount;
	// the timer is gated on having a transient HIT to count down against
	// so we don't spin pointlessly on misses or object-cache backends.
	const shouldTick =
		isHit && storage !== 'object_cache' && typeof expiresAt === 'number';
	const [now, setNow] = useState(() => Math.floor(Date.now() / 1000));
	useEffect(() => {
		if (!shouldTick) {
			return undefined;
		}
		const id = setInterval(() => {
			setNow(Math.floor(Date.now() / 1000));
		}, 1000);
		return () => clearInterval(id);
	}, [shouldTick]);

	if (!diagnostics) {
		return null;
	}

	// Object cache backend (Redis/Memcache) — the backend enforces TTL
	// but doesn't expose a countdown to PHP.
	if (isHit && storage === 'object_cache') {
		return (
			<dl className="wpgraphql-ide-smart-cache-ttl">
				<dt>TTL</dt>
				<dd>
					Object cache backend (Redis/Memcache) — TTL enforced by the
					backend but not introspectable.
				</dd>
			</dl>
		);
	}

	// HIT with transient timeout — show live countdown driven by `now`.
	if (isHit && typeof expiresAt === 'number') {
		const remaining = Math.max(0, expiresAt - now);
		const ageSec =
			typeof cachedAt === 'number' ? Math.max(0, now - cachedAt) : null;
		const totalTtl = globalTtl || (ageSec || 0) + remaining;
		const elapsed = totalTtl > 0 ? Math.min(totalTtl, ageSec || 0) : 0;
		const isExpired = remaining === 0;
		return (
			<dl className="wpgraphql-ide-smart-cache-ttl">
				<dt>TTL</dt>
				<dd>
					<div>
						{isExpired ? (
							<strong>Expired</strong>
						) : (
							<>
								<strong>{formatDuration(remaining)}</strong>{' '}
								remaining
							</>
						)}
						{ageSec !== null && (
							<>
								{' '}
								<span className="wpgraphql-ide-smart-cache-ttl-muted">
									(cached {formatDuration(ageSec)} ago)
								</span>
							</>
						)}
					</div>
					{totalTtl > 0 && (
						<progress
							className={`wpgraphql-ide-smart-cache-ttl-progress${
								isExpired ? ' is-expired' : ''
							}`}
							value={elapsed}
							max={totalTtl}
							aria-label={`Cache age: ${formatDuration(elapsed)} of ${formatDuration(totalTtl)}`}
						/>
					)}
				</dd>
			</dl>
		);
	}

	// MISS — surface the global TTL so the user knows what to expect.
	if (!isHit && typeof globalTtl === 'number' && globalTtl > 0) {
		return (
			<dl className="wpgraphql-ide-smart-cache-ttl">
				<dt>TTL</dt>
				<dd>
					Global default: <strong>{formatDuration(globalTtl)}</strong>
				</dd>
			</dl>
		);
	}

	return null;
}

function formatDuration(seconds) {
	const s = Math.max(0, Math.floor(seconds));
	if (s < 60) {
		return `${s}s`;
	}
	const m = Math.floor(s / 60);
	const r = s % 60;
	if (m < 60) {
		return r ? `${m}m ${r}s` : `${m}m`;
	}
	const h = Math.floor(m / 60);
	const rm = m % 60;
	return rm ? `${h}h ${rm}m` : `${h}h`;
}

/**
 * Bucket each X-GraphQL-Keys entry by its prefix. The categories are
 * how Smart Cache (and downstream CDN) addresses tags for invalidation:
 *
 *   - Query ID (64-char hex) — the X-GraphQL-Query-ID header value;
 *     a hash of the normalized query document, identifies the query
 *     *shape* regardless of variables/auth. **Not** the same as the
 *     Smart Cache transient key shown in the top "Cache key" section
 *     (that one hashes query + variables + operation + user_id).
 *   - Root types (`graphql:*`) — schema root type tags
 *   - Operations (`operation:*`) — the named operation that produced this response
 *   - List types (`list:*`) — the list/connection types touched
 *   - Nodes — everything else, almost always base64 Relay IDs
 *
 * Falls back to `purgeMap.nodes` / `purgeMap.lists` (the legacy
 * structured fields) when `purgeMap.keys` isn't populated — keeps
 * older cached entries rendering correctly until they refresh.
 *
 * @param {Array<string>} keys X-GraphQL-Keys entries to bucket.
 *
 * @return {{ queryId: Array, root: Array, operations: Array, lists: Array, nodes: Array }} Keys grouped by category.
 */
function categorizeKeys(keys) {
	const cats = {
		queryId: [],
		root: [],
		operations: [],
		lists: [],
		nodes: [],
	};
	for (const k of keys) {
		if (/^[a-f0-9]{64}$/.test(k)) {
			cats.queryId.push(k);
		} else if (k.startsWith('list:')) {
			cats.lists.push(k);
		} else if (k.startsWith('operation:')) {
			cats.operations.push(k);
		} else if (k.startsWith('graphql:')) {
			cats.root.push(k);
		} else {
			cats.nodes.push(k);
		}
	}
	return cats;
}

function PurgeMapSection({ diagnostics, defaultOpen }) {
	const purgeMap = diagnostics?.purgeMap;
	if (!purgeMap) {
		return null;
	}

	const keys = Array.isArray(purgeMap.keys) ? purgeMap.keys : [];
	const cats = categorizeKeys(keys);
	// Back-compat: if the response was cached before `keys` started
	// flowing through, fall back to the legacy structured fields.
	if (keys.length === 0) {
		cats.lists = Array.isArray(purgeMap.lists) ? purgeMap.lists : [];
		cats.nodes = Array.isArray(purgeMap.nodes) ? purgeMap.nodes : [];
	}
	const total =
		cats.queryId.length +
		cats.root.length +
		cats.operations.length +
		cats.lists.length +
		cats.nodes.length;

	const groups = [
		{ key: 'queryId', label: 'Query ID', items: cats.queryId },
		{ key: 'root', label: 'Root types', items: cats.root },
		{ key: 'operations', label: 'Operations', items: cats.operations },
		{ key: 'lists', label: 'List types', items: cats.lists },
		{ key: 'nodes', label: 'Nodes', items: cats.nodes },
	];

	const aside =
		total === 0 ? (
			<span>untracked</span>
		) : (
			<span>
				{total} {total === 1 ? 'tag' : 'tags'}
			</span>
		);

	return (
		<DetailsSection
			summaryLabel="Purge map"
			summaryAside={aside}
			className="wpgraphql-ide-smart-cache-purge-map"
			defaultOpen={defaultOpen}
		>
			{total === 0 ? (
				<div className="wpgraphql-ide-smart-cache-purge-map-empty">
					No tags emitted — Query Analyzer didn&apos;t match this
					query to invalidatable resources, so it won&apos;t be
					auto-purged on data changes.
				</div>
			) : (
				<>
					<div className="wpgraphql-ide-smart-cache-purge-map-explainer">
						Tags emitted on this response. Smart Cache invalidates
						this entry when any matching list type or node changes;
						downstream caches (CDN, proxy) can purge by any of these
						tags.
					</div>
					{groups
						.filter((g) => g.items.length > 0)
						.map((g) => (
							<div
								key={g.key}
								className="wpgraphql-ide-smart-cache-purge-map-group"
							>
								<div className="wpgraphql-ide-smart-cache-purge-map-group-label">
									{g.label} ({g.items.length})
								</div>
								<ul className="wpgraphql-ide-smart-cache-purge-map-list">
									{g.items.map((item) => (
										<li key={`${g.key}:${item}`}>
											<code>{item}</code>
										</li>
									))}
								</ul>
							</div>
						))}
				</>
			)}
		</DetailsSection>
	);
}

function SkippedKeysSection({ diagnostics }) {
	const skipped = diagnostics?.skipped;
	if (!skipped || typeof skipped !== 'object' || (skipped.count || 0) === 0) {
		return null;
	}

	const keys = Array.isArray(skipped.keys) ? skipped.keys : [];
	const types = Array.isArray(skipped.types) ? skipped.types : [];
	const count = skipped.count || keys.length;
	const size = skipped.size || 0;

	return (
		<DetailsSection
			summaryLabel="Cache integrity warning"
			summaryAside={
				<span>
					{count} skipped{size > 0 ? ` · ${size} chars` : ''}
				</span>
			}
			className="wpgraphql-ide-smart-cache-skipped"
			defaultOpen
		>
			<div className="wpgraphql-ide-smart-cache-skipped-explainer">
				The Query Analyzer hit its header-length budget and dropped the
				entries below from <code>X-GraphQL-Keys</code>. Smart Cache
				won&apos;t invalidate this entry when these change — the cache
				may serve stale data for affected resources. Raise the limit via
				the <code>graphql_query_analyzer_header_length_limit</code>{' '}
				filter (default 8000) to fit more keys.
			</div>
			{types.length > 0 && (
				<div className="wpgraphql-ide-smart-cache-purge-map-group">
					<div className="wpgraphql-ide-smart-cache-purge-map-group-label">
						Skipped types ({types.length})
					</div>
					<ul className="wpgraphql-ide-smart-cache-purge-map-list">
						{types.map((name) => (
							<li key={`skipped-type:${name}`}>
								<code>{name}</code>
							</li>
						))}
					</ul>
				</div>
			)}
			{keys.length > 0 && (
				<div className="wpgraphql-ide-smart-cache-purge-map-group">
					<div className="wpgraphql-ide-smart-cache-purge-map-group-label">
						Skipped keys ({keys.length})
					</div>
					<ul className="wpgraphql-ide-smart-cache-purge-map-list">
						{keys.map((id) => (
							<li key={`skipped-key:${id}`}>
								<code>{id}</code>
							</li>
						))}
					</ul>
				</div>
			)}
		</DetailsSection>
	);
}

function DocumentPolicySection({ docSettings, globalGrantMode, globalTtl }) {
	const maxAge = docSettings?.maxAgeHeader;
	const grant = docSettings?.grant;

	if (!docSettings && !globalGrantMode) {
		return null;
	}

	// Value-first / source-second so each row reads as a single fact
	// instead of "default — actually <value>" doubled-up phrasing.
	const maxAgeIsCustom =
		maxAge !== undefined && maxAge !== null && maxAge !== '';
	let maxAgeValue;
	if (maxAgeIsCustom) {
		maxAgeValue = `${maxAge}s`;
	} else if (globalTtl) {
		maxAgeValue = formatDuration(globalTtl);
	} else {
		maxAgeValue = null;
	}
	const maxAgeSource = maxAgeIsCustom
		? 'set on this document'
		: 'global default';

	const grantIsCustom = !!grant;
	const grantValue = labelForGrant(
		grantIsCustom ? grant : globalGrantMode || 'public'
	);
	const grantSource = grantIsCustom
		? 'set on this document'
		: 'global default';

	const summaryAside =
		maxAgeIsCustom || grantIsCustom ? (
			<span>customized</span>
		) : (
			<span>defaults</span>
		);

	return (
		<DetailsSection
			summaryLabel="Document settings"
			summaryAside={summaryAside}
			className="wpgraphql-ide-smart-cache-policy"
		>
			<div className="wpgraphql-ide-smart-cache-policy-row">
				<span className="wpgraphql-ide-smart-cache-policy-label">
					Max-age:
				</span>
				<span>
					{maxAgeValue || 'Global default'}{' '}
					<span className="wpgraphql-ide-smart-cache-policy-muted">
						({maxAgeSource})
					</span>
				</span>
			</div>
			<div className="wpgraphql-ide-smart-cache-policy-row">
				<span className="wpgraphql-ide-smart-cache-policy-label">
					Access:
				</span>
				<span>
					{grantValue}{' '}
					<span className="wpgraphql-ide-smart-cache-policy-muted">
						({grantSource})
					</span>
				</span>
			</div>
		</DetailsSection>
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

function NetworkCacheSection({ cacheControl }) {
	if (!cacheControl) {
		return null;
	}

	const interpretation = interpretCacheControl(cacheControl);

	return (
		<DetailsSection
			summaryLabel="Network cache"
			summaryAside={<code>{cacheControl}</code>}
			className="wpgraphql-ide-smart-cache-network"
		>
			{interpretation && (
				<div className="wpgraphql-ide-smart-cache-network-explainer">
					{interpretation}
				</div>
			)}
		</DetailsSection>
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

function PrerequisiteChecklist({
	isAuthenticated,
	isMutation,
	isIntrospection,
	cached,
}) {
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
					Smart Cache currently skips authenticated requests. Flip the
					IDE&apos;s auth toggle off — the fetcher will drop the
					session cookie + nonce on the way out, so the server sees an
					anonymous viewer. (Schema introspection still forces auth;
					see the next item.)
				</>
			),
		},
		{
			key: 'isIntrospection',
			label: 'Not an introspection query',
			state: isIntrospection ? 'blocking' : 'ok',
			fixHint: (
				<>
					Selecting <code>__schema</code> or <code>__type</code> makes
					the IDE&apos;s fetcher force credentials + nonce on the
					request — schema discovery relies on the viewer&apos;s
					permissions, so it has to authenticate even when the auth
					toggle is off. Cache won&apos;t apply. Run a non-
					introspection query (e.g.{' '}
					<code>{'{ posts { nodes { id } } }'}</code>) to test the
					cache.
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

	return (
		<div className="wpgraphql-ide-smart-cache-checklist">
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

function prereqAside({ isAuthenticated, isMutation, isIntrospection }) {
	let blockers = 1; // 'enabled' is always 'unknown' (counts as a question, not a fail)
	if (isAuthenticated) {
		blockers += 1;
	}
	if (isIntrospection) {
		blockers += 1;
	}
	if (isMutation) {
		blockers += 1;
	}
	blockers += 1; // 'cached' is blocking on a MISS
	return (
		<span>
			{blockers} thing{blockers === 1 ? '' : 's'} to check
		</span>
	);
}

function checklistGlyph(state) {
	if (state === 'ok') {
		return <Icon icon={check} size={16} />;
	}
	if (state === 'unknown') {
		return <Icon icon={help} size={16} />;
	}
	return <Icon icon={cancelCircleFilled} size={16} />;
}
