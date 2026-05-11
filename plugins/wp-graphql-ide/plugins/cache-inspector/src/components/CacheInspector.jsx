import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SearchControl,
	Spinner,
	TabPanel,
} from '@wordpress/components';
import { Icon, update, trash } from '@wordpress/icons';

// Resolved per-call rather than captured at module load — wp_localize_script
// is guaranteed to have populated `window.WPGRAPHQL_IDE_CACHE_INSPECTOR`
// before this module executes, but late reads make the helper trivially
// mockable in unit tests.
function getRestConfig() {
	if (typeof window !== 'undefined' && window.WPGRAPHQL_IDE_CACHE_INSPECTOR) {
		return window.WPGRAPHQL_IDE_CACHE_INSPECTOR;
	}
	return { restUrl: '', restNonce: '' };
}

const DEFAULT_SORT = { column: 'size', direction: 'desc' };
const TICK_INTERVAL_MS = 1000;

/**
 * Cache Inspector workspace tab. Cache-wide view of every Smart Cache
 * entry in the WordPress object cache (transient backend); supports
 * sortable columns, type and substring filters, per-entry purge, and
 * bulk purge.
 *
 * @return {JSX.Element} Inspector UI.
 */
export function CacheInspector() {
	const [state, setState] = useState({
		loading: true,
		error: null,
		data: null,
	});
	const [purging, setPurging] = useState(() => new Set());
	const [bulkPurging, setBulkPurging] = useState(false);
	const [purgeAllOpen, setPurgeAllOpen] = useState(false);
	const [purgeSelectedOpen, setPurgeSelectedOpen] = useState(false);
	const [search, setSearch] = useState('');
	const [typeFilter, setTypeFilter] = useState('all');
	const [sort, setSort] = useState(DEFAULT_SORT);
	const [selected, setSelected] = useState(() => new Set());
	const [now, setNow] = useState(() => Math.floor(Date.now() / 1000));

	// Live-tick once per second so "Expires in" countdowns update while the
	// inspector is open. Gated on having data to avoid the timer spinning
	// during loading / error states.
	const hasData = !!state.data && !state.loading;
	useEffect(() => {
		if (!hasData) {
			return undefined;
		}
		const id = setInterval(
			() => setNow(Math.floor(Date.now() / 1000)),
			TICK_INTERVAL_MS
		);
		return () => clearInterval(id);
	}, [hasData]);

	const fetchEntries = useCallback(async () => {
		setState((s) => ({ ...s, loading: true, error: null }));
		try {
			const res = await fetch(`${getRestConfig().restUrl}/entries`, {
				headers: { 'X-WP-Nonce': getRestConfig().restNonce },
				credentials: 'same-origin',
			});
			if (!res.ok) {
				throw new Error(`Request failed (${res.status})`);
			}
			const data = await res.json();
			setState({ loading: false, error: null, data });
			setSelected(new Set());
		} catch (err) {
			setState({ loading: false, error: err.message, data: null });
		}
	}, []);

	useEffect(() => {
		fetchEntries();
	}, [fetchEntries]);

	const purgeOne = useCallback(async (cacheKey) => {
		setPurging((p) => new Set(p).add(cacheKey));
		try {
			const res = await fetch(`${getRestConfig().restUrl}/purge`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': getRestConfig().restNonce,
				},
				credentials: 'same-origin',
				body: JSON.stringify({ cacheKey }),
			});
			if (!res.ok) {
				throw new Error(`Purge failed (${res.status})`);
			}
			setState((s) => {
				if (!s.data) {
					return s;
				}
				const entries = s.data.entries.filter(
					(e) => e.cacheKey !== cacheKey
				);
				return {
					...s,
					data: {
						...s.data,
						entries,
						count: Math.max(0, s.data.count - 1),
					},
				};
			});
			setSelected((sel) => {
				if (!sel.has(cacheKey)) {
					return sel;
				}
				const next = new Set(sel);
				next.delete(cacheKey);
				return next;
			});
		} catch (err) {
			setState((s) => ({ ...s, error: err.message }));
		} finally {
			setPurging((p) => {
				const next = new Set(p);
				next.delete(cacheKey);
				return next;
			});
		}
	}, []);

	const purgeAll = useCallback(async () => {
		setBulkPurging(true);
		try {
			const res = await fetch(`${getRestConfig().restUrl}/purge-all`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': getRestConfig().restNonce },
				credentials: 'same-origin',
			});
			if (!res.ok) {
				throw new Error(`Bulk purge failed (${res.status})`);
			}
			setPurgeAllOpen(false);
			await fetchEntries();
		} catch (err) {
			setState((s) => ({ ...s, error: err.message }));
		} finally {
			setBulkPurging(false);
		}
	}, [fetchEntries]);

	const purgeSelected = useCallback(async () => {
		const cacheKeys = Array.from(selected);
		if (cacheKeys.length === 0) {
			return;
		}
		setBulkPurging(true);
		try {
			const res = await fetch(`${getRestConfig().restUrl}/purge-bulk`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': getRestConfig().restNonce,
				},
				credentials: 'same-origin',
				body: JSON.stringify({ cacheKeys }),
			});
			if (!res.ok) {
				throw new Error(`Bulk purge failed (${res.status})`);
			}
			setPurgeSelectedOpen(false);
			await fetchEntries();
		} catch (err) {
			setState((s) => ({ ...s, error: err.message }));
		} finally {
			setBulkPurging(false);
		}
	}, [selected, fetchEntries]);

	if (state.loading) {
		return (
			<div className="wpgraphql-ide-cache-inspector wpgraphql-ide-cache-inspector--loading">
				<Spinner />
				<span>Reading cache inventory…</span>
			</div>
		);
	}

	if (state.error) {
		return (
			<div className="wpgraphql-ide-cache-inspector">
				<Notice status="error" isDismissible={false}>
					{state.error}
				</Notice>
				<Button variant="secondary" onClick={fetchEntries}>
					Retry
				</Button>
			</div>
		);
	}

	const data = state.data || {};

	if (data.storage === 'object_cache') {
		return (
			<div className="wpgraphql-ide-cache-inspector">
				<Notice status="info" isDismissible={false}>
					This site uses an external object cache (Redis or Memcache).
					Smart Cache entries are stored there and can&apos;t be
					enumerated from PHP. Use your backend tools (e.g.{' '}
					<code>
						redis-cli --scan --pattern &apos;gql_cache_*&apos;
					</code>
					) to inspect or purge.
				</Notice>
			</div>
		);
	}

	const allEntries = data.entries || [];
	const totalCount = data.count || 0;
	const totalSize = data.totalSize || 0;
	const responseCount = allEntries.filter(
		(e) => e.type === 'response'
	).length;
	const trackerCount = allEntries.filter((e) => e.type === 'tracker').length;

	return (
		<div className="wpgraphql-ide-cache-inspector">
			<StatStrip
				totalCount={totalCount}
				totalSize={totalSize}
				selectedCount={selected.size}
				onClearSelection={() => setSelected(new Set())}
				onRefresh={fetchEntries}
				onPurgeAllRequest={() => setPurgeAllOpen(true)}
				onPurgeSelectedRequest={() => setPurgeSelectedOpen(true)}
			/>

			<FilterBar
				search={search}
				onSearch={setSearch}
				typeFilter={typeFilter}
				onTypeFilter={setTypeFilter}
				allCount={allEntries.length}
				responseCount={responseCount}
				trackerCount={trackerCount}
			/>

			{data.truncated && (
				<Notice status="warning" isDismissible={false}>
					Showing the {allEntries.length} largest entries of{' '}
					{totalCount} total. Purge from this list to surface the next
					batch.
				</Notice>
			)}

			{allEntries.length === 0 ? (
				<EmptyState />
			) : (
				<EntriesView
					entries={allEntries}
					purging={purging}
					selected={selected}
					setSelected={setSelected}
					onPurge={purgeOne}
					search={search}
					typeFilter={typeFilter}
					sort={sort}
					onSort={setSort}
					onClearFilters={() => {
						setSearch('');
						setTypeFilter('all');
					}}
					now={now}
				/>
			)}

			{purgeAllOpen && (
				<PurgeConfirmDialog
					title="Purge all Smart Cache entries?"
					message={
						<>
							This will delete <strong>{totalCount}</strong>{' '}
							{totalCount === 1 ? 'entry' : 'entries'}
							{totalSize > 0 && (
								<>
									{' '}
									(<strong>{formatBytes(totalSize)}</strong>)
								</>
							)}{' '}
							from the WordPress object cache. The next request
							that matches each query will re-populate the cache.
							This cannot be undone.
						</>
					}
					confirmLabel="Purge all"
					submitting={bulkPurging}
					onConfirm={purgeAll}
					onClose={() => setPurgeAllOpen(false)}
				/>
			)}

			{purgeSelectedOpen && (
				<PurgeConfirmDialog
					title={`Purge ${selected.size} selected ${
						selected.size === 1 ? 'entry' : 'entries'
					}?`}
					message={
						<>
							This will delete <strong>{selected.size}</strong>{' '}
							{selected.size === 1 ? 'entry' : 'entries'} from the
							WordPress object cache. The next request that
							matches each query will re-populate the cache. This
							cannot be undone.
						</>
					}
					confirmLabel={`Purge ${selected.size}`}
					submitting={bulkPurging}
					onConfirm={purgeSelected}
					onClose={() => setPurgeSelectedOpen(false)}
				/>
			)}
		</div>
	);
}

function StatStrip({
	totalCount,
	totalSize,
	selectedCount,
	onClearSelection,
	onRefresh,
	onPurgeAllRequest,
	onPurgeSelectedRequest,
}) {
	if (selectedCount > 0) {
		return (
			<div className="wpgraphql-ide-cache-inspector-stat-strip is-selection">
				<div className="wpgraphql-ide-cache-inspector-stat-primary">
					<strong>{selectedCount}</strong>{' '}
					{selectedCount === 1 ? 'entry' : 'entries'} selected
					<Button
						variant="tertiary"
						size="small"
						onClick={onClearSelection}
					>
						Clear
					</Button>
				</div>
				<div className="wpgraphql-ide-cache-inspector-stat-actions">
					<Button
						variant="secondary"
						isDestructive
						onClick={onPurgeSelectedRequest}
						icon={() => <Icon icon={trash} size={16} />}
					>
						Purge selected ({selectedCount})
					</Button>
				</div>
			</div>
		);
	}

	return (
		<div className="wpgraphql-ide-cache-inspector-stat-strip">
			<div className="wpgraphql-ide-cache-inspector-stat-primary">
				<div className="wpgraphql-ide-cache-inspector-stat-value">
					{formatBytes(totalSize)}
				</div>
				<div className="wpgraphql-ide-cache-inspector-stat-label">
					total cache size · {totalCount}{' '}
					{totalCount === 1 ? 'entry' : 'entries'}
				</div>
			</div>
			<div className="wpgraphql-ide-cache-inspector-stat-actions">
				<Button
					variant="tertiary"
					onClick={onRefresh}
					icon={() => <Icon icon={update} size={16} />}
				>
					Refresh
				</Button>
				<Button
					variant="secondary"
					isDestructive
					disabled={totalCount === 0}
					onClick={onPurgeAllRequest}
					icon={() => <Icon icon={trash} size={16} />}
				>
					Purge all
				</Button>
			</div>
		</div>
	);
}

function FilterBar({
	search,
	onSearch,
	typeFilter,
	onTypeFilter,
	allCount,
	responseCount,
	trackerCount,
}) {
	return (
		<div className="wpgraphql-ide-cache-inspector-filters">
			<div className="wpgraphql-ide-cache-inspector-filter-search">
				<SearchControl
					value={search}
					onChange={onSearch}
					placeholder="Search cache keys…"
					__nextHasNoMarginBottom
					size="compact"
				/>
			</div>
			{/* Mirrors the Documents-panel filter tabs (`SavedQueriesPanel`).
			    Underlined active indicator + counts in the title — the WP
			    Core / Gutenberg native pattern for "filter by category with
			    counts". The empty render-prop child is intentional: tabs
			    here drive a sibling list, not their own content panel. */}
			<TabPanel
				className="wpgraphql-ide-cache-inspector-filter"
				tabs={[
					{ name: 'all', title: `All (${allCount})` },
					{
						name: 'response',
						title: `Responses (${responseCount})`,
					},
					{ name: 'tracker', title: `Trackers (${trackerCount})` },
				]}
				initialTabName={typeFilter}
				onSelect={onTypeFilter}
			>
				{() => null}
			</TabPanel>
		</div>
	);
}

function PurgeConfirmDialog({
	title,
	message,
	confirmLabel,
	submitting,
	onConfirm,
	onClose,
}) {
	return (
		<Modal
			title={title}
			onRequestClose={() => (submitting ? null : onClose())}
			className="wpgraphql-ide-dialog wpgraphql-ide-cache-inspector-purge-dialog"
		>
			<p className="wpgraphql-ide-dialog-message">{message}</p>
			<div className="wpgraphql-ide-dialog-actions">
				<Button
					variant="tertiary"
					onClick={onClose}
					disabled={submitting}
				>
					Cancel
				</Button>
				<Button
					variant="primary"
					isDestructive
					onClick={onConfirm}
					isBusy={submitting}
					disabled={submitting}
				>
					{submitting ? 'Purging…' : confirmLabel}
				</Button>
			</div>
		</Modal>
	);
}

function EmptyState() {
	return (
		<div className="wpgraphql-ide-cache-inspector-empty">
			<p>No cached responses yet.</p>
			<p className="wpgraphql-ide-cache-inspector-empty-hint">
				Run an anonymous, non-mutation query in the editor to populate
				the cache.
			</p>
		</div>
	);
}

function FilterEmptyState({ onClearFilters }) {
	return (
		<div className="wpgraphql-ide-cache-inspector-empty">
			<p>No entries match the current filters.</p>
			<p className="wpgraphql-ide-cache-inspector-empty-hint">
				<Button variant="link" onClick={onClearFilters}>
					Clear filters
				</Button>
			</p>
		</div>
	);
}

function EntriesView({
	entries,
	purging,
	selected,
	setSelected,
	onPurge,
	search,
	typeFilter,
	sort,
	onSort,
	onClearFilters,
	now,
}) {
	const filtered = useMemo(() => {
		const q = search.trim().toLowerCase();
		return entries.filter((e) => {
			if (typeFilter !== 'all' && e.type !== typeFilter) {
				return false;
			}
			if (q && !e.cacheKey.toLowerCase().includes(q)) {
				return false;
			}
			return true;
		});
	}, [entries, search, typeFilter]);

	const sorted = useMemo(() => sortEntries(filtered, sort), [filtered, sort]);

	// Max size is computed from the *unfiltered* set so the bar lengths are
	// stable while the user is searching/filtering — comparing rows to the
	// global maximum is what makes "this is the bloated one" obvious.
	const maxSize = useMemo(
		() =>
			entries.reduce(
				(max, e) => (e.sizeBytes > max ? e.sizeBytes : max),
				0
			),
		[entries]
	);

	const visibleKeys = useMemo(() => sorted.map((e) => e.cacheKey), [sorted]);
	const allSelected =
		visibleKeys.length > 0 && visibleKeys.every((k) => selected.has(k));
	const someSelected =
		!allSelected && visibleKeys.some((k) => selected.has(k));

	const toggleSelectAll = useCallback(() => {
		setSelected((sel) => {
			const next = new Set(sel);
			if (allSelected) {
				visibleKeys.forEach((k) => next.delete(k));
			} else {
				visibleKeys.forEach((k) => next.add(k));
			}
			return next;
		});
	}, [allSelected, visibleKeys, setSelected]);

	const toggleSelect = useCallback(
		(cacheKey) => {
			setSelected((sel) => {
				const next = new Set(sel);
				if (next.has(cacheKey)) {
					next.delete(cacheKey);
				} else {
					next.add(cacheKey);
				}
				return next;
			});
		},
		[setSelected]
	);

	if (sorted.length === 0) {
		return <FilterEmptyState onClearFilters={onClearFilters} />;
	}

	return (
		<table className="wpgraphql-ide-cache-inspector-table">
			<thead>
				<tr>
					<th
						scope="col"
						className="wpgraphql-ide-cache-inspector-col-checkbox"
					>
						<CheckboxControl
							__nextHasNoMarginBottom
							checked={allSelected}
							indeterminate={someSelected}
							onChange={toggleSelectAll}
							aria-label={
								allSelected
									? 'Deselect all visible entries'
									: 'Select all visible entries'
							}
						/>
					</th>
					<SortableHeader
						column="cacheKey"
						label="Cache key"
						sort={sort}
						onSort={onSort}
					/>
					<SortableHeader
						column="size"
						label="Size"
						className="is-numeric"
						sort={sort}
						onSort={onSort}
					/>
					<SortableHeader
						column="expiresIn"
						label="Expires in"
						className="is-numeric"
						sort={sort}
						onSort={onSort}
					/>
					<th
						scope="col"
						className="is-actions"
						aria-label="Actions"
					/>
				</tr>
			</thead>
			<tbody>
				{sorted.map((entry) => (
					<EntryRow
						key={entry.cacheKey}
						entry={entry}
						isPurging={purging.has(entry.cacheKey)}
						isSelected={selected.has(entry.cacheKey)}
						onToggleSelect={toggleSelect}
						onPurge={onPurge}
						maxSize={maxSize}
						now={now}
					/>
				))}
			</tbody>
		</table>
	);
}

function SortableHeader({ column, label, className, sort, onSort }) {
	const isSorted = sort.column === column;
	const direction = isSorted ? sort.direction : null;
	const ariaSort = sortStateToAria(isSorted, direction);

	const handleClick = () => {
		if (isSorted) {
			onSort({
				column,
				direction: direction === 'asc' ? 'desc' : 'asc',
			});
			return;
		}
		// Numeric columns are most useful starting desc (biggest / soonest
		// first); strings start asc.
		onSort({
			column,
			direction: column === 'cacheKey' ? 'asc' : 'desc',
		});
	};

	const indicator = sortIndicator(isSorted, direction);

	return (
		<th scope="col" className={className} aria-sort={ariaSort}>
			<button
				type="button"
				onClick={handleClick}
				className="wpgraphql-ide-cache-inspector-sort"
			>
				<span>{label}</span>
				<span className="wpgraphql-ide-cache-inspector-sort-indicator">
					{indicator}
				</span>
			</button>
		</th>
	);
}

function sortStateToAria(isSorted, direction) {
	if (!isSorted) {
		return 'none';
	}
	return direction === 'asc' ? 'ascending' : 'descending';
}

function sortIndicator(isSorted, direction) {
	if (!isSorted) {
		return '';
	}
	return direction === 'asc' ? '↑' : '↓';
}

function EntryRow({
	entry,
	isPurging,
	isSelected,
	onToggleSelect,
	onPurge,
	maxSize,
	now,
}) {
	const liveExpiresIn =
		typeof entry.expiresAt === 'number'
			? Math.max(0, entry.expiresAt - now)
			: entry.expiresIn;
	const sizePct = maxSize > 0 ? (entry.sizeBytes / maxSize) * 100 : 0;

	return (
		<tr className={isSelected ? 'is-selected' : undefined}>
			<td className="wpgraphql-ide-cache-inspector-col-checkbox">
				<CheckboxControl
					__nextHasNoMarginBottom
					checked={isSelected}
					onChange={() => onToggleSelect(entry.cacheKey)}
					aria-label={`Select ${entry.cacheKey}`}
				/>
			</td>
			<td>
				<div className="wpgraphql-ide-cache-inspector-key-cell">
					<TypeBadge type={entry.type} />
					<code
						className="wpgraphql-ide-cache-inspector-key"
						title={entry.cacheKey}
					>
						{truncateMiddle(entry.cacheKey, 20)}
					</code>
				</div>
			</td>
			<td className="is-numeric">
				<div className="wpgraphql-ide-cache-inspector-size-cell">
					<span>{formatBytes(entry.sizeBytes)}</span>
					<div
						className="wpgraphql-ide-cache-inspector-size-bar"
						role="progressbar"
						aria-valuenow={Math.round(sizePct)}
						aria-valuemin={0}
						aria-valuemax={100}
						aria-label={`Size: ${sizePct.toFixed(1)}% of largest entry`}
					>
						<span
							className="wpgraphql-ide-cache-inspector-size-bar-fill"
							style={{ width: `${sizePct}%` }}
						/>
					</div>
				</div>
			</td>
			<td className="is-numeric">
				<ExpiresInCell value={liveExpiresIn} />
			</td>
			<td className="is-actions">
				<Button
					variant="tertiary"
					isDestructive
					size="small"
					isBusy={isPurging}
					disabled={isPurging}
					onClick={() => onPurge(entry.cacheKey)}
				>
					{isPurging ? 'Purging…' : 'Purge'}
				</Button>
			</td>
		</tr>
	);
}

function ExpiresInCell({ value }) {
	if (value === null) {
		return '—';
	}
	if (value === 0) {
		return (
			<span className="wpgraphql-ide-cache-inspector-expired">
				Expired
			</span>
		);
	}
	return formatDuration(value);
}

function TypeBadge({ type }) {
	const label = type === 'response' ? 'Response' : 'Tracker';
	return (
		<span
			className={`wpgraphql-ide-cache-inspector-type wpgraphql-ide-cache-inspector-type--${type}`}
		>
			{label}
		</span>
	);
}

function sortEntries(entries, { column, direction }) {
	const dir = direction === 'asc' ? 1 : -1;
	const compare = (a, b) => {
		if (column === 'cacheKey') {
			return a.cacheKey.localeCompare(b.cacheKey) * dir;
		}
		if (column === 'expiresIn') {
			// Null expirations sort to the end regardless of direction —
			// they represent "no countdown known" and shouldn't intrude on
			// either the soonest- or the latest-expiring view.
			const aNull = a.expiresIn === null;
			const bNull = b.expiresIn === null;
			if (aNull && bNull) {
				return 0;
			}
			if (aNull) {
				return 1;
			}
			if (bNull) {
				return -1;
			}
			return (a.expiresIn - b.expiresIn) * dir;
		}
		// Default: numeric `size`.
		return (a.sizeBytes - b.sizeBytes) * dir;
	};
	return entries.slice().sort(compare);
}

function truncateMiddle(str, maxLen) {
	if (!str || str.length <= maxLen) {
		return str;
	}
	const head = Math.ceil((maxLen - 1) / 2);
	const tail = Math.floor((maxLen - 1) / 2);
	return `${str.slice(0, head)}…${str.slice(-tail)}`;
}

function formatBytes(bytes) {
	if (!bytes && bytes !== 0) {
		return '—';
	}
	if (bytes < 1024) {
		return `${bytes} B`;
	}
	const kb = bytes / 1024;
	if (kb < 1024) {
		return `${kb.toFixed(1)} KB`;
	}
	const mb = kb / 1024;
	return `${mb.toFixed(2)} MB`;
}

function formatDuration(seconds) {
	const s = Math.max(0, Math.floor(seconds));
	if (s < 60) {
		return `${s}s`;
	}
	const m = Math.floor(s / 60);
	const r = s % 60;
	if (m < 60) {
		return r ? `${m}m ${r}s` : `${m}m`;
	}
	const h = Math.floor(m / 60);
	const rm = m % 60;
	return rm ? `${h}h ${rm}m` : `${h}h`;
}
