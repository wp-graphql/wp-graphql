import React from 'react';

const formatValue = (value) => {
	if (value === null || value === undefined || value === '') {
		return <span className="wpgraphql-ide-qa-empty">(none)</span>;
	}
	if (Array.isArray(value)) {
		if (value.length === 0) {
			return <span className="wpgraphql-ide-qa-empty">(empty)</span>;
		}
		return (
			<ul className="wpgraphql-ide-qa-list">
				{value.map((v, i) => (
					<li key={i}>
						<code>{String(v)}</code>
					</li>
				))}
			</ul>
		);
	}
	if (typeof value === 'object') {
		return (
			<pre className="wpgraphql-ide-qa-json">
				{JSON.stringify(value, null, 2)}
			</pre>
		);
	}
	return <code className="wpgraphql-ide-qa-scalar">{String(value)}</code>;
};

const humanize = (key) =>
	String(key)
		.replace(/([A-Z])/g, ' $1')
		.replace(/^./, (s) => s.toUpperCase())
		.trim();

export const QueryAnalyzerExtensionTab = ({ data }) => {
	if (!data || typeof data !== 'object') {
		return (
			<p className="wpgraphql-ide-extensions-empty">
				No query analyzer data in the last response.
			</p>
		);
	}

	return (
		<dl className="wpgraphql-ide-qa-grid">
			{Object.entries(data).map(([key, value]) => (
				<React.Fragment key={key}>
					<dt className="wpgraphql-ide-qa-key">{humanize(key)}</dt>
					<dd className="wpgraphql-ide-qa-value">
						{formatValue(value)}
					</dd>
				</React.Fragment>
			))}
		</dl>
	);
};
