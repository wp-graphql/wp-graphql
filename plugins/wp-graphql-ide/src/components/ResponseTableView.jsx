import React, { useEffect, useMemo, useState } from 'react';

const isPlainObject = (v) =>
	v !== null && typeof v === 'object' && !Array.isArray(v);

const isScalar = (v) => v === null || v === undefined || typeof v !== 'object';

const getAtPath = (root, path) => {
	let val = root;
	for (const seg of path) {
		if (val === null || val === undefined) {
			return val;
		}
		val = val[seg];
	}
	return val;
};

const formatSegment = (seg) =>
	typeof seg === 'number' ? `[${seg}]` : String(seg);

const isEdgesArray = (arr) =>
	arr.length > 0 &&
	arr.every(
		(item) =>
			isPlainObject(item) &&
			Object.keys(item).length === 1 &&
			'node' in item &&
			isPlainObject(item.node)
	);

const compareValues = (a, b) => {
	if (a === b) {
		return 0;
	}
	if (a === null || a === undefined) {
		return 1;
	}
	if (b === null || b === undefined) {
		return -1;
	}
	if (typeof a === 'number' && typeof b === 'number') {
		return a - b;
	}
	return String(a).localeCompare(String(b));
};

const ScalarInline = ({ value }) => {
	if (value === null) {
		return <code className="wpgraphql-ide-table-cell-null">null</code>;
	}
	if (value === undefined) {
		return <code className="wpgraphql-ide-table-cell-null">—</code>;
	}
	if (typeof value === 'boolean') {
		return (
			<code className="wpgraphql-ide-table-cell-bool">
				{String(value)}
			</code>
		);
	}
	if (typeof value === 'number') {
		return (
			<code className="wpgraphql-ide-table-cell-num">
				{String(value)}
			</code>
		);
	}
	return (
		<span className="wpgraphql-ide-table-cell-str">{String(value)}</span>
	);
};

// Cell inside a data table — space-constrained, so nested values become drill chips.
const TableCell = ({ value, onDrill }) => {
	if (isScalar(value)) {
		return <ScalarInline value={value} />;
	}
	if (Array.isArray(value)) {
		return (
			<button
				type="button"
				onClick={onDrill}
				className="wpgraphql-ide-table-cell-drill"
				title={`Array[${value.length}] — click to narrow to this path`}
			>
				[{value.length}]
			</button>
		);
	}
	return (
		<button
			type="button"
			onClick={onDrill}
			className="wpgraphql-ide-table-cell-drill"
			title="Object — click to narrow to this path"
		>
			{'{'}
			{Object.keys(value).length}
			{'}'}
		</button>
	);
};

const Breadcrumb = ({ path, onNavigate }) => (
	<nav className="wpgraphql-ide-table-breadcrumb" aria-label="Response path">
		<button
			type="button"
			className={`wpgraphql-ide-table-crumb${path.length === 0 ? ' is-current' : ''}`}
			onClick={() => onNavigate([])}
		>
			Root
		</button>
		{path.map((seg, i) => {
			const isLast = i === path.length - 1;
			return (
				<React.Fragment key={i}>
					<span className="wpgraphql-ide-table-crumb-sep">›</span>
					<button
						type="button"
						className={`wpgraphql-ide-table-crumb${isLast ? ' is-current' : ''}`}
						onClick={() => onNavigate(path.slice(0, i + 1))}
					>
						{formatSegment(seg)}
					</button>
				</React.Fragment>
			);
		})}
	</nav>
);

// Table for an array of objects. One instance holds its own sort state.
const DataTable = ({ rows, rowPathSuffix, path, onNavigate }) => {
	const columns = useMemo(() => {
		const seen = new Set();
		const ordered = [];
		rows.forEach((row) => {
			Object.keys(row).forEach((k) => {
				if (!seen.has(k)) {
					seen.add(k);
					ordered.push(k);
				}
			});
		});
		return ordered;
	}, [rows]);

	const [sort, setSort] = useState({ column: null, direction: 'asc' });

	const orderedIndices = useMemo(() => {
		const indices = rows.map((_, i) => i);
		if (!sort.column) {
			return indices;
		}
		indices.sort((a, b) =>
			compareValues(rows[a][sort.column], rows[b][sort.column])
		);
		if (sort.direction === 'desc') {
			indices.reverse();
		}
		return indices;
	}, [rows, sort]);

	const toggleSort = (column) => {
		setSort((prev) => {
			if (prev.column !== column) {
				return { column, direction: 'asc' };
			}
			if (prev.direction === 'asc') {
				return { column, direction: 'desc' };
			}
			return { column: null, direction: 'asc' };
		});
	};

	return (
		<table className="wpgraphql-ide-data-table">
			<thead>
				<tr>
					<th className="wpgraphql-ide-table-index-col">#</th>
					{columns.map((col) => {
						const isSorted = sort.column === col;
						let indicator = '';
						if (isSorted) {
							indicator = sort.direction === 'asc' ? '↑' : '↓';
						}
						return (
							<th key={col}>
								<button
									type="button"
									onClick={() => toggleSort(col)}
									className="wpgraphql-ide-data-table-sort"
								>
									<span>{col}</span>
									<span className="wpgraphql-ide-data-table-sort-indicator">
										{indicator}
									</span>
								</button>
							</th>
						);
					})}
				</tr>
			</thead>
			<tbody>
				{orderedIndices.map((origIndex, displayIdx) => {
					const row = rows[origIndex];
					return (
						<tr key={origIndex}>
							<td className="wpgraphql-ide-table-index">
								{displayIdx}
							</td>
							{columns.map((col) => (
								<td key={col}>
									<TableCell
										value={row[col]}
										onDrill={() =>
											onNavigate([
												...path,
												origIndex,
												...rowPathSuffix,
												col,
											])
										}
									/>
								</td>
							))}
						</tr>
					);
				})}
			</tbody>
		</table>
	);
};

const ScalarArrayTable = ({ array }) => (
	<table className="wpgraphql-ide-data-table">
		<thead>
			<tr>
				<th className="wpgraphql-ide-table-index-col">#</th>
				<th>value</th>
			</tr>
		</thead>
		<tbody>
			{array.map((item, i) => (
				<tr key={i}>
					<td className="wpgraphql-ide-table-index">{i}</td>
					<td>
						<ScalarInline value={item} />
					</td>
				</tr>
			))}
		</tbody>
	</table>
);

// Recursive renderer. Objects expand into nested sections + scalar rows,
// arrays of objects become inline data tables, scalars render as plain values.
const TreeView = ({ value, path, onNavigate }) => {
	if (isScalar(value)) {
		return (
			<div className="wpgraphql-ide-tree-scalar">
				<ScalarInline value={value} />
			</div>
		);
	}

	if (Array.isArray(value)) {
		if (value.length === 0) {
			return (
				<p className="wpgraphql-ide-extensions-empty">(empty array)</p>
			);
		}
		// Auto-unwrap Relay connection edges so columns reflect node fields.
		if (isEdgesArray(value)) {
			return (
				<DataTable
					rows={value.map((e) => e.node)}
					rowPathSuffix={['node']}
					path={path}
					onNavigate={onNavigate}
				/>
			);
		}
		if (value.every(isPlainObject)) {
			return (
				<DataTable
					rows={value}
					rowPathSuffix={[]}
					path={path}
					onNavigate={onNavigate}
				/>
			);
		}
		return <ScalarArrayTable array={value} />;
	}

	const entries = Object.entries(value);
	if (entries.length === 0) {
		return <p className="wpgraphql-ide-extensions-empty">(empty object)</p>;
	}

	return (
		<div className="wpgraphql-ide-tree-object">
			{entries.map(([key, val]) => {
				if (isScalar(val)) {
					return (
						<div className="wpgraphql-ide-tree-row" key={key}>
							<div className="wpgraphql-ide-tree-field">
								{key}
							</div>
							<div className="wpgraphql-ide-tree-value">
								<ScalarInline value={val} />
							</div>
						</div>
					);
				}
				return (
					<div className="wpgraphql-ide-tree-section" key={key}>
						<button
							type="button"
							className="wpgraphql-ide-tree-section-header"
							onClick={() => onNavigate([...path, key])}
							title="Click to narrow to this path"
						>
							{key}
						</button>
						<div className="wpgraphql-ide-tree-section-content">
							<TreeView
								value={val}
								path={[...path, key]}
								onNavigate={onNavigate}
							/>
						</div>
					</div>
				);
			})}
		</div>
	);
};

export const ResponseTableView = ({ response }) => {
	const [path, setPath] = useState([]);

	// Reset navigation when the underlying response identity changes.
	useEffect(() => {
		setPath([]);
	}, [response]);

	const currentValue = useMemo(
		() => (response ? getAtPath(response, path) : null),
		[response, path]
	);

	if (!response) {
		return (
			<div className="wpgraphql-ide-table-view">
				<p className="wpgraphql-ide-extensions-empty">
					No response to tabulate. Run a query to see results.
				</p>
			</div>
		);
	}

	return (
		<div className="wpgraphql-ide-table-view">
			<div className="wpgraphql-ide-table-toolbar">
				<Breadcrumb path={path} onNavigate={setPath} />
			</div>
			<div className="wpgraphql-ide-table-scroll">
				<TreeView
					value={currentValue}
					path={path}
					onNavigate={setPath}
				/>
			</div>
		</div>
	);
};
