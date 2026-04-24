import React, { useState, useMemo } from 'react';
import { Button, SearchControl, Tooltip } from '@wordpress/components';
import { Icon, plus, close, file } from '@wordpress/icons';
import { useSelect, useDispatch } from '@wordpress/data';
import { isTempId } from '../stores/document-editor/document-editor-store-actions';

/**
 * Saved Queries panel icon for the activity bar.
 */
export const SavedQueriesIcon = () => <Icon icon={file} />;

/**
 * Saved Queries panel — browse all saved documents with search.
 *
 * Shows all persisted documents (saved to the server) and unsaved
 * in-memory documents in a separate section. Click to open in a tab.
 */
export function SavedQueriesPanel() {
	const [search, setSearch] = useState('');

	const { documents, openTabs, activeTab } = useSelect((select) => {
		const editor = select('wpgraphql-ide/document-editor');
		return {
			documents: editor.getDocuments(),
			openTabs: editor.getOpenTabs(),
			activeTab: editor.getActiveTab(),
		};
	}, []);

	const { switchTab, createTab, removeDocument } = useDispatch(
		'wpgraphql-ide/document-editor'
	);

	const savedDocs = useMemo(
		() => documents.filter((d) => !isTempId(d.id)),
		[documents]
	);

	const unsavedDocs = useMemo(
		() => documents.filter((d) => isTempId(d.id)),
		[documents]
	);

	const filteredSaved = useMemo(() => {
		if (!search.trim()) {
			return savedDocs;
		}
		const q = search.toLowerCase();
		return savedDocs.filter(
			(d) =>
				(d.title || '').toLowerCase().includes(q) ||
				(d.query || '').toLowerCase().includes(q)
		);
	}, [savedDocs, search]);

	const renderDoc = (doc) => {
		const isActive = String(doc.id) === String(activeTab);
		const isOpen = openTabs.includes(String(doc.id));
		const isUnsaved = isTempId(doc.id);

		return (
			<li
				key={doc.id}
				className={`wpgraphql-ide-document-item${isActive ? ' is-active' : ''}`}
			>
				<button
					type="button"
					className="wpgraphql-ide-document-label"
					onClick={() => switchTab(String(doc.id))}
				>
					{isOpen && (
						<span className="wpgraphql-ide-document-open-dot" />
					)}
					{isUnsaved && doc.dirty && (
						<span className="wpgraphql-ide-document-dirty-dot" />
					)}
					{doc.title || 'Untitled'}
				</button>
				<Tooltip text="Delete">
					<button
						type="button"
						className="wpgraphql-ide-document-close"
						onClick={(e) => {
							e.stopPropagation();
							if (
								// eslint-disable-next-line no-alert
								window.confirm(
									`Delete "${doc.title || 'Untitled'}"?`
								)
							) {
								removeDocument(doc.id);
							}
						}}
						aria-label="Delete document"
					>
						<Icon icon={close} size={12} />
					</button>
				</Tooltip>
			</li>
		);
	};

	return (
		<div className="wpgraphql-ide-saved-queries-panel">
			<div className="wpgraphql-ide-documents-header">
				<Tooltip text="New document">
					<Button
						size="compact"
						onClick={() => createTab()}
						aria-label="New document"
					>
						<Icon icon={plus} />
					</Button>
				</Tooltip>
			</div>
			<div className="wpgraphql-ide-saved-queries-search">
				<SearchControl
					value={search}
					onChange={setSearch}
					placeholder="Search documents..."
					__nextHasNoMarginBottom
				/>
			</div>
			{filteredSaved.length > 0 && (
				<ul className="wpgraphql-ide-documents-list">
					{filteredSaved.map(renderDoc)}
				</ul>
			)}
			{filteredSaved.length === 0 && savedDocs.length === 0 && (
				<p className="wpgraphql-ide-saved-queries-empty">
					No saved documents yet. Save a query with Cmd+S.
				</p>
			)}
			{filteredSaved.length === 0 && savedDocs.length > 0 && (
				<p className="wpgraphql-ide-saved-queries-empty">
					No documents match &ldquo;{search}&rdquo;
				</p>
			)}
			{unsavedDocs.length > 0 && (
				<>
					<div className="wpgraphql-ide-saved-queries-divider">
						Unsaved
					</div>
					<ul className="wpgraphql-ide-documents-list">
						{unsavedDocs.map(renderDoc)}
					</ul>
				</>
			)}
		</div>
	);
}
