import React, { useState } from 'react';
import {
	Button,
	Modal,
	SelectControl,
	TextControl,
} from '@wordpress/components';

const NEW_COLLECTION_OPTION = '__new__';

const MODE_LABELS = {
	save: {
		title: 'Save document',
		submit: 'Save',
		submitting: 'Saving',
	},
	rename: {
		title: 'Rename document',
		submit: 'Rename',
		submitting: 'Renaming',
	},
};

// Document details dialog — shared between the "Save" flow (promoting
// a temp draft to a real document) and the "Rename" flow (editing an
// existing saved document). Both flows let the user set/change the
// title and collection (with inline collection creation) in a single
// step.
//
// Props:
//   {
//     mode?: 'save' | 'rename',           // default 'save'
//     defaultTitle?: string,
//     defaultCollectionId?: number|null,  // only used by rename
//     collections: Array<{id, name}>,
//     onCreateCollection?: (name) => Promise<{id}>,
//     onSubmit: ({title, collectionId|null}) => Promise<void>,
//     onClose: () => void,
//   }
export function SaveDialog({
	mode = 'save',
	defaultTitle = '',
	defaultCollectionId = null,
	collections = [],
	onCreateCollection,
	onSubmit,
	// Backwards-compat: callers used to pass `onSave`. Either alias works.
	onSave,
	onClose,
}) {
	const submitHandler = onSubmit || onSave;
	const labels = MODE_LABELS[mode] || MODE_LABELS.save;
	const [title, setTitle] = useState(defaultTitle);
	const [collectionId, setCollectionId] = useState(
		defaultCollectionId !== null && defaultCollectionId !== undefined
			? String(defaultCollectionId)
			: ''
	);
	const [newCollectionName, setNewCollectionName] = useState('');
	const [creatingCollection, setCreatingCollection] = useState(false);
	const [submitting, setSubmitting] = useState(false);

	const submit = async () => {
		const trimmed = title.trim();
		if (!trimmed || submitting || !submitHandler) {
			return;
		}
		setSubmitting(true);
		try {
			await submitHandler({
				title: trimmed,
				collectionId: collectionId === '' ? null : Number(collectionId),
			});
			onClose();
		} catch (e) {
			setSubmitting(false);
		}
	};

	const handleCreateCollection = async () => {
		const name = newCollectionName.trim();
		if (!name || !onCreateCollection) {
			return;
		}
		const created = await onCreateCollection(name);
		if (created?.id) {
			setCollectionId(String(created.id));
		}
		setNewCollectionName('');
		setCreatingCollection(false);
	};

	const cancelNewCollection = () => {
		setCreatingCollection(false);
		setNewCollectionName('');
	};

	const handleCollectionChange = (value) => {
		if (value === NEW_COLLECTION_OPTION) {
			setCreatingCollection(true);
			return;
		}
		setCollectionId(value);
	};

	const collectionOptions = [
		{ label: '— None —', value: '' },
		...collections.map((c) => ({
			label: c.name,
			value: String(c.id),
		})),
		...(onCreateCollection
			? [{ label: '+ New collection…', value: NEW_COLLECTION_OPTION }]
			: []),
	];

	return (
		<Modal
			title={labels.title}
			onRequestClose={() => (submitting ? null : onClose())}
			className={`wpgraphql-ide-dialog wpgraphql-ide-save-dialog wpgraphql-ide-save-dialog--${mode}`}
		>
			<div className="wpgraphql-ide-save-dialog-fields">
				<TextControl
					label="Document name"
					value={title}
					onChange={setTitle}
					placeholder="e.g. Recent Posts by Author"
					// eslint-disable-next-line jsx-a11y/no-autofocus
					autoFocus
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					onKeyDown={(e) => {
						if (e.key === 'Enter') {
							e.preventDefault();
							submit();
						}
					}}
				/>
				<SelectControl
					label="Collection"
					value={
						creatingCollection
							? NEW_COLLECTION_OPTION
							: collectionId
					}
					options={collectionOptions}
					onChange={handleCollectionChange}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				{creatingCollection && (
					<div className="wpgraphql-ide-save-dialog-new-collection">
						<TextControl
							label="New collection name"
							value={newCollectionName}
							onChange={setNewCollectionName}
							placeholder="Collection name"
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							onKeyDown={(e) => {
								if (e.key === 'Enter') {
									e.preventDefault();
									handleCreateCollection();
								}
								if (e.key === 'Escape') {
									cancelNewCollection();
								}
							}}
						/>
						<div className="wpgraphql-ide-save-dialog-new-collection-actions">
							<Button
								variant="tertiary"
								onClick={cancelNewCollection}
							>
								Cancel
							</Button>
							<Button
								variant="secondary"
								onClick={handleCreateCollection}
								disabled={!newCollectionName.trim()}
							>
								Create collection
							</Button>
						</div>
					</div>
				)}
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
					disabled={!title.trim() || submitting}
					isBusy={submitting}
				>
					{labels.submit}
				</Button>
			</div>
		</Modal>
	);
}
