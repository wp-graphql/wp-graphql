import React, { useState } from 'react';

const ErrorCard = ({ error }) => {
	const [showDetails, setShowDetails] = useState(false);
	const locations = Array.isArray(error.locations) ? error.locations : [];
	const path = Array.isArray(error.path) ? error.path : [];
	const details =
		error.extensions && typeof error.extensions === 'object'
			? error.extensions
			: null;

	return (
		<li className="wpgraphql-ide-error-card">
			<div className="wpgraphql-ide-error-message">
				{error.message || '(no message)'}
			</div>
			{(locations.length > 0 || path.length > 0) && (
				<div className="wpgraphql-ide-error-meta">
					{locations.map((loc, i) => (
						<span
							key={`loc-${i}`}
							className="wpgraphql-ide-error-location"
						>
							line {loc.line}:{loc.column}
						</span>
					))}
					{path.length > 0 && (
						<span className="wpgraphql-ide-error-path">
							{path.join(' › ')}
						</span>
					)}
				</div>
			)}
			{details && (
				<>
					<button
						type="button"
						className="wpgraphql-ide-error-details-toggle"
						onClick={() => setShowDetails((s) => !s)}
					>
						<span
							className={`wpgraphql-ide-error-details-chevron${showDetails ? ' is-open' : ''}`}
						>
							›
						</span>
						{showDetails ? 'Hide' : 'Show'} details
					</button>
					{showDetails && (
						<pre className="wpgraphql-ide-error-details">
							{JSON.stringify(details, null, 2)}
						</pre>
					)}
				</>
			)}
		</li>
	);
};

export const ErrorsPanel = ({ errors }) => {
	if (!Array.isArray(errors) || errors.length === 0) {
		return (
			<div className="wpgraphql-ide-errors-panel">
				<p className="wpgraphql-ide-extensions-empty">
					No errors in the last response.
				</p>
			</div>
		);
	}
	return (
		<div className="wpgraphql-ide-errors-panel">
			<ul className="wpgraphql-ide-errors-list">
				{errors.map((err, i) => (
					<ErrorCard
						key={`${err.message || 'err'}-${i}`}
						error={err}
					/>
				))}
			</ul>
		</div>
	);
};
