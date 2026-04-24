import React from 'react';

export const HeadersPanel = ({ headers }) => {
	const entries =
		headers && typeof headers === 'object' ? Object.entries(headers) : [];

	if (entries.length === 0) {
		return (
			<div className="wpgraphql-ide-headers-panel">
				<p className="wpgraphql-ide-extensions-empty">
					No headers yet.
				</p>
			</div>
		);
	}

	const sorted = [...entries].sort(([a], [b]) => a.localeCompare(b));

	return (
		<div className="wpgraphql-ide-headers-panel">
			<dl className="wpgraphql-ide-headers-list">
				{sorted.map(([name, value]) => (
					<React.Fragment key={name}>
						<dt className="wpgraphql-ide-header-name">{name}</dt>
						<dd className="wpgraphql-ide-header-value">
							<code>{value}</code>
						</dd>
					</React.Fragment>
				))}
			</dl>
		</div>
	);
};
