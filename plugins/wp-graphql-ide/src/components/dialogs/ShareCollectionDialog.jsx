import React, { useState, useEffect, useMemo, useCallback } from 'react';
import { Button, Modal, SearchControl, Spinner } from '@wordpress/components';
import { Icon, close, plus } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

/**
 * Share a personal collection with specific other users.
 *
 * Reads the collection's current `shared_with` list and lets the owner
 * add or remove user IDs. On save, calls `onSubmit(nextSharedWith)` with
 * the full updated list — caller is responsible for persisting.
 *
 * @param {Object}   props
 * @param {Object}   props.collection The personal collection (with name + shared_with).
 * @param {Function} props.onSubmit   Called with `(newSharedWith: number[])`.
 * @param {Function} props.onClose    Close the dialog.
 * @return {JSX.Element}
 */
export function ShareCollectionDialog({ collection, onSubmit, onClose }) {
	const [shared, setShared] = useState(
		Array.isArray(collection?.shared_with) ? collection.shared_with : []
	);
	const [search, setSearch] = useState('');
	const [searchResults, setSearchResults] = useState([]);
	const [searching, setSearching] = useState(false);
	const [usersById, setUsersById] = useState({});
	const [submitting, setSubmitting] = useState(false);

	// Hydrate display names for already-shared users so the chip list reads
	// nicely on first paint.
	useEffect(() => {
		if (shared.length === 0) {
			return;
		}
		const missing = shared.filter((id) => !usersById[id]);
		if (missing.length === 0) {
			return;
		}
		apiFetch({
			path: `/wp/v2/users?include=${missing.join(',')}&per_page=${missing.length}`,
		})
			.then((users) => {
				const map = { ...usersById };
				for (const u of users) {
					map[u.id] = u;
				}
				setUsersById(map);
			})
			.catch(() => {});
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [shared]);

	// Debounced user search.
	useEffect(() => {
		const term = search.trim();
		if (term.length < 2) {
			setSearchResults([]);
			return undefined;
		}
		setSearching(true);
		const timer = setTimeout(() => {
			apiFetch({
				path: `/wp/v2/users?search=${encodeURIComponent(term)}&per_page=10`,
			})
				.then((users) => {
					setSearchResults(users || []);
				})
				.catch(() => setSearchResults([]))
				.finally(() => setSearching(false));
		}, 250);
		return () => clearTimeout(timer);
	}, [search]);

	const addUser = useCallback((user) => {
		setShared((prev) =>
			prev.includes(user.id) ? prev : [...prev, user.id]
		);
		setUsersById((prev) => ({ ...prev, [user.id]: user }));
		setSearch('');
		setSearchResults([]);
	}, []);

	const removeUser = useCallback((id) => {
		setShared((prev) => prev.filter((x) => x !== id));
	}, []);

	const submit = async () => {
		if (submitting) {
			return;
		}
		setSubmitting(true);
		try {
			await onSubmit(shared);
			onClose();
		} catch (e) {
			setSubmitting(false);
		}
	};

	const visibleResults = useMemo(
		() => searchResults.filter((u) => !shared.includes(u.id)),
		[searchResults, shared]
	);

	return (
		<Modal
			title={`Share "${collection?.name || 'Collection'}"`}
			onRequestClose={() => (submitting ? null : onClose())}
			className="wpgraphql-ide-dialog wpgraphql-ide-share-collection-dialog"
		>
			<div className="wpgraphql-ide-dialog-stack">
				<p className="wpgraphql-ide-dialog-message">
					Add users who should see this collection. They get read-only
					access — you remain the only person who can edit it.
				</p>
				{/* Form wrapper lets Enter submit, which we hijack to add the
				    first visible search result. Keyboard-only users can find
				    a person and add them without grabbing the mouse. */}
				<form
					onSubmit={(e) => {
						e.preventDefault();
						if (visibleResults.length > 0) {
							addUser(visibleResults[0]);
						}
					}}
				>
					<SearchControl
						label="Add user"
						value={search}
						onChange={setSearch}
						placeholder="Search by name or username…"
						__nextHasNoMarginBottom
					/>
				</form>
				{searching && (
					<div className="wpgraphql-ide-share-collection-status">
						<Spinner /> Searching…
					</div>
				)}
				{visibleResults.length > 0 && (
					<ul className="wpgraphql-ide-share-collection-results">
						{visibleResults.map((u) => {
							const showSlug =
								u.slug &&
								u.slug.toLowerCase() !==
									(u.name || '').toLowerCase();
							return (
								<li key={u.id}>
									<button
										type="button"
										className="wpgraphql-ide-share-collection-result"
										onClick={() => addUser(u)}
									>
										<span className="wpgraphql-ide-share-collection-result-name">
											{u.name}
										</span>
										{showSlug && (
											<span className="wpgraphql-ide-share-collection-result-slug">
												@{u.slug}
											</span>
										)}
										<Icon
											icon={plus}
											size={16}
											className="wpgraphql-ide-share-collection-result-add"
										/>
									</button>
								</li>
							);
						})}
					</ul>
				)}
				<div className="wpgraphql-ide-share-collection-shared">
					{shared.length === 0 ? (
						<p className="wpgraphql-ide-share-collection-empty">
							Not shared with anyone yet.
						</p>
					) : (
						<>
							<div className="wpgraphql-ide-share-collection-shared-label">
								Shared with ({shared.length})
							</div>
							<ul className="wpgraphql-ide-share-collection-chips">
								{shared.map((id) => {
									const user = usersById[id];
									const label = user
										? user.name
										: `User #${id}`;
									return (
										<li
											key={id}
											className="wpgraphql-ide-share-collection-chip"
										>
											<span>{label}</span>
											<button
												type="button"
												className="wpgraphql-ide-share-collection-chip-remove"
												aria-label={`Remove ${label}`}
												onClick={() => removeUser(id)}
											>
												<Icon icon={close} size={14} />
											</button>
										</li>
									);
								})}
							</ul>
						</>
					)}
				</div>
			</div>
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
					onClick={submit}
					disabled={submitting}
					isBusy={submitting}
				>
					Save
				</Button>
			</div>
		</Modal>
	);
}
