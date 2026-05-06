import React, { useCallback, useMemo, useState } from 'react';

/**
 * Free-form multi-tag input for fields like alias names.
 *
 * Tags are committed on Enter, comma, or blur. Duplicates and empty strings
 * are dropped client-side; the server is the source of truth for cross-document
 * uniqueness validation.
 *
 * @param {Object}   props
 * @param {Object}   props.field      Registered field descriptor.
 * @param {string[]} props.value      Current tag list.
 * @param {Function} props.onChange   Receives the next tag array.
 * @param {boolean}  [props.disabled]
 *
 * @return {JSX.Element}
 */
export function TagListField({ field, value, onChange, disabled = false }) {
	const tags = useMemo(() => (Array.isArray(value) ? value : []), [value]);
	const [input, setInput] = useState('');
	const inputId = `wpgraphql-ide-doc-setting-${field.name}`;

	const commit = useCallback(
		(raw) => {
			if (typeof raw !== 'string') {
				return;
			}
			const next = raw.trim();
			if (next === '' || tags.includes(next)) {
				setInput('');
				return;
			}
			onChange([...tags, next]);
			setInput('');
		},
		[tags, onChange]
	);

	const removeAt = useCallback(
		(idx) => {
			if (disabled) {
				return;
			}
			onChange(tags.filter((_, i) => i !== idx));
		},
		[tags, onChange, disabled]
	);

	const handleKeyDown = useCallback(
		(e) => {
			if (e.key === 'Enter' || e.key === ',') {
				e.preventDefault();
				commit(input);
			} else if (
				e.key === 'Backspace' &&
				input === '' &&
				tags.length > 0
			) {
				removeAt(tags.length - 1);
			}
		},
		[input, tags, commit, removeAt]
	);

	return (
		<div className="wpgraphql-ide-doc-setting wpgraphql-ide-doc-setting--tag-list">
			<label
				htmlFor={inputId}
				className="wpgraphql-ide-doc-setting-label"
			>
				{field.label}
			</label>
			<div
				className={`wpgraphql-ide-doc-setting-tag-list${disabled ? ' is-disabled' : ''}`}
			>
				{tags.map((tag, idx) => (
					<span
						key={`${tag}-${idx}`}
						className="wpgraphql-ide-doc-setting-tag"
					>
						{tag}
						<button
							type="button"
							aria-label={`Remove ${tag}`}
							className="wpgraphql-ide-doc-setting-tag-remove"
							onClick={() => removeAt(idx)}
							disabled={disabled}
						>
							×
						</button>
					</span>
				))}
				<input
					id={inputId}
					type="text"
					className="wpgraphql-ide-doc-setting-tag-input"
					value={input}
					disabled={disabled}
					placeholder={tags.length === 0 ? 'Add an alias…' : ''}
					onChange={(e) => setInput(e.target.value)}
					onKeyDown={handleKeyDown}
					onBlur={() => commit(input)}
				/>
			</div>
			{field.desc ? (
				<p
					className="wpgraphql-ide-doc-setting-desc"
					dangerouslySetInnerHTML={{ __html: field.desc }}
				/>
			) : null}
		</div>
	);
}
