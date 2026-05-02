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
			<table className="wpgraphql-ide-headers-table">
				<tbody>
					{sorted.map(([name, value]) => (
						<tr key={name} className="wpgraphql-ide-header-row">
							<td className="wpgraphql-ide-header-name">
								{name}
							</td>
							<td className="wpgraphql-ide-header-value">
								{value}
							</td>
						</tr>
					))}
				</tbody>
			</table>
		</div>
	);
};
