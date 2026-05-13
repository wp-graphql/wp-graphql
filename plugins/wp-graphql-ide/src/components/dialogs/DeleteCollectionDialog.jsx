import React, { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Button, CheckboxControl, Modal } from '@wordpress/components';

// Confirmation dialog for deleting a collection. Defaults to keeping
// contents (documents move to "Documents"); a single checkbox lets the
// user opt into cascading the delete to documents in the collection.
// Props:
//   { name, onConfirm({ deleteContents })->Promise<void>, onClose }
export function DeleteCollectionDialog({ name, onConfirm, onClose }) {
	const [deleteContents, setDeleteContents] = useState(false);
	const [submitting, setSubmitting] = useState(false);

	const submit = async () => {
		if (submitting) {
			return;
		}
		setSubmitting(true);
		try {
			await onConfirm({ deleteContents });
			onClose();
		} catch (e) {
			setSubmitting(false);
		}
	};

	return (
		<Modal
			title={__('Delete collection', 'wpgraphql-ide')}
			onRequestClose={() => (submitting ? null : onClose())}
			className="wpgraphql-ide-dialog wpgraphql-ide-delete-collection-dialog"
		>
			<p className="wpgraphql-ide-dialog-message">
				{
					// Sentence is split around <strong>{name}</strong> so the name
					// keeps its visual emphasis; the surrounding phrase carries the
					// %s placeholder for translation.
				}
				{(() => {
					const parts = sprintf(
						/* translators: %s: %NAME% sentinel for the collection name */
						__(
							'Delete %s? Documents in this collection will move to "Documents" unless you also choose to delete them.',
							'wpgraphql-ide'
						),
						'%NAME%'
					).split('%NAME%');
					return (
						<>
							{parts[0]}
							<strong>{`"${name}"`}</strong>
							{parts[1] ?? ''}
						</>
					);
				})()}
			</p>
			<CheckboxControl
				label={__(
					'Also delete documents in this collection',
					'wpgraphql-ide'
				)}
				help={__('This cannot be undone.', 'wpgraphql-ide')}
				checked={deleteContents}
				onChange={setDeleteContents}
				__nextHasNoMarginBottom
			/>
			<div className="wpgraphql-ide-dialog-actions">
				<Button
					variant="tertiary"
					onClick={onClose}
					disabled={submitting}
				>
					{__('Cancel', 'wpgraphql-ide')}
				</Button>
				<Button
					variant="primary"
					isDestructive
					onClick={submit}
					isBusy={submitting}
					disabled={submitting}
				>
					{deleteContents
						? __('Delete collection and documents', 'wpgraphql-ide')
						: __('Delete collection', 'wpgraphql-ide')}
				</Button>
			</div>
		</Modal>
	);
}
