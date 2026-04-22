import React, { useState } from 'react';

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

	if (!data || typeof data !== 'object') {
		return (
			<p className="wpgraphql-ide-extensions-empty">
				No tracing data in the last response. Enable GraphQL Tracing in
				WPGraphQL settings to see field-level timing here.
			</p>
		);
	}

	const resolvers = Array.isArray(data.execution?.resolvers)
		? data.execution.resolvers
		: [];

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
