import React, { useMemo, useState, useCallback } from 'react';
import { useDispatch, useSelect } from '@wordpress/data';
import { detectNPlusOne } from './detect-n-plus-one';
import { resolvePathToOffset } from './resolve-path-to-offset';

// Tracing emits microseconds. Format ms when ≥ 1ms, s when ≥ 1s.
const formatDuration = (value) => {
	if (typeof value !== 'number' || !isFinite(value)) {
		return '—';
	}
	if (value >= 1_000_000) {
		return `${(value / 1_000_000).toFixed(2)} s`;
	}
	if (value >= 1000) {
		return `${(value / 1000).toFixed(2)} ms`;
	}
	return `${value} µs`;
};

const formatPath = (path) =>
	Array.isArray(path) ? path.join('.') : String(path ?? '');

const SORT_OPTIONS = [
	{ value: 'duration', label: 'Duration (slowest first)' },
	{ value: 'offset', label: 'Start offset (execution order)' },
	{ value: 'path', label: 'Path (alphabetical)' },
];

// Per-resolver tier is for the bar color in each row.
const TIER_FAST_MAX_US = 1000; // < 1 ms
const TIER_OK_MAX_US = 10_000; // 1–10 ms

const tierForDuration = (us) => {
	if (us < TIER_FAST_MAX_US) {
		return 'fast';
	}
	if (us < TIER_OK_MAX_US) {
		return 'ok';
	}
	return 'slow';
};

// Trivial threshold: noise rows like pageInfo subfields that resolve in
// 0–1 µs aren't actionable; hidden by default.
const TRIVIAL_MAX_US = 1;

// Total-duration tiers drive the verdict copy at the top.
const verdictForTotal = (totalUs) => {
	if (typeof totalUs !== 'number') {
		return { tier: 'unknown', label: 'No timing data' };
	}
	if (totalUs < 100_000) {
		return { tier: 'fast', label: 'Fast' };
	}
	if (totalUs < 500_000) {
		return { tier: 'ok', label: 'OK' };
	}
	return { tier: 'slow', label: 'Slow' };
};

export const TracingExtensionTab = ({ data }) => {
	const [sortBy, setSortBy] = useState('duration');
	const [hideTrivial, setHideTrivial] = useState(true);

	const query = useSelect(
		(select) => select('wpgraphql-ide/app').getQuery() || '',
		[]
	);
	const { setEditorJumpRequest } = useDispatch('wpgraphql-ide/app');

	const jumpToPath = useCallback(
		(path) => {
			const offset = resolvePathToOffset(query, path);
			if (typeof offset === 'number') {
				setEditorJumpRequest(offset);
			}
		},
		[query, setEditorJumpRequest]
	);

	const hasData = data && typeof data === 'object';
	const resolvers = useMemo(() => {
		if (!hasData) {
			return [];
		}
		return Array.isArray(data.execution?.resolvers)
			? data.execution.resolvers
			: [];
	}, [hasData, data]);

	const nPlusOnePatterns = useMemo(
		() => detectNPlusOne(resolvers),
		[resolvers]
	);

	const slowest = useMemo(() => {
		if (resolvers.length === 0) {
			return null;
		}
		return resolvers.reduce(
			(max, r) => ((r.duration || 0) > (max.duration || 0) ? r : max),
			resolvers[0]
		);
	}, [resolvers]);

	const resolverTotal = useMemo(
		() => resolvers.reduce((sum, r) => sum + (r.duration || 0), 0),
		[resolvers]
	);

	const trivialCount = useMemo(
		() =>
			resolvers.filter((r) => (r.duration || 0) <= TRIVIAL_MAX_US).length,
		[resolvers]
	);

	if (!hasData) {
		return (
			<p className="wpgraphql-ide-extensions-empty">
				No tracing data in the last response. Enable GraphQL Tracing in
				WPGraphQL settings to see field-level timing here.
			</p>
		);
	}

	const verdict = verdictForTotal(data.duration);
	const slowestPct =
		slowest && resolverTotal > 0
			? Math.round(((slowest.duration || 0) / resolverTotal) * 100)
			: 0;
	const slowestMax = slowest ? slowest.duration || 0 : 0;

	const filteredResolvers = hideTrivial
		? resolvers.filter((r) => (r.duration || 0) > TRIVIAL_MAX_US)
		: resolvers;

	const sortedResolvers = [...filteredResolvers].sort((a, b) => {
		if (sortBy === 'duration') {
			return (b.duration || 0) - (a.duration || 0);
		}
		if (sortBy === 'offset') {
			return (a.startOffset || 0) - (b.startOffset || 0);
		}
		return formatPath(a.path).localeCompare(formatPath(b.path));
	});

	return (
		<div className="wpgraphql-ide-tracing-panel">
			<header
				className="wpgraphql-ide-tracing-verdict"
				data-tier={verdict.tier}
			>
				<div className="wpgraphql-ide-tracing-verdict-headline">
					<span
						className="wpgraphql-ide-tracing-verdict-dot"
						aria-hidden="true"
					/>
					<span className="wpgraphql-ide-tracing-verdict-label">
						{verdict.label}
					</span>
					<span className="wpgraphql-ide-tracing-verdict-summary">
						{formatDuration(data.duration)} across{' '}
						{resolvers.length}{' '}
						{resolvers.length === 1 ? 'resolver' : 'resolvers'}
					</span>
				</div>
				{slowest && (
					<p className="wpgraphql-ide-tracing-verdict-detail">
						Slowest field: <code>{formatPath(slowest.path)}</code> —{' '}
						{formatDuration(slowest.duration)}
						{slowestPct > 0 && resolvers.length > 1
							? ` (${slowestPct}% of resolver time)`
							: ''}
					</p>
				)}
				{nPlusOnePatterns.length > 0 && (
					<p className="wpgraphql-ide-tracing-verdict-detail">
						{nPlusOnePatterns.length} likely N+1{' '}
						{nPlusOnePatterns.length === 1 ? 'pattern' : 'patterns'}{' '}
						detected — see below.
					</p>
				)}
			</header>

			{nPlusOnePatterns.length > 0 && (
				<section
					className="wpgraphql-ide-tracing-n1"
					aria-label="Possible N+1 patterns"
				>
					<h4 className="wpgraphql-ide-tracing-n1-title">
						Possible N+1 patterns
					</h4>
					<p className="wpgraphql-ide-tracing-n1-description">
						These fields were resolved repeatedly in series — one
						call per parent item. A DataLoader could batch them into
						a single call.
					</p>
					<table className="wpgraphql-ide-tracing-table wpgraphql-ide-tracing-n1-table">
						<thead>
							<tr>
								<th>Path pattern</th>
								<th className="is-numeric">Calls</th>
								<th className="is-numeric">Total</th>
								<th className="is-numeric">Avg / call</th>
							</tr>
						</thead>
						<tbody>
							{nPlusOnePatterns.map((p) => {
								const examplePath = p.pattern
									.split('.')
									.map((seg) => (seg === '*' ? 0 : seg));
								const onActivate = () =>
									jumpToPath(examplePath);
								return (
									<tr
										key={p.pattern}
										className="wpgraphql-ide-tracing-row is-clickable"
										tabIndex={0}
										role="button"
										aria-label={`Jump to ${p.pattern} in the editor`}
										onClick={onActivate}
										onKeyDown={(e) => {
											if (
												e.key === 'Enter' ||
												e.key === ' '
											) {
												e.preventDefault();
												onActivate();
											}
										}}
									>
										<td>
											<code className="wpgraphql-ide-tracing-path">
												{p.pattern}
											</code>
										</td>
										<td className="is-numeric">
											{p.count}
										</td>
										<td className="is-numeric">
											{formatDuration(p.totalDuration)}
										</td>
										<td className="is-numeric">
											{formatDuration(p.avgDuration)}
										</td>
									</tr>
								);
							})}
						</tbody>
					</table>
				</section>
			)}

			{resolvers.length > 0 && (
				<>
					<div className="wpgraphql-ide-tracing-controls">
						<label htmlFor="wpgraphql-ide-tracing-sort">
							<span>Sort by</span>
							<select
								id="wpgraphql-ide-tracing-sort"
								value={sortBy}
								onChange={(e) => setSortBy(e.target.value)}
							>
								{SORT_OPTIONS.map((opt) => (
									<option key={opt.value} value={opt.value}>
										{opt.label}
									</option>
								))}
							</select>
						</label>
						{trivialCount > 0 && (
							<label
								className="wpgraphql-ide-tracing-trivial-toggle"
								htmlFor="wpgraphql-ide-tracing-hide-trivial"
							>
								<input
									id="wpgraphql-ide-tracing-hide-trivial"
									type="checkbox"
									checked={hideTrivial}
									onChange={(e) =>
										setHideTrivial(e.target.checked)
									}
								/>
								<span>
									Hide trivial fields (under 1 µs) ·{' '}
									{trivialCount} hidden
								</span>
							</label>
						)}
					</div>

					<table className="wpgraphql-ide-tracing-table">
						<thead>
							<tr>
								<th>Path</th>
								<th className="wpgraphql-ide-tracing-return-cell">
									Return Type
								</th>
								<th className="is-numeric is-bar-col">
									Duration
								</th>
							</tr>
						</thead>
						<tbody>
							{sortedResolvers.map((r, i) => {
								const dur = r.duration || 0;
								const pct =
									slowestMax > 0
										? Math.max(
												2,
												Math.round(
													(dur / slowestMax) * 100
												)
											)
										: 0;
								const tier = tierForDuration(dur);
								return (
									<tr
										key={`${formatPath(r.path)}-${i}`}
										className="wpgraphql-ide-tracing-row is-clickable"
										tabIndex={0}
										role="button"
										aria-label={`Jump to ${formatPath(r.path)} in the editor`}
										onClick={() => jumpToPath(r.path)}
										onKeyDown={(e) => {
											if (
												e.key === 'Enter' ||
												e.key === ' '
											) {
												e.preventDefault();
												jumpToPath(r.path);
											}
										}}
									>
										<td>
											<code className="wpgraphql-ide-tracing-path">
												{formatPath(r.path)}
											</code>
											<span className="wpgraphql-ide-tracing-parent">
												{r.parentType}
												<span className="wpgraphql-ide-tracing-return-inline">
													{' → '}
													<code>{r.returnType}</code>
												</span>
											</span>
										</td>
										<td className="wpgraphql-ide-tracing-return-cell">
											<code>{r.returnType}</code>
										</td>
										<td
											className="is-numeric is-bar-col"
											data-tier={tier}
											style={{ '--bar-pct': `${pct}%` }}
										>
											<span className="wpgraphql-ide-tracing-bar-track" />
											<span className="wpgraphql-ide-tracing-bar-value">
												{formatDuration(dur)}
											</span>
										</td>
									</tr>
								);
							})}
						</tbody>
					</table>
				</>
			)}
		</div>
	);
};
