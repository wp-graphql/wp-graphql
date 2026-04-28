import React from 'react';

/**
 * Render a single registered WPGraphQL settings field.
 *
 * Maps each registered field type to the appropriate native control. Stays
 * deliberately controlled (value/onChange) so the parent owns autosave state.
 *
 * @param {Object}   props
 * @param {Object}   props.field    Field descriptor from the registry snapshot.
 * @param {*}        props.value    Current value.
 * @param {Function} props.onChange Receives the next value.
 *
 * @return {JSX.Element}
 */
export function SettingsField({ field, value, onChange }) {
	const inputId = `wpgraphql-ide-setting-${field.name}`;
	const desc = field.desc ? (
		<p
			className="wpgraphql-ide-setting-desc"
			dangerouslySetInnerHTML={{ __html: field.desc }}
		/>
	) : null;

	const labelEl = field.label ? (
		<label htmlFor={inputId} className="wpgraphql-ide-setting-label">
			{field.label}
		</label>
	) : null;

	switch (field.type) {
		case 'checkbox':
			return (
				<div className="wpgraphql-ide-setting wpgraphql-ide-setting--checkbox">
					<label
						htmlFor={inputId}
						className="wpgraphql-ide-setting-checkbox"
					>
						<input
							id={inputId}
							type="checkbox"
							checked={value === 'on' || value === true}
							disabled={field.disabled}
							onChange={(e) => onChange(e.target.checked)}
						/>
						<span>{field.label}</span>
					</label>
					{desc}
				</div>
			);

		case 'number':
			return (
				<div className="wpgraphql-ide-setting">
					{labelEl}
					<input
						id={inputId}
						type="number"
						value={value ?? ''}
						min={field.min ?? undefined}
						max={field.max ?? undefined}
						step={field.step ?? undefined}
						disabled={field.disabled}
						onChange={(e) =>
							onChange(
								e.target.value === ''
									? ''
									: Number(e.target.value)
							)
						}
					/>
					{desc}
				</div>
			);

		case 'textarea':
			return (
				<div className="wpgraphql-ide-setting">
					{labelEl}
					<textarea
						id={inputId}
						value={value ?? ''}
						disabled={field.disabled}
						placeholder={field.placeholder || ''}
						rows={5}
						onChange={(e) => onChange(e.target.value)}
					/>
					{desc}
				</div>
			);

		case 'url':
			return (
				<div className="wpgraphql-ide-setting">
					{labelEl}
					<input
						id={inputId}
						type="url"
						value={value ?? ''}
						disabled={field.disabled}
						placeholder={field.placeholder || ''}
						onChange={(e) => onChange(e.target.value)}
					/>
					{desc}
				</div>
			);

		case 'select': {
			const options = field.options || {};
			return (
				<div className="wpgraphql-ide-setting">
					{labelEl}
					<select
						id={inputId}
						value={value ?? ''}
						disabled={field.disabled}
						onChange={(e) => onChange(e.target.value)}
					>
						{Object.entries(options).map(([key, label]) => (
							<option key={key} value={key}>
								{label}
							</option>
						))}
					</select>
					{desc}
				</div>
			);
		}

		case 'radio': {
			const options = field.options || {};
			return (
				<div className="wpgraphql-ide-setting">
					{labelEl}
					<div className="wpgraphql-ide-setting-radio-group">
						{Object.entries(options).map(([key, label]) => {
							const radioId = `${inputId}-${key}`;
							return (
								<label
									key={key}
									htmlFor={radioId}
									className="wpgraphql-ide-setting-radio"
								>
									<input
										id={radioId}
										type="radio"
										name={inputId}
										value={key}
										checked={String(value) === String(key)}
										disabled={field.disabled}
										onChange={() => onChange(key)}
									/>
									<span>{label}</span>
								</label>
							);
						})}
					</div>
					{desc}
				</div>
			);
		}

		case 'multicheck': {
			const options = field.options || {};
			const selected = Array.isArray(value) ? value : [];
			return (
				<div className="wpgraphql-ide-setting">
					{labelEl}
					<div className="wpgraphql-ide-setting-multicheck">
						{Object.entries(options).map(([key, label]) => {
							const isChecked = selected.includes(key);
							const checkId = `${inputId}-${key}`;
							return (
								<label
									key={key}
									htmlFor={checkId}
									className="wpgraphql-ide-setting-checkbox"
								>
									<input
										id={checkId}
										type="checkbox"
										checked={isChecked}
										disabled={field.disabled}
										onChange={(e) => {
											const next = e.target.checked
												? [...selected, key]
												: selected.filter(
														(k) => k !== key
													);
											onChange(next);
										}}
									/>
									<span>{label}</span>
								</label>
							);
						})}
					</div>
					{desc}
				</div>
			);
		}

		case 'html':
			return (
				<div className="wpgraphql-ide-setting wpgraphql-ide-setting--html">
					{labelEl}
					{desc}
				</div>
			);

		case 'color':
		case 'user_role_select':
			return (
				<div className="wpgraphql-ide-setting wpgraphql-ide-setting--unsupported">
					{labelEl}
					<p className="wpgraphql-ide-setting-unsupported">
						This field type ({field.type}) is not yet editable in
						the IDE. Use the WPGraphQL admin settings page.
					</p>
					{desc}
				</div>
			);

		case 'text':
		default:
			return (
				<div className="wpgraphql-ide-setting">
					{labelEl}
					<input
						id={inputId}
						type="text"
						value={value ?? ''}
						disabled={field.disabled}
						placeholder={field.placeholder || ''}
						onChange={(e) => onChange(e.target.value)}
					/>
					{desc}
				</div>
			);
	}
}
