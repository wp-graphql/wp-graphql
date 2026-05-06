import React from 'react';

const GRANT_MODE_LABELS = {
	public: 'Allowed',
	only_allowed: 'Deny',
	some_denied: 'Allowed',
};

/**
 * Three-state radio group whose "default" option's label dynamically reflects
 * a global setting (e.g. "Use global default (Allowed)"). The default option
 * is identified as the one whose `value` is an empty string.
 *
 * @param {Object}   props
 * @param {Object}   props.field           Registered field descriptor.
 * @param {string}   props.value           Current selection.
 * @param {Function} props.onChange        Receives the next value.
 * @param {string}   [props.globalDefault] Global default key (e.g. 'public').
 * @param {boolean}  [props.disabled]
 *
 * @return {JSX.Element}
 */
export function RadioWithDefaultField({
	field,
	value,
	onChange,
	globalDefault = 'public',
	disabled = false,
}) {
	const groupName = `wpgraphql-ide-doc-setting-${field.name}`;
	const options = Array.isArray(field.options) ? field.options : [];
	const effectiveLabel = GRANT_MODE_LABELS[globalDefault] || 'Allowed';

	return (
		<div className="wpgraphql-ide-doc-setting wpgraphql-ide-doc-setting--radio">
			<span className="wpgraphql-ide-doc-setting-label">
				{field.label}
			</span>
			<div className="wpgraphql-ide-doc-setting-radio-group">
				{options.map((opt) => {
					const radioId = `${groupName}-${opt.value || 'default'}`;
					const label =
						opt.value === ''
							? `${opt.label} (${effectiveLabel})`
							: opt.label;
					return (
						<label
							key={opt.value}
							htmlFor={radioId}
							className="wpgraphql-ide-doc-setting-radio-option"
						>
							<input
								id={radioId}
								type="radio"
								name={groupName}
								value={opt.value}
								checked={(value ?? '') === opt.value}
								disabled={disabled}
								onChange={() => onChange(opt.value)}
							/>
							<span>{label}</span>
						</label>
					);
				})}
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
