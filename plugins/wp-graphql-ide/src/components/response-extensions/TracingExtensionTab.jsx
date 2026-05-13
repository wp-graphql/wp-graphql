import React, { useMemo, useState, useCallback } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { detectNPlusOne } from './detect-n-plus-one';
import { resolvePathToOffset } from './resolve-path-to-offset';

// Tracing emits microseconds. Format ms when ≥ 1ms, s when ≥ 1s.
const formatDuration = (value) => {
	if (typeof value !== 'number' || !isFinite(value)) {
		return '—';
	}
	if (value >= 1_000_000) {
		return sprintf(
			/* translators: %s: pre-formatted seconds value, e.g. "1.23" */
			__('%s s', 'wpgraphql-ide'),
			(value / 1_000_000).toFixed(2)
		);
	}
	if (value >= 1000) {
		return sprintf(
			/* translators: %s: pre-formatted millisecond value, e.g. "12.34" */
			__('%s ms', 'wpgraphql-ide'),
			(value / 1000).toFixed(2)
		);
	}
	return sprintf(
		/* translators: %s: pre-formatted microsecond value */
		__('%s µs', 'wpgraphql-ide'),
		value
	);
};

const formatPath = (path) =>
	Array.isArray(path) ? path.join('.') : String(path ?? '');

// Built lazily so __() resolves after wp.i18n is available.
const getSortOptions = () => [
	{
		value: 'duration',
		label: __('Duration (slowest first)', 'wpgraphql-ide'),
	},
	{
		value: 'offset',
		label: __('Start offset (execution order)', 'wpgraphql-ide'),
	},
	{ value: 'path', label: __('Path (alphabetical)', 'wpgraphql-ide') },
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

// Total-duration tiers drive the verdict copy at the top. Computed lazily so
// __() resolves after wp.i18n is loaded.
const verdictForTotal = (totalUs) => {
	if (typeof totalUs !== 'number') {
		return {
			tier: 'unknown',
			label: __('No timing data', 'wpgraphql-ide'),
		};
	}
	if (totalUs < 100_000) {
		return { tier: 'fast', label: __('Fast', 'wpgraphql-ide') };
	}
	if (totalUs < 500_000) {
		return { tier: 'ok', label: __('OK', 'wpgraphql-ide') };
	}
	return { tier: 'slow', label: __('Slow', 'wpgraphql-ide') };
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
				{__(
					'No tracing data in the last response. Enable GraphQL Tracing in WPGraphQL settings to see field-level timing here.',
					'wpgraphql-ide'
				)}
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
						{sprintf(
							/* translators: 1: formatted duration with unit (e.g. "12.34 ms"); 2: number of GraphQL resolvers */
							_n(
								'%1$s across %2$d resolver',
								'%1$s across %2$d resolvers',
								resolvers.length,
								'wpgraphql-ide'
							),
							formatDuration(data.duration),
							resolvers.length
						)}
					</span>
				</div>
				{slowest && (
					<p className="wpgraphql-ide-tracing-verdict-detail">
						{sprintf(
							/* translators: 1: GraphQL path of the slowest resolver; 2: formatted duration of that resolver */
							__('Slowest field: %1$s — %2$s', 'wpgraphql-ide'),
							formatPath(slowest.path),
							formatDuration(slowest.duration)
						)}
						{slowestPct > 0 && resolvers.length > 1
							? sprintf(
									/* translators: %s: pre-formatted percentage string (already includes %), e.g. "42%" */
									__(
										' (%s of resolver time)',
										'wpgraphql-ide'
									),
									`${slowestPct}%`
								)
							: ''}
					</p>
				)}
				{nPlusOnePatterns.length > 0 && (
					<p className="wpgraphql-ide-tracing-verdict-detail">
						{sprintf(
							/* translators: %d: number of likely N+1 query patterns detected */
							_n(
								'%d likely N+1 pattern detected — see below.',
								'%d likely N+1 patterns detected — see below.',
								nPlusOnePatterns.length,
								'wpgraphql-ide'
							),
							nPlusOnePatterns.length
						)}
					</p>
				)}
			</header>

			{nPlusOnePatterns.length > 0 && (
				<section
					className="wpgraphql-ide-tracing-n1"
					aria-label={__('Possible N+1 patterns', 'wpgraphql-ide')}
				>
					<h4 className="wpgraphql-ide-tracing-n1-title">
						{__('Possible N+1 patterns', 'wpgraphql-ide')}
					</h4>
					<p className="wpgraphql-ide-tracing-n1-description">
						{__(
							'These fields were resolved repeatedly in series — one call per parent item. A DataLoader could batch them into a single call.',
							'wpgraphql-ide'
						)}
					</p>
					<table className="wpgraphql-ide-tracing-table wpgraphql-ide-tracing-n1-table">
						<thead>
							<tr>
								<th>{__('Path pattern', 'wpgraphql-ide')}</th>
								<th className="is-numeric">
									{__('Calls', 'wpgraphql-ide')}
								</th>
								<th className="is-numeric">
									{__('Total', 'wpgraphql-ide')}
								</th>
								<th className="is-numeric">
									{__('Avg / call', 'wpgraphql-ide')}
								</th>
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
										aria-label={sprintf(
											/* translators: %s: GraphQL path pattern to jump to in the editor */
											__(
												'Jump to %s in the editor',
												'wpgraphql-ide'
											),
											p.pattern
										)}
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
							<span>{__('Sort by', 'wpgraphql-ide')}</span>
							<select
								id="wpgraphql-ide-tracing-sort"
								value={sortBy}
								onChange={(e) => setSortBy(e.target.value)}
							>
								{getSortOptions().map((opt) => (
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
									{sprintf(
										/* translators: %d: number of trivial resolver fields hidden */
										__(
											'Hide trivial fields (under 1 µs) · %d hidden',
											'wpgraphql-ide'
										),
										trivialCount
									)}
								</span>
							</label>
						)}
					</div>

					<table className="wpgraphql-ide-tracing-table">
						<thead>
							<tr>
								<th>{__('Path', 'wpgraphql-ide')}</th>
								<th className="wpgraphql-ide-tracing-return-cell">
									{__('Return Type', 'wpgraphql-ide')}
								</th>
								<th className="is-numeric is-bar-col">
									{__('Duration', 'wpgraphql-ide')}
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
										aria-label={sprintf(
											/* translators: %s: resolved field path to jump to in the editor */
											__(
												'Jump to %s in the editor',
												'wpgraphql-ide'
											),
											formatPath(r.path)
										)}
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
