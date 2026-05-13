import React, { useMemo, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, CheckboxControl, Modal } from '@wordpress/components';

/**
 * Trigger a JSON download for the given data.
 *
 * @param {Object} data     Payload to serialize.
 * @param {string} filename File name for the download.
 */
function downloadJson(data, filename) {
	const blob = new Blob([JSON.stringify(data, null, 2)], {
		type: 'application/json',
	});
	const url = URL.createObjectURL(blob);
	const a = document.createElement('a');
	a.href = url;
	a.download = filename;
	document.body.appendChild(a);
	a.click();
	document.body.removeChild(a);
	URL.revokeObjectURL(url);
}

/**
 * Pick which collections to include in an export, then download the
 * filtered payload as JSON. Mirrors the ShareDialog UX.
 *
 * @param {Object}   props
 * @param {Function} props.fetchPayload  Returns the full export payload.
 * @param {Array}    [props.collections] Collection list (id/name). Used to
 *                                       pre-populate the toggle list.
 * @param {Function} props.onClose       Modal close callback.
 */
export function ExportDialog({ fetchPayload, collections = [], onClose }) {
	const [loading, setLoading] = useState(false);
	const initialSelection = useMemo(() => {
		const sel = {};
		for (const c of collections) {
			sel[c.name] = true;
		}
		return sel;
	}, [collections]);
	const [selected, setSelected] = useState(initialSelection);

	const selectedCount = useMemo(
		() => Object.values(selected).filter(Boolean).length,
		[selected]
	);

	const allChecked = collections.every((c) => selected[c.name]);
	const someChecked = collections.some((c) => selected[c.name]);

	const toggleAll = () => {
		const next = {};
		const target = !allChecked;
		for (const c of collections) {
			next[c.name] = target;
		}
		setSelected(next);
	};

	const handleExport = async () => {
		setLoading(true);
		try {
			const payload = await fetchPayload();
			const filtered = {
				...payload,
				collections: (payload.collections || []).filter(
					(c) => selected[c.name]
				),
			};
			downloadJson(
				filtered,
				`wpgraphql-ide-documents-${new Date().toISOString().slice(0, 10)}.json`
			);
			onClose();
		} catch (err) {
			// eslint-disable-next-line no-console
			console.error('Export failed:', err);
		} finally {
			setLoading(false);
		}
	};

	return (
		<Modal
			title={__('Export documents', 'wpgraphql-ide')}
			onRequestClose={onClose}
			className="wpgraphql-ide-dialog wpgraphql-ide-export-dialog"
		>
			<p className="wpgraphql-ide-dialog-message">
				{__(
					'Pick which collections to include in the exported file.',
					'wpgraphql-ide'
				)}
			</p>
			<div className="wpgraphql-ide-export-list">
				{collections.length === 0 ? (
					<p className="wpgraphql-ide-export-empty">
						{__('No collections to export.', 'wpgraphql-ide')}
					</p>
				) : (
					<>
						<CheckboxControl
							label={sprintf(
								/* translators: %d: total number of collections available to export */
								__('All collections (%d)', 'wpgraphql-ide'),
								collections.length
							)}
							checked={allChecked}
							indeterminate={!allChecked && someChecked}
							onChange={toggleAll}
							__nextHasNoMarginBottom
						/>
						<div className="wpgraphql-ide-export-divider" />
						{collections.map((c) => (
							<CheckboxControl
								key={c.id}
								label={c.name}
								checked={!!selected[c.name]}
								onChange={(checked) =>
									setSelected((prev) => ({
										...prev,
										[c.name]: checked,
									}))
								}
								__nextHasNoMarginBottom
							/>
						))}
					</>
				)}
			</div>
			<div className="wpgraphql-ide-dialog-actions wpgraphql-ide-export-footer">
				<Button variant="tertiary" onClick={onClose} disabled={loading}>
					{__('Cancel', 'wpgraphql-ide')}
				</Button>
				<Button
					variant="primary"
					onClick={handleExport}
					disabled={loading || selectedCount === 0}
					isBusy={loading}
				>
					{selectedCount === collections.length
						? __('Export all', 'wpgraphql-ide')
						: sprintf(
								/* translators: %d: number of collections selected for export */
								_n(
									'Export %d collection',
									'Export %d collections',
									selectedCount,
									'wpgraphql-ide'
								),
								selectedCount
							)}
				</Button>
			</div>
		</Modal>
	);
}
