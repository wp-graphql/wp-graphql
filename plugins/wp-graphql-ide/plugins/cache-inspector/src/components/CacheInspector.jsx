import React, { useCallback, useEffect, useState } from 'react';
import { Button, Modal, Notice, Spinner } from '@wordpress/components';
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

/**
 * Cache Inspector workspace tab. Cache-wide view of every Smart Cache
 * entry in the WordPress object cache (transient backend); supports
 * per-entry and bulk purge.
 *
 * @return {JSX.Element} Inspector UI.
 */
export function CacheInspector() {
	const [state, setState] = useState({
		loading: true,
		error: null,
		data: null,
	});
	const [purging, setPurging] = useState(new Set());
	const [bulkPurging, setBulkPurging] = useState(false);
	const [purgeAllOpen, setPurgeAllOpen] = useState(false);

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

	return (
		<div className="wpgraphql-ide-cache-inspector">
			<Header
				onRefresh={fetchEntries}
				onPurgeAllRequest={() => setPurgeAllOpen(true)}
				count={data.count || 0}
				totalSize={data.totalSize || 0}
			/>

			{data.truncated && (
				<Notice status="warning" isDismissible={false}>
					Showing the {data.entries.length} largest entries of{' '}
					{data.count} total. Purge from this list to surface the next
					batch.
				</Notice>
			)}

			{data.entries.length === 0 ? (
				<EmptyState />
			) : (
				<EntriesTable
					entries={data.entries}
					purging={purging}
					onPurge={purgeOne}
				/>
			)}

			{purgeAllOpen && (
				<PurgeAllDialog
					count={data.count || 0}
					totalSize={data.totalSize || 0}
					submitting={bulkPurging}
					onConfirm={purgeAll}
					onClose={() => setPurgeAllOpen(false)}
				/>
			)}
		</div>
	);
}

function Header({ onRefresh, onPurgeAllRequest, count = 0, totalSize = 0 }) {
	return (
		<div className="wpgraphql-ide-cache-inspector-header">
			<div className="wpgraphql-ide-cache-inspector-header-summary">
				<strong>{count}</strong> {count === 1 ? 'entry' : 'entries'}
				{totalSize > 0 && (
					<>
						{' · '}
						<strong>{formatBytes(totalSize)}</strong> total
					</>
				)}
			</div>
			<div className="wpgraphql-ide-cache-inspector-header-actions">
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
					disabled={count === 0}
					onClick={onPurgeAllRequest}
					icon={() => <Icon icon={trash} size={16} />}
				>
					Purge all
				</Button>
			</div>
		</div>
	);
}

function PurgeAllDialog({ count, totalSize, submitting, onConfirm, onClose }) {
	return (
		<Modal
			title="Purge all Smart Cache entries?"
			onRequestClose={() => (submitting ? null : onClose())}
			className="wpgraphql-ide-dialog wpgraphql-ide-cache-inspector-purge-all-dialog"
		>
			<p className="wpgraphql-ide-dialog-message">
				This will delete <strong>{count}</strong>{' '}
				{count === 1 ? 'entry' : 'entries'}
				{totalSize > 0 && (
					<>
						{' '}
						(<strong>{formatBytes(totalSize)}</strong>)
					</>
				)}{' '}
				from the WordPress object cache. The next request that matches
				each query will re-populate the cache. This cannot be undone.
			</p>
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
					{submitting ? 'Purging…' : 'Purge all'}
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

function EntriesTable({ entries, purging, onPurge }) {
	return (
		<table className="wpgraphql-ide-cache-inspector-table">
			<thead>
				<tr>
					<th scope="col">Cache key</th>
					<th scope="col" className="is-numeric">
						Size
					</th>
					<th scope="col" className="is-numeric">
						Expires in
					</th>
					<th
						scope="col"
						className="is-actions"
						aria-label="Actions"
					/>
				</tr>
			</thead>
			<tbody>
				{entries.map((entry) => (
					<EntryRow
						key={entry.cacheKey}
						entry={entry}
						isPurging={purging.has(entry.cacheKey)}
						onPurge={onPurge}
					/>
				))}
			</tbody>
		</table>
	);
}

function EntryRow({ entry, isPurging, onPurge }) {
	return (
		<tr>
			<td>
				<code
					className="wpgraphql-ide-cache-inspector-key"
					title={entry.cacheKey}
				>
					{truncateMiddle(entry.cacheKey, 20)}
				</code>
			</td>
			<td className="is-numeric">{formatBytes(entry.sizeBytes)}</td>
			<td className="is-numeric">
				{entry.expiresIn === null
					? '—'
					: formatDuration(entry.expiresIn)}
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
