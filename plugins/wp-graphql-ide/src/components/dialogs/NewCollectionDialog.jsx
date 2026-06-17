import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Modal,
	RadioControl,
	TextControl,
} from '@wordpress/components';

/**
 * Create-collection dialog. One entry point for both sitewide and
 * personal collections — the user picks visibility, types a name, hits
 * create. Mirrors the modal style of the Share / Export dialogs.
 *
 * @param {Object}   props
 * @param {Function} props.onCreateSitewide Called with `(name)` to create a sitewide (taxonomy) collection.
 * @param {Function} props.onCreatePersonal Called with `(name)` to create a personal (user-meta) collection.
 * @param {Function} props.onClose          Close the dialog.
 * @return {JSX.Element}
 */
export function NewCollectionDialog({
	onCreateSitewide,
	onCreatePersonal,
	onClose,
}) {
	const [name, setName] = useState('');
	const [visibility, setVisibility] = useState('sitewide');
	const [submitting, setSubmitting] = useState(false);

	const submit = async () => {
		const trimmed = name.trim();
		if (!trimmed || submitting) {
			return;
		}
		setSubmitting(true);
		try {
			if (visibility === 'personal') {
				await onCreatePersonal(trimmed);
			} else {
				await onCreateSitewide(trimmed);
			}
			onClose();
		} catch (e) {
			setSubmitting(false);
		}
	};

	return (
		<Modal
			title={__('New collection', 'wpgraphql-ide')}
			onRequestClose={() => (submitting ? null : onClose())}
			className="wpgraphql-ide-dialog wpgraphql-ide-new-collection-dialog"
		>
			<div className="wpgraphql-ide-dialog-stack">
				<TextControl
					label={__('Name', 'wpgraphql-ide')}
					value={name}
					onChange={setName}
					placeholder={__('e.g. Reporting queries', 'wpgraphql-ide')}
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
				<RadioControl
					label={__('Visibility', 'wpgraphql-ide')}
					selected={visibility}
					onChange={setVisibility}
					options={[
						{
							label: __(
								'Sitewide — visible to anyone with IDE access',
								'wpgraphql-ide'
							),
							value: 'sitewide',
						},
						{
							label: __(
								'Personal — only visible to you',
								'wpgraphql-ide'
							),
							value: 'personal',
						},
					]}
				/>
			</div>
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
					onClick={submit}
					disabled={!name.trim() || submitting}
					isBusy={submitting}
				>
					{__('Create', 'wpgraphql-ide')}
				</Button>
			</div>
		</Modal>
	);
}
