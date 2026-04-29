import React, { useState } from 'react';
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
			title="Delete collection"
			onRequestClose={() => (submitting ? null : onClose())}
			className="wpgraphql-ide-dialog wpgraphql-ide-delete-collection-dialog"
		>
			<p className="wpgraphql-ide-dialog-message">
				Delete <strong>&ldquo;{name}&rdquo;</strong>? Documents in this
				collection will move to &ldquo;Documents&rdquo; unless you also
				choose to delete them.
			</p>
			<CheckboxControl
				label="Also delete documents in this collection"
				help="This cannot be undone."
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
					Cancel
				</Button>
				<Button
					variant="primary"
					isDestructive
					onClick={submit}
					isBusy={submitting}
					disabled={submitting}
				>
					{deleteContents
						? 'Delete collection and documents'
						: 'Delete collection'}
				</Button>
			</div>
		</Modal>
	);
}
