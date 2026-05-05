import React, { useRef } from 'react';

/**
 * Inline rename input shared by the tab strip and the saved-queries
 * collection headers. Centralises the small-but-fiddly UX:
 * - Enter blurs (which commits via onCommit).
 * - Escape cancels (calls onCancel, suppresses commit-on-blur).
 * - Blur commits with the trimmed current value.
 * - Empty trimmed value on blur/Enter cancels instead of committing
 *   (callers don't want to clear a tab/collection name to "").
 * - Click stops propagation so the surrounding row doesn't toggle.
 *
 * Callers own the trimmed-value semantics — onCommit may decide to
 * no-op (e.g. unchanged value) or call out to a server. RenameInput
 * just delivers the value cleanly. Either onCommit or onCancel is
 * always invoked on blur, so callers can rely on one of them to exit
 * the editing state.
 *
 * @param {Object}   props
 * @param {string}   props.value
 * @param {Function} props.onChange    Called with the next raw input value.
 * @param {Function} props.onCommit    Called with the trimmed value on Enter or blur. Skipped if empty or after Escape.
 * @param {Function} [props.onCancel]  Called on Escape, or on blur/Enter when trimmed value is empty.
 * @param {string}   [props.className]
 * @param {string}   [props.ariaLabel]
 */
export function RenameInput({
	value,
	onChange,
	onCommit,
	onCancel,
	className,
	ariaLabel,
}) {
	const cancelledRef = useRef(false);

	const finish = () => {
		const trimmed = (value || '').trim();
		if (trimmed && onCommit) {
			onCommit(trimmed);
		} else if (onCancel) {
			onCancel();
		}
	};

	return (
		<input
			type="text"
			className={className}
			value={value}
			aria-label={ariaLabel}
			onChange={(e) => onChange(e.target.value)}
			onBlur={() => {
				if (cancelledRef.current) {
					cancelledRef.current = false;
					return;
				}
				finish();
			}}
			onKeyDown={(e) => {
				if (e.key === 'Enter') {
					e.preventDefault();
					e.target.blur();
				}
				if (e.key === 'Escape') {
					cancelledRef.current = true;
					if (onCancel) {
						onCancel();
					}
					e.target.blur();
				}
			}}
			onClick={(e) => e.stopPropagation()}
			// eslint-disable-next-line jsx-a11y/no-autofocus
			autoFocus
		/>
	);
}
