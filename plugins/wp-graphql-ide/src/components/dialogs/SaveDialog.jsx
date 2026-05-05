import React, { useState } from 'react';
import {
	Button,
	CheckboxControl,
	Modal,
	TextControl,
} from '@wordpress/components';
import { Icon, lock } from '@wordpress/icons';

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
// existing saved document). The user can assign the doc to any
// combination of sitewide and personal collections via checkboxes.
// Creating new collections happens in the dedicated NewCollectionDialog
// from the panel kebab.
//
// Props:
//   {
//     mode?: 'save' | 'rename',                    // default 'save'
//     defaultTitle?: string,
//     defaultCollectionIds?: number[],             // sitewide rename default
//     defaultPersonalCollectionIds?: string[],     // personal rename default
//     collections: Array<{id, name}>,              // sitewide
//     personalCollections?: Array<{id, name}>,     // user-meta backed
//     onSubmit: ({title, collectionIds, personalCollectionIds}) => Promise<void>,
//     onClose: () => void,
//   }
export function SaveDialog({
	mode = 'save',
	defaultTitle = '',
	defaultCollectionIds = [],
	defaultPersonalCollectionIds = [],
	// Backwards-compat with the singular default-id props that earlier
	// callers pass. If the array variant isn't supplied, fall back.
	defaultCollectionId = null,
	defaultPersonalCollectionId = null,
	collections = [],
	personalCollections = [],
	onSubmit,
	// Backwards-compat: callers used to pass `onSave`. Either alias works.
	onSave,
	onClose,
}) {
	const submitHandler = onSubmit || onSave;
	const labels = MODE_LABELS[mode] || MODE_LABELS.save;

	const seedSitewide = (() => {
		if (
			Array.isArray(defaultCollectionIds) &&
			defaultCollectionIds.length
		) {
			return defaultCollectionIds.map(Number);
		}
		if (defaultCollectionId !== null && defaultCollectionId !== undefined) {
			return [Number(defaultCollectionId)];
		}
		return [];
	})();
	const seedPersonal = (() => {
		if (
			Array.isArray(defaultPersonalCollectionIds) &&
			defaultPersonalCollectionIds.length
		) {
			return defaultPersonalCollectionIds.map(String);
		}
		if (defaultPersonalCollectionId) {
			return [String(defaultPersonalCollectionId)];
		}
		return [];
	})();

	const [title, setTitle] = useState(defaultTitle);
	const [pickedSitewide, setPickedSitewide] = useState(new Set(seedSitewide));
	const [pickedPersonal, setPickedPersonal] = useState(new Set(seedPersonal));
	const [submitting, setSubmitting] = useState(false);

	const toggleSitewide = (id) =>
		setPickedSitewide((prev) => {
			const next = new Set(prev);
			if (next.has(id)) {
				next.delete(id);
			} else {
				next.add(id);
			}
			return next;
		});

	const togglePersonal = (id) =>
		setPickedPersonal((prev) => {
			const next = new Set(prev);
			if (next.has(id)) {
				next.delete(id);
			} else {
				next.add(id);
			}
			return next;
		});

	const submit = async () => {
		const trimmed = title.trim();
		if (!trimmed || submitting || !submitHandler) {
			return;
		}
		setSubmitting(true);
		try {
			await submitHandler({
				title: trimmed,
				collectionIds: Array.from(pickedSitewide),
				personalCollectionIds: Array.from(pickedPersonal),
			});
			onClose();
		} catch (e) {
			setSubmitting(false);
		}
	};

	const hasAnyCollections =
		collections.length > 0 || personalCollections.length > 0;

	return (
		<Modal
			title={labels.title}
			onRequestClose={() => (submitting ? null : onClose())}
			className={`wpgraphql-ide-dialog wpgraphql-ide-save-dialog wpgraphql-ide-save-dialog--${mode}`}
		>
			<div className="wpgraphql-ide-dialog-stack">
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

				{!hasAnyCollections ? (
					<p className="wpgraphql-ide-save-dialog-empty">
						No collections yet — you can create one from the
						saved-queries panel.
					</p>
				) : (
					<>
						{collections.length > 0 && (
							<fieldset className="wpgraphql-ide-save-dialog-fieldset">
								<legend className="wpgraphql-ide-save-dialog-legend">
									Collections
								</legend>
								<ul className="wpgraphql-ide-save-dialog-checklist">
									{collections.map((c) => (
										<li key={c.id}>
											<CheckboxControl
												__nextHasNoMarginBottom
												label={c.name}
												checked={pickedSitewide.has(
													Number(c.id)
												)}
												onChange={() =>
													toggleSitewide(Number(c.id))
												}
											/>
										</li>
									))}
								</ul>
							</fieldset>
						)}
						{personalCollections.length > 0 && (
							<fieldset className="wpgraphql-ide-save-dialog-fieldset">
								<legend className="wpgraphql-ide-save-dialog-legend">
									<Icon icon={lock} size={14} /> Personal
									collections
								</legend>
								<ul className="wpgraphql-ide-save-dialog-checklist">
									{personalCollections.map((pc) => (
										<li key={pc.id}>
											<CheckboxControl
												__nextHasNoMarginBottom
												label={pc.name}
												checked={pickedPersonal.has(
													pc.id
												)}
												onChange={() =>
													togglePersonal(pc.id)
												}
											/>
										</li>
									))}
								</ul>
							</fieldset>
						)}
					</>
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
