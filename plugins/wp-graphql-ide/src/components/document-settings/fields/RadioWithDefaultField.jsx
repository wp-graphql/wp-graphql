import React from 'react';
import { __, sprintf } from '@wordpress/i18n';

// Built lazily so __() runs after wp.i18n is available.
const getGrantModeLabels = () => ({
	public: __('Allowed', 'wpgraphql-ide'),
	only_allowed: __('Deny', 'wpgraphql-ide'),
	some_denied: __('Allowed', 'wpgraphql-ide'),
});

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
	const grantModeLabels = getGrantModeLabels();
	const effectiveLabel =
		grantModeLabels[globalDefault] || __('Allowed', 'wpgraphql-ide');

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
							? sprintf(
									/* translators: 1: base option label (e.g. "Use global default"); 2: resolved effective label (e.g. "Allowed") */
									__('%1$s (%2$s)', 'wpgraphql-ide'),
									opt.label,
									effectiveLabel
								)
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
