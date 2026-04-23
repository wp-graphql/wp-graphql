import React, { useState } from 'react';

const formatMs = (seconds) => {
	if (typeof seconds !== 'number' || !isFinite(seconds)) {
		return '—';
	}
	return `${(seconds * 1000).toFixed(2)} ms`;
};

const QueryEntry = ({ entry }) => {
	const [showStack, setShowStack] = useState(false);
	const stackFrames = entry.stack
		? String(entry.stack)
				.split(',')
				.map((f) => f.trim())
				.filter(Boolean)
		: [];

	return (
		<li className="wpgraphql-ide-querylog-entry">
			<div className="wpgraphql-ide-querylog-entry-header">
				<span className="wpgraphql-ide-querylog-time">
					{formatMs(entry.time)}
				</span>
				<pre className="wpgraphql-ide-querylog-sql">{entry.sql}</pre>
			</div>
			{stackFrames.length > 0 && (
				<>
					<button
						type="button"
						className="wpgraphql-ide-querylog-stack-toggle"
						onClick={() => setShowStack((s) => !s)}
					>
						<span
							className={`wpgraphql-ide-querylog-stack-chevron${showStack ? ' is-open' : ''}`}
						>
							›
						</span>
						{showStack ? 'Hide' : 'Show'} stack (
						{stackFrames.length})
					</button>
					{showStack && (
						<ol className="wpgraphql-ide-querylog-stack">
							{stackFrames.map((frame, i) => (
								<li key={i}>
									<code>{frame}</code>
								</li>
							))}
						</ol>
					)}
				</>
			)}
		</li>
	);
};

export const QueryLogExtensionTab = ({ data }) => {
	if (!data || typeof data !== 'object') {
		return (
			<p className="wpgraphql-ide-extensions-empty">
				No query log data in the last response. Enable the Query Monitor
				integration and Query Logs in WPGraphQL settings.
			</p>
		);
	}

	const queries = Array.isArray(data.queries) ? data.queries : [];

	return (
		<div className="wpgraphql-ide-querylog-panel">
			<div className="wpgraphql-ide-querylog-summary">
				<div>
					<span className="wpgraphql-ide-tracing-label">Queries</span>
					<span className="wpgraphql-ide-tracing-value">
						{data.queryCount ?? queries.length}
					</span>
				</div>
				<div>
					<span className="wpgraphql-ide-tracing-label">
						Total time
					</span>
					<span className="wpgraphql-ide-tracing-value">
						{formatMs(data.totalTime)}
					</span>
				</div>
			</div>

			{queries.length === 0 ? (
				<p className="wpgraphql-ide-extensions-empty">
					No SQL queries were recorded for this request.
				</p>
			) : (
				<ul className="wpgraphql-ide-querylog-list">
					{queries.map((entry, i) => (
						<QueryEntry
							key={`${entry.sql?.slice(0, 40) || 'q'}-${i}`}
							entry={entry}
						/>
					))}
				</ul>
			)}
		</div>
	);
};
