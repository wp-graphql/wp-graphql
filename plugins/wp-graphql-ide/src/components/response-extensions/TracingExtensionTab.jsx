import React, { useMemo, useState } from 'react';
import { detectNPlusOne } from './detect-n-plus-one';

// WPGraphQL's tracing emits durations as microseconds by convention; format
// as ms when large enough to be meaningful, otherwise keep as μs.
const formatDuration = (value) => {
	if (typeof value !== 'number' || !isFinite(value)) {
		return '—';
	}
	if (value >= 1000) {
		return `${(value / 1000).toFixed(2)} ms`;
	}
	return `${value} μs`;
};

const formatPath = (path) =>
	Array.isArray(path) ? path.join('.') : String(path ?? '');

const SORT_OPTIONS = [
	{ value: 'duration', label: 'Duration (slowest first)' },
	{ value: 'offset', label: 'Start offset (execution order)' },
	{ value: 'path', label: 'Path (alphabetical)' },
];

export const TracingExtensionTab = ({ data }) => {
	const [sortBy, setSortBy] = useState('duration');

	const hasData = data && typeof data === 'object';
	// Hooks must run unconditionally — derive a stable resolver list
	// regardless of `data` validity, then short-circuit the render
	// after the hooks have all been called.
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

	if (!hasData) {
		return (
			<p className="wpgraphql-ide-extensions-empty">
				No tracing data in the last response. Enable GraphQL Tracing in
				WPGraphQL settings to see field-level timing here.
			</p>
		);
	}

	const sortedResolvers = [...resolvers].sort((a, b) => {
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
			<div className="wpgraphql-ide-tracing-summary">
				<div>
					<span className="wpgraphql-ide-tracing-label">
						Total duration
					</span>
					<span className="wpgraphql-ide-tracing-value">
						{formatDuration(data.duration)}
					</span>
				</div>
				<div>
					<span className="wpgraphql-ide-tracing-label">
						Resolvers
					</span>
					<span className="wpgraphql-ide-tracing-value">
						{resolvers.length}
					</span>
				</div>
			</div>

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
							{nPlusOnePatterns.map((p) => (
								<tr key={p.pattern}>
									<td>
										<code className="wpgraphql-ide-tracing-path">
											{p.pattern}
										</code>
									</td>
									<td className="is-numeric">{p.count}</td>
									<td className="is-numeric">
										{formatDuration(p.totalDuration)}
									</td>
									<td className="is-numeric">
										{formatDuration(p.avgDuration)}
									</td>
								</tr>
							))}
						</tbody>
					</table>
				</section>
			)}

			{resolvers.length > 0 && (
				<>
					<div className="wpgraphql-ide-tracing-controls">
						<label htmlFor="wpgraphql-ide-tracing-sort">
							<span>Sort by</span>
						</label>
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
					</div>

					<table className="wpgraphql-ide-tracing-table">
						<thead>
							<tr>
								<th>Path</th>
								<th>Return Type</th>
								<th className="is-numeric">Duration</th>
							</tr>
						</thead>
						<tbody>
							{sortedResolvers.map((r, i) => (
								<tr key={`${formatPath(r.path)}-${i}`}>
									<td>
										<code className="wpgraphql-ide-tracing-path">
											{formatPath(r.path)}
										</code>
										<span className="wpgraphql-ide-tracing-parent">
											{r.parentType}
										</span>
									</td>
									<td>
										<code>{r.returnType}</code>
									</td>
									<td className="is-numeric">
										{formatDuration(r.duration)}
									</td>
								</tr>
							))}
						</tbody>
					</table>
				</>
			)}
		</div>
	);
};
