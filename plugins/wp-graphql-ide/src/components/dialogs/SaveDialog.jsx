import React, { useState } from 'react';
import {
	Button,
	Modal,
	SelectControl,
	TextControl,
} from '@wordpress/components';

// Save dialog for promoting a temp draft to a real document. Lets the
// user pick the title and an optional collection in one step instead
// of saving first and re-organising later. Props:
//   { defaultTitle, collections, onSave({title, collectionId|null}), onClose }
export function SaveDialog({
	defaultTitle = '',
	collections = [],
	onSave,
	onClose,
}) {
	const [title, setTitle] = useState(defaultTitle);
	const [collectionId, setCollectionId] = useState('');
	const [saving, setSaving] = useState(false);

	const submit = async () => {
		const trimmed = title.trim();
		if (!trimmed || saving) {
			return;
		}
		setSaving(true);
		try {
			await onSave({
				title: trimmed,
				collectionId: collectionId === '' ? null : Number(collectionId),
			});
			onClose();
		} catch (e) {
			setSaving(false);
		}
	};

	const collectionOptions = [
		{ label: '— None —', value: '' },
		...collections.map((c) => ({
			label: c.name,
			value: String(c.id),
		})),
	];

	return (
		<Modal
			title="Save document"
			onRequestClose={() => (saving ? null : onClose())}
			className="wpgraphql-ide-dialog wpgraphql-ide-save-dialog"
		>
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
				value={collectionId}
				options={collectionOptions}
				onChange={setCollectionId}
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			<div className="wpgraphql-ide-dialog-actions">
				<Button variant="tertiary" onClick={onClose} disabled={saving}>
					Cancel
				</Button>
				<Button
					variant="primary"
					onClick={submit}
					disabled={!title.trim() || saving}
					isBusy={saving}
				>
					Save
				</Button>
			</div>
		</Modal>
	);
}
